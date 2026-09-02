<?php

namespace App\Domain\Tickets;

use App\Domain\Money\MoneyService;
use App\Enums\TicketSeatingScheme;
use App\Models\Appointment;
use App\Models\AppointmentType;
use InvalidArgumentException;

class TicketSeatingService
{
    public function __construct(private readonly MoneyService $money)
    {
    }

    /**
     * @param array<int, mixed> $blocks
     * @return list<array{section:?string,row:?string,first_seat:?int,last_seat:?int,quantity:int,seat_fee_minor:int}>
     */
    public function normalizeInput(
        TicketSeatingScheme $scheme,
        bool $seatOptional,
        array $blocks,
        int $capacity,
        string $currency,
        bool $allowSeatFees,
    ): array {
        $prepared = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                $prepared[] = $block;
                continue;
            }

            $feeInput = $block['seat_fee'] ?? null;
            $hasFee = $feeInput !== null && trim((string) $feeInput) !== '';
            if ($hasFee && ! $allowSeatFees) {
                throw new InvalidArgumentException('Seating fees require a paid ticketed event using per-attendee pricing.');
            }

            $block['seat_fee_minor'] = $hasFee ? $this->money->parse((string) $feeInput, $currency) : 0;
            $prepared[] = $block;
        }

        return $this->normalize($scheme, $seatOptional, $prepared, $capacity);
    }

    /**
     * @param array<int, mixed> $blocks
     * @return list<array{section:?string,row:?string,first_seat:?int,last_seat:?int,quantity:int,seat_fee_minor:int}>
     */
    public function normalize(
        TicketSeatingScheme $scheme,
        bool $seatOptional,
        array $blocks,
        int $capacity,
    ): array {
        if ($capacity < 1) {
            throw new InvalidArgumentException('Ticket capacity must be at least one.');
        }

        if (! $scheme->usesBlocks()) {
            return [];
        }

        if ($blocks === []) {
            throw new InvalidArgumentException('Add at least one seating block for the selected ticket numbering scheme.');
        }

        $seatOptional = $seatOptional && $scheme->supportsOptionalSeat();
        $normalized = [];
        $inventoryKeys = [];
        $total = 0;

        foreach (array_values($blocks) as $index => $block) {
            if (! is_array($block)) {
                throw new InvalidArgumentException('Every ticket seating block must be a valid row.');
            }

            $number = $index + 1;
            $section = $this->label($block['section'] ?? null);
            $row = $this->label($block['row'] ?? null);
            $seatFeeMinor = filter_var($block['seat_fee_minor'] ?? 0, FILTER_VALIDATE_INT);
            if ($seatFeeMinor === false || $seatFeeMinor < 0) {
                throw new InvalidArgumentException("Seating block {$number} has an invalid per-ticket seating fee.");
            }

            if (in_array($scheme, [TicketSeatingScheme::SectionSeat, TicketSeatingScheme::SectionRowSeat], true) && $section === null) {
                throw new InvalidArgumentException("Seating block {$number} requires a section label.");
            }
            if (in_array($scheme, [TicketSeatingScheme::RowSeat, TicketSeatingScheme::SectionRowSeat], true) && $row === null) {
                throw new InvalidArgumentException("Seating block {$number} requires a row label.");
            }

            if ($scheme === TicketSeatingScheme::SectionSeat) {
                $row = null;
            } elseif ($scheme === TicketSeatingScheme::RowSeat) {
                $section = null;
            }

            $first = $this->positiveIntegerOrNull($block['first_seat'] ?? null);
            $last = $this->positiveIntegerOrNull($block['last_seat'] ?? null);
            if (($first === null) !== ($last === null)) {
                throw new InvalidArgumentException("Seating block {$number} must include both the first and last seat number.");
            }

            if ($first !== null) {
                if ($last < $first) {
                    throw new InvalidArgumentException("Seating block {$number} must end on or after its first seat number.");
                }
                $quantity = $last - $first + 1;
                for ($seat = $first; $seat <= $last; $seat++) {
                    $key = $this->inventoryKey($section, $row, (string) $seat);
                    if (isset($inventoryKeys[$key])) {
                        throw new InvalidArgumentException("Seating block {$number} overlaps another configured seat.");
                    }
                    $inventoryKeys[$key] = true;
                }
            } else {
                if (! $seatOptional) {
                    throw new InvalidArgumentException("Seating block {$number} requires consecutive seat numbers.");
                }
                $quantity = $this->positiveIntegerOrNull($block['quantity'] ?? null);
                if ($quantity === null) {
                    throw new InvalidArgumentException("Seating block {$number} requires a quantity when seat numbers are omitted.");
                }
                $key = $this->inventoryKey($section, $row, null);
                if (isset($inventoryKeys[$key])) {
                    throw new InvalidArgumentException("Seating block {$number} duplicates an unnumbered section or row.");
                }
                $inventoryKeys[$key] = true;
            }

            $total += $quantity;
            $normalized[] = [
                'section' => $section,
                'row' => $row,
                'first_seat' => $first,
                'last_seat' => $last,
                'quantity' => $quantity,
                'seat_fee_minor' => $seatFeeMinor,
            ];
        }

        if ($total !== $capacity) {
            throw new InvalidArgumentException("The seating blocks define {$total} tickets, but session capacity is {$capacity}.");
        }

        return $normalized;
    }

    /**
     * @return list<array{seat_key:?string,section_label:?string,row_label:?string,seat_label:?string,seat_fee_minor:int}>
     */
    public function inventory(Appointment $appointment): array
    {
        $scheme = $appointment->ticket_seating_scheme ?? TicketSeatingScheme::None;
        if (! $scheme instanceof TicketSeatingScheme) {
            $scheme = TicketSeatingScheme::tryFrom((string) $scheme) ?? TicketSeatingScheme::None;
        }

        if ($scheme === TicketSeatingScheme::None) {
            return array_fill(0, (int) $appointment->capacity, [
                'seat_key' => null,
                'section_label' => null,
                'row_label' => null,
                'seat_label' => null,
                'seat_fee_minor' => 0,
            ]);
        }

        if ($scheme === TicketSeatingScheme::Consecutive) {
            $inventory = [];
            for ($seat = 1; $seat <= (int) $appointment->capacity; $seat++) {
                $inventory[] = [
                    'seat_key' => 'seat:'.$seat,
                    'section_label' => null,
                    'row_label' => null,
                    'seat_label' => (string) $seat,
                    'seat_fee_minor' => 0,
                ];
            }

            return $inventory;
        }

        $inventory = [];
        foreach ($appointment->ticket_seat_blocks ?? [] as $blockIndex => $block) {
            $section = $block['section'] ?? null;
            $row = $block['row'] ?? null;
            if ($block['first_seat'] !== null) {
                for ($seat = (int) $block['first_seat']; $seat <= (int) $block['last_seat']; $seat++) {
                    $inventory[] = [
                        'seat_key' => $this->inventoryKey($section, $row, (string) $seat),
                        'section_label' => $section,
                        'row_label' => $row,
                        'seat_label' => (string) $seat,
                        'seat_fee_minor' => (int) ($block['seat_fee_minor'] ?? 0),
                    ];
                }
                continue;
            }

            for ($position = 1; $position <= (int) $block['quantity']; $position++) {
                $inventory[] = [
                    'seat_key' => $this->inventoryKey($section, $row, null).':'.($blockIndex + 1).':'.$position,
                    'section_label' => $section,
                    'row_label' => $row,
                    'seat_label' => null,
                    'seat_fee_minor' => (int) ($block['seat_fee_minor'] ?? 0),
                ];
            }
        }

        return $inventory;
    }

    /** @return list<array{seat_key:?string,section_label:?string,row_label:?string,seat_label:?string,seat_fee_minor:int}> */
    public function inventoryForType(AppointmentType $type): array
    {
        $appointment = new Appointment([
            'capacity' => $type->capacity,
            'ticket_seating_scheme' => $type->ticket_seating_scheme?->value ?? TicketSeatingScheme::None->value,
            'ticket_seat_blocks' => $type->ticket_seat_blocks,
        ]);

        return $this->inventory($appointment);
    }

    /** @param array<string, mixed> $seat */
    public function display(array $seat): string
    {
        $parts = [];
        if (filled($seat['section_label'] ?? null)) {
            $parts[] = 'Section '.$seat['section_label'];
        }
        if (filled($seat['row_label'] ?? null)) {
            $parts[] = 'Row '.$seat['row_label'];
        }
        if (filled($seat['seat_label'] ?? null)) {
            $parts[] = 'Seat '.$seat['seat_label'];
        }

        return $parts === [] ? 'General admission' : implode(' · ', $parts);
    }

    private function label(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $label = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        return $label === '' ? null : mb_substr($label, 0, 80);
    }

    private function positiveIntegerOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer !== false && $integer > 0 ? $integer : null;
    }

    private function inventoryKey(?string $section, ?string $row, ?string $seat): string
    {
        return implode('|', array_map(
            fn (?string $part): string => mb_strtolower($part ?? '-', 'UTF-8'),
            [$section, $row, $seat],
        ));
    }
}
