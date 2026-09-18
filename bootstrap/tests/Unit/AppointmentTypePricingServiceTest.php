<?php

namespace Tests\Unit;

use App\Domain\Appointments\AppointmentTypePricingService;
use App\Models\AppointmentType;
use Tests\TestCase;

class AppointmentTypePricingServiceTest extends TestCase
{
    public function test_hourly_rate_prices_minutes_without_float_math(): void
    {
        $type = new AppointmentType([
            'pricing_mode' => 'rate',
            'rate_amount_minor' => 10000,
            'rate_unit' => 'hour',
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 30,
        ]);

        $this->assertSame(5000, app(AppointmentTypePricingService::class)->priceForDuration($type));
    }

    public function test_daily_rate_can_price_week_duration(): void
    {
        $type = new AppointmentType([
            'pricing_mode' => 'rate',
            'rate_amount_minor' => 10000,
            'rate_unit' => 'day',
            'duration_mode' => 'fixed',
            'duration_unit' => 'week',
            'duration_value' => 1,
        ]);

        $this->assertSame(70000, app(AppointmentTypePricingService::class)->priceForDuration($type));
    }
}
