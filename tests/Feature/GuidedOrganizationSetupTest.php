<?php

namespace Tests\Feature;

use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidedOrganizationSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guided_setup_creates_a_normal_starter_configuration(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'Simple Studio',
            'timezone' => 'America/Toronto',
            'currency' => 'CAD',
            'guided_setup' => '1',
            'guided_business_type' => 'photography',
            'guided_appointment_name' => 'Family Photo Session',
            'guided_duration_minutes' => 60,
            'guided_location_mode' => 'in_person',
            'guided_pricing_mode' => 'fixed',
            'guided_fixed_price' => '125.00',
            'guided_attendance_mode' => 'single',
            'guided_use_owner_resource' => '1',
            'guided_booking_notice_hours' => 24,
            'guided_weekdays' => [1, 2, 3, 4, 5],
            'guided_start_time' => '09:00',
            'guided_end_time' => '17:00',
        ]);

        $response->assertSessionHasNoErrors();

        $organization = Organization::where('name', 'Simple Studio')->firstOrFail();
        $type = AppointmentType::where('organization_id', $organization->getKey())->firstOrFail();

        $response->assertRedirect(route('appointment-types.edit', $type));

        $this->assertSame('Family Photo Session', $type->name);
        $this->assertSame('single', $type->attendance_mode->value);
        $this->assertSame(1, $type->capacity);
        $this->assertSame(60, $type->duration_value);
        $this->assertSame('fixed', $type->pricing_mode->value);
        $this->assertSame(12500, $type->fixed_price_minor);
        $this->assertSame(24, $type->booking_notice_value);
        $this->assertFalse($type->is_online);

        $resource = Resource::where('organization_id', $organization->getKey())->firstOrFail();
        $this->assertSame($user->person_id, $resource->person_id);
        $this->assertTrue($type->resources()->whereKey($resource->getKey())->exists());

        $schedule = AvailabilitySchedule::where('organization_id', $organization->getKey())
            ->where('scope_type', AvailabilityScope::Organization->value)
            ->firstOrFail();

        $this->assertSame('America/Toronto', $schedule->timezone);
        $this->assertSame(5, $schedule->rules()->count());
        $this->assertSame([1, 2, 3, 4, 5], $schedule->rules()->orderBy('weekday')->pluck('weekday')->all());
    }

    public function test_user_can_skip_the_starter_appointment(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'Blank Organization',
            'timezone' => 'America/Toronto',
            'currency' => 'CAD',
            'guided_setup' => '0',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard'));

        $organization = Organization::where('name', 'Blank Organization')->firstOrFail();
        $this->assertSame(0, $organization->appointmentTypes()->count());
        $this->assertSame(0, $organization->availabilitySchedules()->count());
    }

    public function test_edit_appointment_page_exposes_collapsible_editor_controls(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);

        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $type = AppointmentType::factory()->create([
            'organization_id' => $organization->getKey(),
            'name' => 'Consultation',
            'slug' => 'consultation',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('appointment-types.edit', $type));

        $response->assertOk()
            ->assertSee('data-appointment-editor-toolbar', false)
            ->assertSee('data-appointment-sections="expand"', false)
            ->assertSee('data-appointment-sections="collapse"', false)
            ->assertSee('js/appointment-type-editor.js', false);
    }
}
