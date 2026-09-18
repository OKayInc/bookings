<?php

namespace Tests\Unit;

use App\Domain\Questionnaires\DrivingDistancePricingService;
use App\Models\AppointmentQuestion;
use Tests\TestCase;

class DrivingDistancePricingServiceTest extends TestCase
{
    public function test_fixed_distance_fee_carries_route_measurement_without_the_private_origin(): void
    {
        $question = new AppointmentQuestion([
            'configuration' => [
                'distance_pricing' => [
                    'enabled' => true,
                    'origin_address' => 'Private point 0',
                    'unit' => 'kilometer',
                    'mode' => 'fixed',
                    'fixed_amount_minor' => 2500,
                ],
            ],
        ]);

        $charge = app(DrivingDistancePricingService::class)->charge($question, 12345);

        $this->assertNotNull($charge);
        $this->assertSame(2500, $charge->amountMinor);
        $this->assertSame('12.35 km', $charge->distanceLabel);
        $this->assertSame(12345, $charge->metadata['distance_meters']);
        $this->assertArrayNotHasKey('origin_address', $charge->metadata);
        $this->assertStringNotContainsString('Private point 0', json_encode($charge->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_range_boundaries_are_preserved_and_gaps_use_rounded_up_fallback_increments(): void
    {
        $question = new AppointmentQuestion([
            'configuration' => [
                'distance_pricing' => [
                    'enabled' => true,
                    'origin_address' => 'Private point 0',
                    'unit' => 'kilometer',
                    'mode' => 'range',
                    'ranges' => [
                        ['minimum' => 0, 'maximum' => 10, 'amount_minor' => 0],
                        ['minimum' => 15, 'maximum' => 25, 'amount_minor' => 3000],
                        ['minimum' => 25, 'maximum' => null, 'amount_minor' => 5000],
                    ],
                    'fallback' => ['increment' => 5, 'amount_minor' => 700],
                ],
            ],
        ]);

        $pricing = app(DrivingDistancePricingService::class);
        $this->assertSame(0, $pricing->charge($question, 9999)?->amountMinor);
        $this->assertSame(1400, $pricing->charge($question, 10000)?->amountMinor);
        $fallback = $pricing->charge($question, 14999);
        $this->assertSame(2100, $fallback?->amountMinor);
        $this->assertSame('distance_fallback', $fallback?->lineType);
        $this->assertSame(3, $fallback?->metadata['fallback_blocks']);
        $this->assertSame(5.0, $fallback?->metadata['fallback_increment']);
        $this->assertSame(3000, $pricing->charge($question, 15000)?->amountMinor);
        $this->assertSame(5000, $pricing->charge($question, 25000)?->amountMinor);
    }

    public function test_uncovered_legacy_range_without_a_fallback_fails_closed(): void
    {
        $question = new AppointmentQuestion([
            'label' => 'Service address',
            'configuration' => [
                'distance_pricing' => [
                    'enabled' => true,
                    'unit' => 'kilometer',
                    'mode' => 'range',
                    'ranges' => [
                        ['minimum' => 0, 'maximum' => 10, 'amount_minor' => 1000],
                    ],
                ],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no valid per-distance fallback is configured');

        app(DrivingDistancePricingService::class)->charge($question, 10000);
    }

    public function test_mile_ranges_are_compared_against_google_distance_meters(): void
    {
        $question = new AppointmentQuestion([
            'configuration' => [
                'distance_pricing' => [
                    'enabled' => true,
                    'origin_address' => 'Private point 0',
                    'unit' => 'mile',
                    'mode' => 'range',
                    'ranges' => [
                        ['minimum' => 0, 'maximum' => 10, 'amount_minor' => 1500],
                        ['minimum' => 10, 'maximum' => null, 'amount_minor' => 3500],
                    ],
                    'fallback' => ['increment' => 5, 'amount_minor' => 900],
                ],
            ],
        ]);

        $charge = app(DrivingDistancePricingService::class)->charge($question, 16094);

        $this->assertNotNull($charge);
        $this->assertSame(3500, $charge->amountMinor);
        $this->assertSame('10 mi', $charge->distanceLabel);
        $this->assertSame('mile', $charge->metadata['distance_unit']);
    }

    public function test_mile_fallback_uses_the_configured_unit_and_every_started_increment(): void
    {
        $question = new AppointmentQuestion([
            'configuration' => [
                'distance_pricing' => [
                    'enabled' => true,
                    'unit' => 'mile',
                    'mode' => 'range',
                    'ranges' => [
                        ['minimum' => 0, 'maximum' => 5, 'amount_minor' => 1000],
                    ],
                    'fallback' => ['increment' => 5, 'amount_minor' => 800],
                ],
            ],
        ]);

        $charge = app(DrivingDistancePricingService::class)->charge($question, 19312); // 12 miles.

        $this->assertSame(2400, $charge?->amountMinor);
        $this->assertSame('12 mi', $charge?->distanceLabel);
        $this->assertSame('range_fallback', $charge?->metadata['pricing_mode']);
        $this->assertSame(3, $charge?->metadata['fallback_blocks']);
    }
}
