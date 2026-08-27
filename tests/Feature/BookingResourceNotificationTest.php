<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use App\Notifications\BookingResourceAssignedEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingResourceNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_linked_person_resource_receives_email_when_booking_is_created(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00 UTC'));
        [$organization, $type] = $this->bookableTypeWithMemberResources(1);

        $lease = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc(),
            60,
            'America/Toronto',
            1,
        );

        app(BookingCreationService::class)->createFromHold($lease->token, [
            'first_name' => 'Booking',
            'last_name' => 'Client',
            'email' => 'client@example.test',
        ]);

        Notification::assertSentOnDemand(BookingResourceAssignedEmail::class);
    }

    public function test_multiple_resources_linked_to_same_member_generate_one_email(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00 UTC'));
        [$organization, $type] = $this->bookableTypeWithMemberResources(2);

        $lease = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc(),
            60,
            'America/Toronto',
            1,
        );

        app(BookingCreationService::class)->createFromHold($lease->token, [
            'first_name' => 'Booking',
            'last_name' => 'Client',
            'email' => 'client@example.test',
        ]);

        Notification::assertSentOnDemandTimes(BookingResourceAssignedEmail::class, 1);
    }

    private function bookableTypeWithMemberResources(int $resourceCount): array
    {
        $organization = Organization::factory()->create([
            'timezone' => 'America/Toronto',
            'currency' => 'CAD',
        ]);
        $member = User::factory()->create(['email' => 'resource.member@example.test']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $member->person_id,
            'role' => MembershipRole::Employee,
            'status' => MembershipStatus::Active,
        ]);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Member Resource Session',
            'slug' => 'member-resource-session',
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
            'requires_resource_confirmation' => false,
            'is_active' => true,
        ]);

        for ($number = 1; $number <= $resourceCount; $number++) {
            $resource = Resource::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $member->person_id,
                'type' => 'person',
                'name' => 'Assigned role '.$number,
                'timezone' => 'America/Toronto',
                'is_active' => true,
                'is_required_by_default' => true,
            ]);
            $type->resources()->attach($resource->getKey(), [
                'is_required' => true,
                'requirement_mode' => 'inherit',
            ]);
        }

        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']],
        );

        return [$organization, $type];
    }
}
