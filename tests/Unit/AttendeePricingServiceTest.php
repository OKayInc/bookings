<?php

namespace Tests\Unit;

use App\Domain\Appointments\AppointmentTypePricingService;
use App\Domain\Appointments\AttendeePricingService;
use App\Models\AppointmentType;
use InvalidArgumentException;
use Tests\TestCase;

class AttendeePricingServiceTest extends TestCase
{
    public function test_flat_attendee_price_does_not_depend_on_duration(): void
    {
        $type = $this->type(['attendee_pricing_mode' => 'flat', 'attendee_price_minor' => 2500]);
        $pricing = app(AppointmentTypePricingService::class);

        $this->assertSame(7500, $pricing->priceForBooking($type, 30, 'minute', 3));
        $this->assertSame(7500, $pricing->priceForBooking($type, 120, 'minute', 3));
        $this->assertSame(2500, $pricing->priceForDuration($type));
    }

    public function test_absolute_ranges_apply_the_matching_rate_to_every_attendee(): void
    {
        $type = $this->type(['attendee_pricing_mode' => 'absolute']);
        foreach ([1 => 200, 10 => 2000, 11 => 1650, 12 => 1800, 20 => 3000] as $count => $expected) {
            $this->assertSame($expected, app(AppointmentTypePricingService::class)->priceForBooking($type, attendeeCount: $count));
        }
        $lines = app(AttendeePricingService::class)->breakdown($type, 12);
        $this->assertCount(1, $lines);
        $this->assertSame(12, $lines[0]['quantity']);
        $this->assertSame(150, $lines[0]['unit_amount_minor']);
    }

    public function test_accumulative_ranges_add_the_portions_instead_of_discounting_previous_attendees(): void
    {
        $type = $this->type(['attendee_pricing_mode' => 'accumulative']);
        foreach ([1 => 200, 10 => 2000, 11 => 2150, 12 => 2300, 20 => 3500] as $count => $expected) {
            $this->assertSame($expected, app(AppointmentTypePricingService::class)->priceForBooking($type, attendeeCount: $count));
        }
        $lines = app(AttendeePricingService::class)->breakdown($type, 12);
        $this->assertSame([10, 2], array_column($lines, 'quantity'));
        $this->assertSame([2000, 300], array_column($lines, 'amount_minor'));
    }

    public function test_invalid_ranges_and_counts_fail_closed(): void
    {
        $invalid = [
            ['attendee_price_ranges' => []],
            ['attendee_price_ranges' => [$this->range(2, 20, 200)]],
            ['attendee_price_ranges' => [$this->range(1, 10, 200), $this->range(12, 20, 150)]],
            ['attendee_price_ranges' => [$this->range(1, 10, 200), $this->range(10, 20, 150)]],
            ['attendee_price_ranges' => [$this->range(1, 10, 200)]],
            ['attendee_price_ranges' => [$this->range(1, 20, 0)]],
            ['attendee_price_ranges' => [$this->range(10, 1, 200)]],
            ['attendance_mode' => 'single'],
            ['capacity' => 21],
            ['attendee_pricing_mode' => 'flat', 'attendee_price_minor' => 0],
            ['attendee_pricing_mode' => 'flat', 'attendee_price_minor' => null],
        ];
        foreach ($invalid as $overrides) {
            $this->assertInvalid($this->type($overrides), 12);
        }
        $this->assertInvalid($this->type(), 0);
        $this->assertInvalid($this->type(), 21);
    }

    public function test_multiplication_and_accumulative_overflow_are_rejected(): void
    {
        $this->assertInvalid($this->type(['attendee_pricing_mode' => 'flat', 'attendee_price_minor' => PHP_INT_MAX]), 2);
        $this->assertInvalid($this->type([
            'capacity' => 2,
            'attendee_pricing_mode' => 'accumulative',
            'attendee_price_ranges' => [$this->range(1, 1, PHP_INT_MAX), $this->range(2, 2, 1)],
        ]), 2);
    }

    public function test_existing_fixed_and_duration_prices_are_not_multiplied_by_attendees(): void
    {
        $pricing = app(AppointmentTypePricingService::class);
        $this->assertSame(5000, $pricing->priceForBooking($this->type(['pricing_mode' => 'fixed', 'fixed_price_minor' => 5000]), attendeeCount: 12));
        $this->assertSame(2500, $pricing->priceForBooking($this->type(['pricing_mode' => 'rate', 'rate_amount_minor' => 5000, 'rate_unit' => 'hour']), 30, 'minute', 12));
        $this->assertSame(0, $pricing->priceForBooking($this->type(['pricing_mode' => 'free']), attendeeCount: 12));
    }

    private function assertInvalid(AppointmentType $type, int $count): void
    {
        try {
            app(AppointmentTypePricingService::class)->priceForBooking($type, attendeeCount: $count);
            $this->fail('Invalid attendee pricing was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
    }

    private function range(int $min, int $max, int $amount): array
    {
        return ['min_attendees' => $min, 'max_attendees' => $max, 'unit_amount_minor' => $amount];
    }

    private function type(array $overrides = []): AppointmentType
    {
        return new AppointmentType(array_merge([
            'attendance_mode' => 'group', 'capacity' => 20,
            'duration_mode' => 'fixed', 'duration_value' => 60, 'duration_unit' => 'minute',
            'pricing_mode' => 'per_attendee', 'attendee_pricing_mode' => 'absolute',
            'attendee_price_ranges' => [$this->range(1, 10, 200), $this->range(11, 20, 150)],
        ], $overrides));
    }
}
