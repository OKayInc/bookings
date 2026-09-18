<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\BookingHoldService;
use App\Domain\Bookings\BookingWorkflowService;
use App\Domain\Bookings\ResourceConfirmationService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\ResourceConfirmationStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\ResourceConfirmation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReplacementResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_replacement_group_requires_two_selected_resources_and_is_persisted(): void
    {
        [$owner, $organization] = $this->ownerContext();
        $first = $this->resource($organization, 'First photographer');
        $second = $this->resource($organization, 'Second photographer');
        $this->assertFalse($first->usesQuantityInventory());
        $this->assertFalse($second->usesQuantityInventory());

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->from(route('appointment-types.create'))
            ->post(route('appointment-types.store'), $this->validTypePayload() + [
                'resource_uuids' => [$first->uuid],
                'resource_requirement_modes' => [$first->uuid => 'replacement'],
                'resource_replacement_groups' => [$first->uuid => 'Photographer'],
            ])
            ->assertRedirect(route('appointment-types.create'))
            ->assertSessionHasErrors('resource_uuids');

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $this->validTypePayload() + [
                'resource_uuids' => [$first->uuid, $second->uuid],
                'resource_requirement_modes' => [
                    $first->uuid => 'replacement',
                    $second->uuid => 'replacement',
                ],
                'resource_replacement_groups' => [
                    $first->uuid => 'Photographer',
                    $second->uuid => ' photographer ',
                ],
            ])
            ->assertRedirect(route('appointment-types.index'));

        $type = AppointmentType::where('name', 'Replacement Session')->firstOrFail();
        $this->assertCount(2, $type->resources);
        foreach ($type->resources as $resource) {
            $this->assertSame('replacement', $resource->pivot->requirement_mode);
            $this->assertTrue((bool) $resource->pivot->is_required);
            $this->assertSame('Photographer', $resource->pivot->replacement_group);
        }
    }

    public function test_slot_and_hold_use_an_available_member_instead_of_requiring_every_replacement(): void
    {
        [$type, $first, $second] = $this->scheduledReplacementType();
        $startsAt = CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc();
        $endsAt = $startsAt->addHour();

        $appointment = Appointment::create([
            'organization_id' => $type->organization_id,
            'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $startsAt,
            'ends_at_utc' => $endsAt,
            'blocked_starts_at_utc' => $startsAt,
            'blocked_ends_at_utc' => $endsAt,
            'scheduling_timezone' => 'America/Toronto',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => 'scheduled',
        ]);
        $appointment->resources()->attach($first->getKey(), [
            'is_required' => true,
            'replacement_group' => 'Photographer',
        ]);

        $slots = app(AvailabilityService::class)->slots(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-08-31 00:00', 'America/Toronto')->utc(),
            CarbonImmutable::parse('2026-09-01 00:00', 'America/Toronto')->utc(),
            null,
            'America/Toronto',
        );
        $this->assertSame(['09:00', '10:00', '11:00'], array_map(
            fn ($slot): string => $slot->startsAtUtc->setTimezone('America/Toronto')->format('H:i'),
            $slots,
        ));

        $lease = app(BookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            $startsAt,
            null,
            'America/Toronto',
            10,
        );
        $this->assertCount(1, $lease->hold->resources);
        $held = $lease->hold->resources->firstOrFail();
        $this->assertTrue(hash_equals($second->getKey(), $held->getKey()));
        $this->assertTrue((bool) $held->pivot->is_required);
        $this->assertSame('Photographer', $held->pivot->replacement_group);
    }

    public function test_hold_snapshots_every_available_replacement_candidate(): void
    {
        [$type, $first, $second] = $this->scheduledReplacementType();

        $lease = app(BookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-08-31 10:00', 'America/Toronto')->utc(),
            null,
            'America/Toronto',
            10,
        );

        $this->assertEqualsCanonicalizing([$first->uuid, $second->uuid], $lease->hold->resources->pluck('uuid')->all());
        foreach ($lease->hold->resources as $resource) {
            $this->assertTrue((bool) $resource->pivot->is_required);
            $this->assertSame('Photographer', $resource->pivot->replacement_group);
        }
    }

    public function test_one_decline_keeps_booking_pending_and_other_acceptance_confirms_it(): void
    {
        Notification::fake();
        [$booking, $first, $second] = $this->bookingWithReplacementEmployees();
        $workflow = app(BookingWorkflowService::class);
        $confirmations = app(ResourceConfirmationService::class);

        $workflow->refreshStatus($booking->load(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));
        $firstConfirmation = ResourceConfirmation::where('resource_id', $first->getKey())->firstOrFail();
        $secondConfirmation = ResourceConfirmation::where('resource_id', $second->getKey())->firstOrFail();

        $confirmations->respond($firstConfirmation, ResourceConfirmationStatus::Declined, 'Unavailable.');
        $workflow->refreshStatus($booking->fresh(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));
        $this->assertSame('pending_staff_confirmation', $booking->fresh()->status->value);

        $confirmations->respond($secondConfirmation, ResourceConfirmationStatus::Accepted, 'Available.');
        $workflow->refreshStatus($booking->fresh(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));

        $this->assertSame('confirmed', $booking->fresh()->status->value);
        $assigned = $booking->appointment->fresh('resources')->resources;
        $this->assertCount(1, $assigned);
        $this->assertTrue(hash_equals($second->getKey(), $assigned->firstOrFail()->getKey()));
    }

    public function test_first_acceptance_supersedes_and_releases_other_candidates(): void
    {
        Notification::fake();
        [$booking, $first, $second] = $this->bookingWithReplacementEmployees();
        $workflow = app(BookingWorkflowService::class);
        $workflow->refreshStatus($booking->load(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));

        $firstConfirmation = ResourceConfirmation::where('resource_id', $first->getKey())->firstOrFail();
        app(ResourceConfirmationService::class)->respond($firstConfirmation, ResourceConfirmationStatus::Accepted, null);
        $workflow->refreshStatus($booking->fresh(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));

        $this->assertSame('confirmed', $booking->fresh()->status->value);
        $this->assertSame(
            'superseded',
            ResourceConfirmation::where('resource_id', $second->getKey())->firstOrFail()->status->value,
        );
        $this->assertTrue($booking->appointment->fresh('resources')->resources->contains(
            fn (Resource $resource): bool => hash_equals($resource->getKey(), $first->getKey()),
        ));
        $this->assertFalse($booking->appointment->fresh('resources')->resources->contains(
            fn (Resource $resource): bool => hash_equals($resource->getKey(), $second->getKey()),
        ));
    }

    public function test_all_replacement_candidates_declining_declines_the_booking(): void
    {
        Notification::fake();
        [$booking] = $this->bookingWithReplacementEmployees();
        $workflow = app(BookingWorkflowService::class);
        $confirmations = app(ResourceConfirmationService::class);
        $workflow->refreshStatus($booking->load(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));

        foreach (ResourceConfirmation::all() as $confirmation) {
            $confirmations->respond($confirmation, ResourceConfirmationStatus::Declined, null);
            $workflow->refreshStatus($booking->fresh(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));
        }

        $this->assertSame('declined', $booking->fresh()->status->value);
    }

    private function scheduledReplacementType(): array
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = $this->type($organization);
        $first = $this->resource($organization, 'First photographer');
        $second = $this->resource($organization, 'Second photographer');
        $this->attachReplacementGroup($type, [$first, $second]);
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']],
        );

        return [$type->fresh(['organization', 'resources']), $first, $second];
    }

    private function bookingWithReplacementEmployees(): array
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-28 12:00 UTC'));
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = $this->type($organization, true);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        foreach ([$firstUser, $secondUser] as $user) {
            OrganizationMembership::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $user->person_id,
                'role' => MembershipRole::Employee,
                'status' => MembershipStatus::Active,
            ]);
        }
        $first = $this->resource($organization, 'First photographer', $firstUser);
        $second = $this->resource($organization, 'Second photographer', $secondUser);
        $this->attachReplacementGroup($type, [$first, $second]);

        $startsAt = CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc();
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $startsAt,
            'ends_at_utc' => $startsAt->addHour(),
            'blocked_starts_at_utc' => $startsAt,
            'blocked_ends_at_utc' => $startsAt->addHour(),
            'scheduling_timezone' => 'America/Toronto',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => 'scheduled',
        ]);
        foreach ([$first, $second] as $resource) {
            $appointment->resources()->attach($resource->getKey(), [
                'is_required' => true,
                'replacement_group' => 'Photographer',
            ]);
        }
        $contact = OrganizationContact::create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Client',
            'last_name' => 'One',
            'email' => 'client@example.test',
        ]);
        $booking = Booking::create([
            'organization_id' => $organization->getKey(),
            'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(),
            'organization_contact_id' => $contact->getKey(),
            'reference' => 'REPLACE00001',
            'status' => 'pending_staff_confirmation',
            'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto',
            'base_price_minor' => 0,
            'price_minor' => 0,
            'currency' => 'CAD',
            'first_name' => 'Client',
            'last_name' => 'One',
            'email' => 'client@example.test',
            'email_normalized' => 'client@example.test',
            'manage_token_hash' => random_bytes(32),
            'requires_resource_confirmation' => true,
        ]);

        return [$booking, $first, $second];
    }

    private function type(Organization $organization, bool $requiresConfirmation = false): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Replacement Session',
            'slug' => 'replacement-session',
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
            'email_verification_mode' => 'none',
            'requires_resource_confirmation' => $requiresConfirmation,
            'is_active' => true,
        ]);
    }

    private function resource(Organization $organization, string $name, ?User $user = null): Resource
    {
        return Resource::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user?->person_id,
            'type' => $user === null ? 'equipment' : 'person',
            'name' => $name,
            'timezone' => 'America/Toronto',
            'is_active' => true,
            'is_required_by_default' => true,
        ]);
    }

    /** @param list<Resource> $resources */
    private function attachReplacementGroup(AppointmentType $type, array $resources): void
    {
        foreach ($resources as $resource) {
            $type->resources()->attach($resource->getKey(), [
                'is_required' => true,
                'requirement_mode' => 'replacement',
                'replacement_group' => 'Photographer',
            ]);
        }
    }

    private function ownerContext(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $owner->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$owner, $organization];
    }

    private function validTypePayload(): array
    {
        return [
            'name' => 'Replacement Session',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => '1',
        ];
    }
}
