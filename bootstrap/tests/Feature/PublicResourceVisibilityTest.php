<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
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
use Tests\TestCase;

class PublicResourceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10 12:00:00 UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_owner_can_hide_and_show_resources_on_an_appointment_type(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'UTC', 'currency' => 'CAD']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);
        $type = $this->appointmentType($organization, true);

        $payload = [
            'name' => $type->name,
            'slug' => $type->slug,
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'show_resources_to_clients' => '0',
            'is_active' => '1',
        ];

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('appointment-types.update', $type), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appointment-types.index'));

        $this->assertFalse($type->fresh()->show_resources_to_clients);

        $payload['show_resources_to_clients'] = '1';
        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('appointment-types.update', $type), $payload)
            ->assertSessionHasNoErrors();

        $this->assertTrue($type->fresh()->show_resources_to_clients);
    }

    public function test_hidden_resources_are_omitted_from_slots_and_the_checkout_page(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'more-than-photos',
            'timezone' => 'UTC',
            'currency' => 'CAD',
        ]);
        $type = $this->appointmentType($organization, false);
        $camera = Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'equipment',
            'quantity_enabled' => true,
            'inventory_quantity' => 3,
            'name' => 'Passport camera',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $type->resources()->attach($camera->getKey(), [
            'is_required' => true,
            'requirement_mode' => 'required',
            'quantity_required' => 1,
            'equipment_pricing_mode' => 'free',
        ]);
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'UTC',
            true,
            [['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']],
        );

        $publicPage = $this->get(route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]))->assertOk();
        $publicPage->assertSee('const showResources = false;', false);
        $publicPage->assertDontSee('<small>${data.timezone}</small>', false);
        $publicPage->assertDontSee('shown underneath when different');

        $query = http_build_query([
            'access_mode' => 'direct',
            'timezone' => 'UTC',
            'date' => '2026-09-14',
            'duration_value' => 60,
            'attendee_count' => 1,
        ]);
        $slots = $this->getJson(route('public.booking.slots', $type).'?'.$query)->assertOk();
        $slots->assertJsonPath('slots.0.equipment_availability', []);
        $this->assertStringNotContainsString('Passport camera', $slots->getContent());

        $hold = $this->postJson(route('public.booking.holds.store', $type), [
            'access_mode' => 'direct',
            'timezone' => 'UTC',
            'starts_at_utc' => $slots->json('slots.0.starts_at_utc'),
            'duration_value' => 60,
            'attendee_count' => 1,
        ])->assertOk();

        $checkout = $this->get($hold->json('continue_url'))->assertOk();
        $checkout->assertDontSee('Equipment reserved');
        $checkout->assertDontSee('Passport camera');

        $type->update(['show_resources_to_clients' => true]);

        $visibleSlots = $this->getJson(route('public.booking.slots', $type).'?'.$query)->assertOk();
        $visibleSlots->assertJsonPath('slots.0.equipment_availability.0.name', 'Passport camera');
        $this->get($hold->json('continue_url'))
            ->assertOk()
            ->assertSee('Equipment reserved')
            ->assertSee('Passport camera');
    }

    private function appointmentType(Organization $organization, bool $showResources): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Passport photos',
            'slug' => 'passport-photos',
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
            'show_resources_to_clients' => $showResources,
            'is_active' => true,
        ]);
    }
}
