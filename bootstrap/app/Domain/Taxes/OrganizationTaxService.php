<?php

namespace App\Domain\Taxes;

use App\Domain\Questionnaires\QuestionnaireQuote;
use App\Enums\TaxPriceMode;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationTax;
use InvalidArgumentException;

class OrganizationTaxService
{
    public function quote(Organization $organization, QuestionnaireQuote $quote): TaxQuote
    {
        $nonTaxableMinor = collect($quote->lines)
            ->where('lineType', 'resource_deposit')
            ->sum(fn ($line): int => $line->amountMinor);

        return $this->calculate($organization, $quote->totalMinor, (int) $nonTaxableMinor);
    }

    public function calculate(Organization $organization, int $amountMinor, int $nonTaxableMinor = 0): TaxQuote
    {
        if ($amountMinor < 0 || $nonTaxableMinor < 0 || $nonTaxableMinor > $amountMinor) {
            throw new InvalidArgumentException('The taxable booking amount is invalid.');
        }

        if (! $organization->collects_taxes) {
            return new TaxQuote($amountMinor, 0, $amountMinor, null, null, []);
        }

        $mode = $organization->tax_price_mode;
        if (! $mode instanceof TaxPriceMode || blank($organization->tax_identifier)) {
            throw new InvalidArgumentException('The organization tax configuration is incomplete.');
        }

        $taxes = $organization->relationLoaded('taxes')
            ? $organization->taxes->sortBy([['position', 'asc'], ['name', 'asc']])->values()
            : $organization->taxes()->orderBy('position')->orderBy('name')->get();
        if ($taxes->isEmpty()) {
            throw new InvalidArgumentException('The organization tax configuration needs at least one tax.');
        }

        foreach ($taxes as $tax) {
            if ((int) $tax->rate_millionths < 1 || (int) $tax->rate_millionths > TaxRate::ONE_HUNDRED_PERCENT) {
                throw new InvalidArgumentException('The organization contains an invalid tax percentage.');
            }
        }

        $taxableMinor = $amountMinor - $nonTaxableMinor;

        return $mode === TaxPriceMode::Inclusive
            ? $this->inclusive($taxes->all(), $taxableMinor, $nonTaxableMinor, $organization)
            : $this->exclusive($taxes->all(), $taxableMinor, $nonTaxableMinor, $organization);
    }

    public function persist(Booking $booking, TaxQuote $quote): void
    {
        foreach ($quote->lines as $line) {
            $booking->taxLines()->create([
                'name' => $line->name,
                'rate_millionths' => $line->rateMillionths,
                'amount_minor' => $line->amountMinor,
                'position' => $line->position,
            ]);
        }
    }

    /** @param list<OrganizationTax> $taxes */
    private function exclusive(array $taxes, int $taxableMinor, int $nonTaxableMinor, Organization $organization): TaxQuote
    {
        $lines = [];
        $taxTotal = 0;

        foreach ($taxes as $tax) {
            $amount = $this->multiplyAndRound($taxableMinor, (int) $tax->rate_millionths, TaxRate::ONE_HUNDRED_PERCENT);
            $taxTotal = $this->safeAdd($taxTotal, $amount);
            $lines[] = new TaxLine($tax->name, (int) $tax->rate_millionths, $amount, (int) $tax->position);
        }

        $subtotal = $this->safeAdd($taxableMinor, $nonTaxableMinor);

        return new TaxQuote(
            $subtotal,
            $taxTotal,
            $this->safeAdd($subtotal, $taxTotal),
            TaxPriceMode::Exclusive,
            (string) $organization->tax_identifier,
            $lines,
        );
    }

    /** @param list<OrganizationTax> $taxes */
    private function inclusive(array $taxes, int $taxableMinor, int $nonTaxableMinor, Organization $organization): TaxQuote
    {
        $combinedRate = array_sum(array_map(fn (OrganizationTax $tax): int => (int) $tax->rate_millionths, $taxes));
        $denominator = $this->safeAdd(TaxRate::ONE_HUNDRED_PERCENT, $combinedRate);
        $netTaxable = $this->multiplyAndRound($taxableMinor, TaxRate::ONE_HUNDRED_PERCENT, $denominator);
        $taxTotal = $taxableMinor - $netTaxable;
        $amounts = $this->allocate($taxTotal, $taxes, $combinedRate);
        $lines = [];

        foreach ($taxes as $index => $tax) {
            $lines[] = new TaxLine($tax->name, (int) $tax->rate_millionths, $amounts[$index], (int) $tax->position);
        }

        return new TaxQuote(
            $this->safeAdd($netTaxable, $nonTaxableMinor),
            $taxTotal,
            $this->safeAdd($taxableMinor, $nonTaxableMinor),
            TaxPriceMode::Inclusive,
            (string) $organization->tax_identifier,
            $lines,
        );
    }

    /**
     * Allocate an inclusive tax total proportionally while ensuring the tax
     * lines add to the exact amount extracted from the advertised price.
     *
     * @param  list<OrganizationTax>  $taxes
     * @return list<int>
     */
    private function allocate(int $taxTotal, array $taxes, int $combinedRate): array
    {
        $amounts = [];
        $remainders = [];
        $allocated = 0;

        foreach ($taxes as $index => $tax) {
            [$amount, $remainder] = $this->multiplyAndFloorWithRemainder(
                $taxTotal,
                (int) $tax->rate_millionths,
                $combinedRate,
            );
            $amounts[$index] = $amount;
            $remainders[$index] = $remainder;
            $allocated = $this->safeAdd($allocated, $amount);
        }

        $remaining = $taxTotal - $allocated;
        $indexes = array_keys($taxes);
        usort($indexes, fn (int $left, int $right): int => ($remainders[$right] <=> $remainders[$left]) ?: ($left <=> $right));
        for ($i = 0; $i < $remaining; $i++) {
            $amounts[$indexes[$i % count($indexes)]]++;
        }

        ksort($amounts);

        return array_values($amounts);
    }

    private function multiplyAndRound(int $value, int $multiplier, int $divisor): int
    {
        [$result, $remainder] = $this->multiplyAndFloorWithRemainder($value, $multiplier, $divisor);

        return $remainder >= intdiv($divisor + 1, 2) ? $this->safeAdd($result, 1) : $result;
    }

    /** @return array{int,int} */
    private function multiplyAndFloorWithRemainder(int $value, int $multiplier, int $divisor): array
    {
        if ($value < 0 || $multiplier < 0 || $divisor <= 0) {
            throw new InvalidArgumentException('The tax calculation is invalid.');
        }

        $quotient = intdiv($value, $divisor);
        if ($multiplier > 0 && $quotient > intdiv(PHP_INT_MAX, $multiplier)) {
            throw new InvalidArgumentException('The calculated tax is too large.');
        }

        $remainderValue = $value % $divisor;
        if ($multiplier > 0 && $remainderValue > intdiv(PHP_INT_MAX, $multiplier)) {
            throw new InvalidArgumentException('The calculated tax is too large.');
        }

        $partial = $remainderValue * $multiplier;

        return [
            $this->safeAdd($quotient * $multiplier, intdiv($partial, $divisor)),
            $partial % $divisor,
        ];
    }

    private function safeAdd(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new InvalidArgumentException('The calculated tax is too large.');
        }

        return $left + $right;
    }
}
