<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\BookingHoldService;
use App\Domain\Appointments\AppointmentTypeSummaryService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Domain\Questionnaires\QuestionnairePricingService;
use App\Domain\Resources\EquipmentInventoryService;
use App\Domain\Resources\EquipmentPricingService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class M9R1EquipmentSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_overlapping_appointments_share_piece_inventory_without_overselling(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 00:00:00 UTC'));
        $organization = Organization::factory()->create(['timezone' => 'UTC']);
        $chairs = Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'equipment',
            'quantity_enabled' => true,
            'inventory_quantity' => 20,
            'name' => 'Chairs',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $oneChair = $this->type($organization, 'Appointment one', 'appointment-one');
        $fiveChairs = $this->type($organization, 'Appointment two', 'appointment-two');
        $fifteenChairs = $this->type($organization, 'Appointment three', 'appointment-three');
        $this->assign($oneChair, $chairs, 1);
        $this->assign($fiveChairs, $chairs, 5);
        $this->assign($fifteenChairs, $chairs, 15);
        $this->schedule($organization);

        $start = CarbonImmutable::parse('2026-09-07 09:00:00 UTC');
        $holds = app(BookingHoldService::class);
        $first = $holds->acquire($oneChair->fresh(['organization', 'resources']), $start, 60, 'UTC', 10);

        $snapshot = app(EquipmentInventoryService::class)->snapshotsForTypeAt(
            $fiveChairs->fresh(['organization', 'resources']),
            $start,
            $start->addHour(),
        )[0];
        $this->assertSame(20, $snapshot['total_quantity']);
        $this->assertSame(19, $snapshot['available_quantity']);
        $this->assertSame(5, $snapshot['quantity_required']);
        $this->assertSame(1, (int) $first->hold->resources->firstOrFail()->pivot->quantity_reserved);

        $slotResponse = $this->getJson(route('public.booking.slots', $fiveChairs).'?'.http_build_query([
            'access_mode' => 'direct',
            'timezone' => 'UTC',
            'date' => '2026-09-07',
            'duration_value' => 60,
            'attendee_count' => 1,
        ]))->assertOk();
        $slotPayload = collect($slotResponse->json('slots'))->firstWhere('starts_at_utc', $start->toIso8601String())['equipment_availability'][0];
        $this->assertSame(19, $slotPayload['available_quantity']);
        $this->assertSame(20, $slotPayload['total_quantity']);

        $slots = app(AvailabilityService::class)->slots(
            $fiveChairs->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-09-07 00:00:00 UTC'),
            CarbonImmutable::parse('2026-09-08 00:00:00 UTC'),
            60,
            'UTC',
        );
        $this->assertTrue(collect($slots)->contains(fn ($slot): bool => $slot->startsAtUtc->equalTo($start)));

        $second = $holds->acquire($fiveChairs->fresh(['organization', 'resources']), $start, 60, 'UTC', 10);
        $this->assertSame(5, (int) $second->hold->resources->firstOrFail()->pivot->quantity_reserved);
        $this->assertSame(14, app(EquipmentInventoryService::class)->availableQuantityAt($chairs, $start, $start->addHour()));

        $this->expectException(RuntimeException::class);
        $holds->acquire($fifteenChairs->fresh(['organization', 'resources']), $start, 60, 'UTC', 10);
    }

    public function test_equipment_stock_quantity_and_bundle_pricing_can_be_configured_in_the_ui_flow(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'UTC', 'currency' => 'USD']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('resources.store'), [
                'name' => 'Rental chairs',
                'type' => 'equipment',
                'quantity_enabled' => '1',
                'inventory_quantity' => 20,
                'timezone' => 'UTC',
                'default_requirement' => 'required',
                'is_active' => '1',
            ])
            ->assertRedirect(route('resources.index'));

        $chairs = Resource::where('name', 'Rental chairs')->firstOrFail();
        $this->assertSame(20, $chairs->inventory_quantity);
        $this->assertTrue($chairs->quantity_enabled);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), [
                'name' => 'Chair rental appointment',
                'visibility' => 'public',
                'attendance_mode' => 'single',
                'duration_mode' => 'fixed',
                'duration_unit' => 'minute',
                'duration_value' => 60,
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'pricing_mode' => 'free',
                'resource_uuids' => [$chairs->uuid],
                'resource_requirement_modes' => [$chairs->uuid => 'required'],
                'resource_quantities' => [$chairs->uuid => 5],
                'resource_equipment_pricing_modes' => [$chairs->uuid => 'bundles'],
                'resource_equipment_bundles' => [$chairs->uuid => [
                    ['quantity' => 1, 'amount' => '3.00'],
                    ['quantity' => 5, 'amount' => '10.00'],
                    ['quantity' => 20, 'amount' => '20.00'],
                ]],
                'is_active' => '1',
            ])
            ->assertRedirect(route('appointment-types.index'));

        $configuredType = AppointmentType::where('name', 'Chair rental appointment')->firstOrFail();
        $assigned = $configuredType->resources()->firstOrFail();
        $this->assertSame(5, (int) $assigned->pivot->quantity_required);
        $this->assertSame('bundles', $assigned->pivot->equipment_pricing_mode);
        $this->assertSame(1000, json_decode($assigned->pivot->equipment_bundle_prices, true, flags: JSON_THROW_ON_ERROR)[1]['amount_minor']);
        $this->assertSame('full', $configuredType->payment_collection_mode->value);
    }

    public function test_bundle_unit_fixed_and_free_equipment_prices_are_snapshotted(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 00:00:00 UTC'));
        $organization = Organization::factory()->create(['timezone' => 'UTC', 'currency' => 'USD']);
        $type = $this->type($organization, 'Equipment rental', 'equipment-rental');
        $type->update(['email_verification_mode' => 'none', 'payment_collection_mode' => 'full']);

        $bundled = $this->equipment($organization, 'Chairs', 20);
        $perPiece = $this->equipment($organization, 'Microphones', 10);
        $fixed = $this->equipment($organization, 'Projectors', 5);
        $free = $this->equipment($organization, 'Cables', 10);
        $this->assign($type, $bundled, 6, 'bundles', bundles: [
            ['quantity' => 1, 'amount_minor' => 300],
            ['quantity' => 5, 'amount_minor' => 1000],
            ['quantity' => 20, 'amount_minor' => 2000],
        ]);
        $this->assign($type, $perPiece, 2, 'per_unit', unitPrice: 300);
        $this->assign($type, $fixed, 3, 'fixed', fixedPrice: 2000);
        $this->assign($type, $free, 4, 'free');
        $this->schedule($organization);

        $quote = app(QuestionnairePricingService::class)->quote(
            $type->fresh(['organization', 'resources']),
            60,
            [],
        );
        $this->assertSame(3900, $quote->totalMinor);
        $this->assertStringContainsString(
            'USD 39.00 equipment',
            app(AppointmentTypeSummaryService::class)->pricing($type->fresh(['organization', 'resources'])),
        );
        $this->assertCount(3, collect($quote->lines)->where('sourceType', 'equipment_resource'));
        $bundleLine = collect($quote->lines)->first(fn ($line): bool => $line->sourceUuid === $bundled->uuid);
        $this->assertSame(1300, $bundleLine->amountMinor);
        $this->assertSame([
            ['quantity' => 1, 'amount_minor' => 300, 'count' => 1],
            ['quantity' => 5, 'amount_minor' => 1000, 'count' => 1],
        ], $bundleLine->metadata['bundle_breakdown']);
        $allChairs = app(EquipmentPricingService::class)->charges(
            $type->fresh(['organization', 'resources']),
            [$bundled->getKey() => 20],
        );
        $this->assertSame(2000, $allChairs[0]->amountMinor);

        $start = CarbonImmutable::parse('2026-09-07 10:00:00 UTC');
        $lease = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            $start,
            60,
            'UTC',
            1,
        );
        $booking = app(BookingCreationService::class)->createFromHold($lease->token, [
            'first_name' => 'Equipment',
            'last_name' => 'Client',
            'email' => 'equipment@example.test',
        ])->booking->fresh('priceLines');

        $this->assertSame(3900, (int) $booking->price_minor);
        $this->assertSame(3900, (int) $booking->priceLines->where('source_type', 'equipment_resource')->sum('amount_minor'));
        $this->assertSame(6, (int) $booking->appointment->resources()->whereKey($bundled->getKey())->firstOrFail()->pivot->quantity_reserved);
    }

    public function test_joining_a_group_session_does_not_allocate_its_equipment_again(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 00:00:00 UTC'));
        $organization = Organization::factory()->create(['timezone' => 'UTC']);
        $type = $this->type($organization, 'Workshop', 'workshop');
        $type->update(['attendance_mode' => 'group', 'capacity' => 10, 'email_verification_mode' => 'none']);
        $chairs = $this->equipment($organization, 'Chairs', 20);
        $this->assign($type, $chairs, 5);
        $this->schedule($organization);
        $start = CarbonImmutable::parse('2026-09-07 09:00:00 UTC');

        $first = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            $start,
            60,
            'UTC',
            2,
        );
        app(BookingCreationService::class)->createFromHold($first->token, [
            'first_name' => 'First', 'last_name' => 'Client', 'email' => 'first@example.test',
        ]);
        $this->assertSame(15, app(EquipmentInventoryService::class)->availableQuantityAt($chairs, $start, $start->addHour()));

        $joining = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            $start,
            60,
            'UTC',
            3,
        );
        $this->assertNotNull($joining->hold->appointment_id);
        $this->assertSame(15, app(EquipmentInventoryService::class)->availableQuantityAt($chairs, $start, $start->addHour()));
    }

    public function test_replacement_payload_bypasses_piece_quantity_validation_even_for_tracked_equipment(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'UTC', 'currency' => 'CAD']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);
        $first = $this->equipment($organization, 'Equipment alternative A', 20);
        $second = $this->equipment($organization, 'Equipment alternative B', 20);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), [
                'name' => 'Compatibility replacement session',
                'visibility' => 'public',
                'attendance_mode' => 'single',
                'duration_mode' => 'fixed',
                'duration_unit' => 'minute',
                'duration_value' => 60,
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'pricing_mode' => 'free',
                'resource_uuids' => [$first->uuid, $second->uuid],
                'resource_requirement_modes' => [
                    $first->uuid => 'replacement',
                    $second->uuid => 'replacement',
                ],
                'resource_replacement_groups' => [
                    $first->uuid => 'Equipment alternative',
                    $second->uuid => ' equipment alternative ',
                ],
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appointment-types.index'));

        $type = AppointmentType::where('name', 'Compatibility replacement session')->firstOrFail();
        foreach ($type->resources as $resource) {
            $this->assertSame('replacement', $resource->pivot->requirement_mode);
            $this->assertSame(1, (int) $resource->pivot->quantity_required);
        }
    }

    private function type(Organization $organization, string $name, string $slug): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => $name,
            'slug' => $slug,
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'start_interval_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => true,
        ]);
    }

    private function equipment(Organization $organization, string $name, int $stock): Resource
    {
        return Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'equipment',
            'quantity_enabled' => true,
            'inventory_quantity' => $stock,
            'name' => $name,
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    }

    private function assign(
        AppointmentType $type,
        Resource $resource,
        int $quantity,
        string $pricingMode = 'free',
        ?int $unitPrice = null,
        ?int $fixedPrice = null,
        ?array $bundles = null,
    ): void {
        $type->resources()->attach($resource->getKey(), [
            'is_required' => true,
            'requirement_mode' => 'required',
            'quantity_required' => $quantity,
            'equipment_pricing_mode' => $pricingMode,
            'equipment_unit_price_minor' => $unitPrice,
            'equipment_fixed_price_minor' => $fixedPrice,
            'equipment_bundle_prices' => $bundles === null ? null : json_encode($bundles, JSON_THROW_ON_ERROR),
        ]);
    }

    private function schedule(Organization $organization): void
    {
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'UTC',
            true,
            [['weekday' => 1, 'start_time' => '08:00', 'end_time' => '17:00']],
        );
    }
}
