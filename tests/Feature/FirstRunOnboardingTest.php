<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FirstRunOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_organization_that_needs_onboarding(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'first_name' => 'Taylor',
            'last_name' => 'Owner',
            'email' => 'taylor@example.test',
            'password' => 'ExamplePass12345',
            'password_confirmation' => 'ExamplePass12345',
            'timezone' => 'America/Toronto',
            'organization_name' => 'Taylor Studio',
            'organization_timezone' => 'America/Toronto',
            'currency' => 'CAD',
        ]);

        $response->assertRedirect(route('verification.notice'));

        $organization = Organization::query()->where('slug', 'taylor-studio')->firstOrFail();

        $this->assertNull($organization->onboarding_completed_at);
        $this->assertSame(0, $organization->appointmentTypes()->count());
    }

    public function test_incomplete_organization_is_redirected_to_onboarding(): void
    {
        [$user, $organization] = $this->ownerWithOrganization();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_onboarding_creates_a_working_starter_configuration(): void
    {
        [$user, $organization] = $this->ownerWithOrganization();

        $response = $this->actingAs($user)->post(route('onboarding.store'), [
            'guided_business_type' => 'photography',
            'guided_appointment_name' => 'Photo Session',
            'guided_duration_minutes' => 60,
            'guided_location_mode' => 'in_person',
            'guided_pricing_mode' => 'fixed',
            'guided_fixed_price' => '150.00',
            'guided_attendance_mode' => 'single',
            'guided_use_owner_resource' => '1',
            'guided_booking_notice_hours' => 24,
            'guided_weekdays' => [1, 2, 3, 4, 5],
            'guided_start_time' => '09:00',
            'guided_end_time' => '17:00',
        ]);

        $appointmentType = $organization->appointmentTypes()->firstOrFail();

        $response->assertRedirect(route('appointment-types.edit', $appointmentType));

        $organization->refresh();
        $this->assertNotNull($organization->onboarding_completed_at);
        $this->assertSame('Photo Session', $appointmentType->name);
        $this->assertSame(15000, $appointmentType->fixed_price_minor);
        $this->assertSame(1, $organization->resources()->count());

        $schedule = $organization->availabilitySchedules()->firstOrFail();
        $this->assertSame(5, $schedule->rules()->count());
    }

    public function test_user_can_skip_guided_setup(): void
    {
        [$user, $organization] = $this->ownerWithOrganization();

        $this->actingAs($user)
            ->post(route('onboarding.skip'))
            ->assertRedirect(route('dashboard'));

        $organization->refresh();
        $this->assertNotNull($organization->onboarding_completed_at);
        $this->assertSame(0, $organization->appointmentTypes()->count());
    }

    private function ownerWithOrganization(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['onboarding_completed_at' => null]);

        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $user->forceFill(['active_organization_id' => $organization->getKey()])->save();

        return [$user->fresh(), $organization];
    }
}
