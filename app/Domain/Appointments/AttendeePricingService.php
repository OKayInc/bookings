<?php

namespace App\Domain\Appointments;

use App\Enums\AttendanceMode;
use App\Enums\AttendeePricingMode;
use App\Models\AppointmentType;
use InvalidArgumentException;

class AttendeePricingService
{
    /** @return list<array{quantity: int, unit_amount_minor: int, amount_minor: int, min_attendees: ?int, max_attendees: ?int}> */
    public function breakdown(AppointmentType $type, int $attendeeCount): array
    {
        if ($type->attendance_mode !== AttendanceMode::Group) {
            throw new InvalidArgumentException('Per-attendee pricing is only available for group appointment types.');
        }
        if ($attendeeCount < 1 || $attendeeCount > (int) $type->capacity) {
            throw new InvalidArgumentException('The attendee count must be between 1 and the appointment capacity.');
        }

        $mode = $type->attendee_pricing_mode ?? AttendeePricingMode::Flat;
        if ($mode === AttendeePricingMode::Flat) {
            return [$this->line($attendeeCount, (int) $type->attendee_price_minor)];
        }

        $ranges = $this->validateRanges($type->attendee_price_ranges ?? [], (int) $type->capacity);
        $lines = [];
        foreach ($ranges as $range) {
            if ($attendeeCount < $range['min_attendees']) {
                break;
            }
            if ($mode === AttendeePricingMode::Absolute) {
                if ($attendeeCount <= $range['max_attendees']) {
                    $lines[] = $this->line($attendeeCount, $range['unit_amount_minor'], $range);
                    break;
                }
            } else {
                $quantity = min($attendeeCount, $range['max_attendees']) - $range['min_attendees'] + 1;
                $lines[] = $this->line($quantity, $range['unit_amount_minor'], $range);
            }
        }

        $this->total($lines); // Reject overflow before a breakdown can reach checkout.

        return $lines;
    }

    /** @return list<array{min_attendees: int, max_attendees: int, unit_amount_minor: int}> */
    public function validateRanges(array $ranges, int $capacity): array
    {
        if ($ranges === [] || count($ranges) > 50 || $capacity < 1) {
            throw new InvalidArgumentException('Provide 1 to 50 attendee price ranges covering the full session capacity.');
        }
        foreach ($ranges as $range) {
            if (! is_array($range)
                || ! is_int($range['min_attendees'] ?? null)
                || ! is_int($range['max_attendees'] ?? null)
                || ! is_int($range['unit_amount_minor'] ?? null)
                || $range['min_attendees'] < 1
                || $range['max_attendees'] < $range['min_attendees']
                || $range['max_attendees'] > (int) config('appointment-types.max_capacity', 100000)
                || $range['unit_amount_minor'] < 1) {
                throw new InvalidArgumentException('Each attendee range needs positive whole-number bounds and a positive unit price.');
            }
        }

        usort($ranges, fn (array $a, array $b): int => $a['min_attendees'] <=> $b['min_attendees']);
        $next = 1;
        foreach ($ranges as $range) {
            if ($range['min_attendees'] !== $next) {
                throw new InvalidArgumentException('Attendee price ranges must start at 1 and have no gaps or overlaps.');
            }
            $next = $range['max_attendees'] + 1;
        }
        if ($next <= $capacity) {
            throw new InvalidArgumentException('Attendee price ranges must cover every count up to the session capacity.');
        }

        return array_values($ranges);
    }

    public function total(array $lines): int
    {
        $total = 0;
        foreach ($lines as $line) {
            if ($line['amount_minor'] > PHP_INT_MAX - $total) {
                throw new InvalidArgumentException('The calculated appointment price is too large.');
            }
            $total += $line['amount_minor'];
        }

        return $total;
    }

    private function line(int $quantity, int $unitPrice, ?array $range = null): array
    {
        if ($unitPrice < 1) {
            throw new InvalidArgumentException('The appointment type does not contain a positive per-attendee price.');
        }
        if ($unitPrice > intdiv(PHP_INT_MAX, $quantity)) {
            throw new InvalidArgumentException('The calculated appointment price is too large.');
        }

        return [
            'quantity' => $quantity,
            'unit_amount_minor' => $unitPrice,
            'amount_minor' => $unitPrice * $quantity,
            'min_attendees' => $range['min_attendees'] ?? null,
            'max_attendees' => $range['max_attendees'] ?? null,
        ];
    }
}
