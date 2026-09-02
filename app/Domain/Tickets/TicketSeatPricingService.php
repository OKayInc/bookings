<?php

namespace App\Domain\Tickets;

use InvalidArgumentException;

class TicketSeatPricingService
{
    public function __construct(private readonly TicketSeatingService $seating)
    {
    }

    /** @param array<int, mixed> $seats */
    public function total(array $seats): int
    {
        $total = 0;
        foreach ($seats as $seat) {
            $fee = is_array($seat) ? filter_var($seat['seat_fee_minor'] ?? 0, FILTER_VALIDATE_INT) : false;
            if ($fee === false || $fee < 0 || $fee > PHP_INT_MAX - $total) {
                throw new InvalidArgumentException('The calculated ticket seating fee is invalid or too large.');
            }
            $total += $fee;
        }

        return $total;
    }

    /**
     * @param array<int, mixed> $seats
     * @return list<array{label:string,quantity:int,unit_amount_minor:int,amount_minor:int}>
     */
    public function breakdown(array $seats): array
    {
        $groups = [];
        foreach ($seats as $seat) {
            if (! is_array($seat)) {
                throw new InvalidArgumentException('The ticket seating fee contains invalid data.');
            }
            $fee = filter_var($seat['seat_fee_minor'] ?? 0, FILTER_VALIDATE_INT);
            if ($fee === false || $fee < 0) {
                throw new InvalidArgumentException('The ticket seating fee contains an invalid amount.');
            }
            if ($fee === 0) {
                continue;
            }
            $label = $this->seating->display($seat);
            $key = $label.'|'.$fee;
            $groups[$key] ??= ['label' => $label, 'quantity' => 0, 'unit_amount_minor' => $fee, 'amount_minor' => 0];
            if ($fee > PHP_INT_MAX - $groups[$key]['amount_minor']) {
                throw new InvalidArgumentException('The calculated ticket seating fee is too large.');
            }
            $groups[$key]['quantity']++;
            $groups[$key]['amount_minor'] += $fee;
        }

        return array_values($groups);
    }
}
