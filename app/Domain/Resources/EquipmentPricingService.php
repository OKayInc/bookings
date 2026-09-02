<?php

namespace App\Domain\Resources;

use App\Enums\EquipmentPricingMode;
use App\Models\AppointmentType;
use App\Models\Resource;
use InvalidArgumentException;

class EquipmentPricingService
{
    /**
     * @param null|array<int|string, string|int> $selectedResourceQuantities
     * @return list<EquipmentCharge>
     */
    public function charges(AppointmentType $type, ?array $selectedResourceQuantities = null): array
    {
        $type->loadMissing('resources');
        $selected = null;
        if ($selectedResourceQuantities !== null) {
            $selected = [];
            foreach ($selectedResourceQuantities as $key => $value) {
                if (is_int($key)) {
                    $selected[(string) $value] = null;
                } else {
                    $selected[$key] = max(1, (int) $value);
                }
            }
        }
        $charges = [];

        foreach ($type->resources as $resource) {
            if ($resource->type !== 'equipment') {
                continue;
            }
            if ($selected !== null && ! array_key_exists($resource->getKey(), $selected)) {
                continue;
            }

            $quantity = $selected !== null && $selected[$resource->getKey()] !== null
                ? $selected[$resource->getKey()]
                : max(1, (int) ($resource->pivot?->quantity_required ?? 1));
            $mode = EquipmentPricingMode::tryFrom((string) ($resource->pivot?->equipment_pricing_mode ?? 'free'))
                ?? EquipmentPricingMode::Free;
            [$amount, $metadata] = $this->price($resource, $quantity, $mode);

            if ($amount > 0) {
                $charges[] = new EquipmentCharge(
                    $resource->uuid,
                    $resource->name,
                    $quantity,
                    $mode,
                    $amount,
                    $metadata,
                );
            }
        }

        return $charges;
    }

    public function total(AppointmentType $type, ?array $selectedResourceQuantities = null): int
    {
        $total = 0;
        foreach ($this->charges($type, $selectedResourceQuantities) as $charge) {
            if ($charge->amountMinor > PHP_INT_MAX - $total) {
                throw new InvalidArgumentException('The equipment rental price is too large.');
            }
            $total += $charge->amountMinor;
        }

        return $total;
    }

    /** @return array{int, array<string, mixed>} */
    private function price(Resource $resource, int $quantity, EquipmentPricingMode $mode): array
    {
        return match ($mode) {
            EquipmentPricingMode::Free => [0, ['pricing_mode' => $mode->value]],
            EquipmentPricingMode::PerUnit => $this->perUnitPrice($resource, $quantity, $mode),
            EquipmentPricingMode::Fixed => [
                (int) ($resource->pivot?->equipment_fixed_price_minor ?? 0),
                ['pricing_mode' => $mode->value],
            ],
            EquipmentPricingMode::Bundles => $this->bundlePrice($resource, $quantity, $mode),
        };
    }

    /** @return array{int, array<string, mixed>} */
    private function perUnitPrice(Resource $resource, int $quantity, EquipmentPricingMode $mode): array
    {
        $unit = (int) ($resource->pivot?->equipment_unit_price_minor ?? 0);
        if ($unit > 0 && $quantity > intdiv(PHP_INT_MAX, $unit)) {
            throw new InvalidArgumentException('The equipment rental price is too large.');
        }

        return [$unit * $quantity, [
            'pricing_mode' => $mode->value,
            'unit_amount_minor' => $unit,
        ]];
    }

    /** @return array{int, array<string, mixed>} */
    private function bundlePrice(Resource $resource, int $quantity, EquipmentPricingMode $mode): array
    {
        $bundles = $this->bundles($resource->pivot?->equipment_bundle_prices ?? null);
        if ($bundles === [] || ! collect($bundles)->contains(fn (array $bundle): bool => $bundle['quantity'] === 1)) {
            throw new InvalidArgumentException('Bundle pricing requires a one-piece bundle.');
        }

        $infinity = PHP_INT_MAX;
        $cost = array_fill(0, $quantity + 1, $infinity);
        $previous = array_fill(0, $quantity + 1, null);
        $cost[0] = 0;

        for ($current = 1; $current <= $quantity; $current++) {
            foreach ($bundles as $bundle) {
                $bundleQuantity = $bundle['quantity'];
                if ($bundleQuantity > $current || $cost[$current - $bundleQuantity] === $infinity) {
                    continue;
                }
                if ($bundle['amount_minor'] > PHP_INT_MAX - $cost[$current - $bundleQuantity]) {
                    throw new InvalidArgumentException('The equipment rental price is too large.');
                }
                $candidate = $cost[$current - $bundleQuantity] + $bundle['amount_minor'];
                if ($candidate < $cost[$current]) {
                    $cost[$current] = $candidate;
                    $previous[$current] = $bundle;
                }
            }
        }

        if ($cost[$quantity] === $infinity) {
            throw new InvalidArgumentException('The requested equipment quantity cannot be priced with the configured bundles.');
        }

        $counts = [];
        for ($remaining = $quantity; $remaining > 0;) {
            $bundle = $previous[$remaining];
            if (! is_array($bundle)) {
                throw new InvalidArgumentException('The requested equipment quantity cannot be priced with the configured bundles.');
            }
            $key = (string) $bundle['quantity'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $remaining -= $bundle['quantity'];
        }

        $breakdown = [];
        foreach ($bundles as $bundle) {
            $count = $counts[(string) $bundle['quantity']] ?? 0;
            if ($count > 0) {
                $breakdown[] = $bundle + ['count' => $count];
            }
        }

        return [$cost[$quantity], [
            'pricing_mode' => $mode->value,
            'bundle_breakdown' => $breakdown,
        ]];
    }

    /** @return list<array{quantity:int, amount_minor:int}> */
    public function bundles(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw)) {
            return [];
        }

        $bundles = [];
        foreach ($raw as $bundle) {
            if (! is_array($bundle)) {
                continue;
            }
            $quantity = (int) ($bundle['quantity'] ?? 0);
            $amount = (int) ($bundle['amount_minor'] ?? -1);
            if ($quantity > 0 && $amount >= 0) {
                $bundles[$quantity] = ['quantity' => $quantity, 'amount_minor' => $amount];
            }
        }
        ksort($bundles, SORT_NUMERIC);

        return array_values($bundles);
    }
}
