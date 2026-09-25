<?php

namespace Tests\Feature;

use App\Domain\Organizations\GuidedOrganizationSetup;
use App\Domain\Organizations\OrganizationStartupChecklist;
use App\Enums\MembershipRole;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationStartupChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_organization_has_ordered_steps_and_working_links(): void
    {
        [, $organization] = $this->context();
        $response = $this->get(route('dashboard'))->assertOk()
            ->assertSee('Startup checklist')->assertSee('Next step:')
            ->assertSee('✓')->assertSee('✗')->assertSee('Not needed now')
            ->assertSeeInOrder(['Check your business details', 'Add people, rooms or equipment', 'data-startup-step="availability"', 'Create and enable an appointment type'], false);
        $checklist = $response->viewData('startupChecklist');
        $this->assertFalse($checklist['ready']);
        $this->assertSame('availability', $checklist['next']['id']);
        foreach (array_merge($checklist['steps'], $checklist['extras']) as $step) {
            $this->assertNotNull($step['url']);
            $this->get($step['url'])->assertOk();
        }
        $this->assertFalse($this->step($organization, 'payments')['required']);
    }

    public function test_guided_setup_is_recognized_without_saved_completion_flags(): void
    {
        [$user, $organization] = $this->context();
        app(GuidedOrganizationSetup::class)->create($organization, $user->person, [
            'guided_appointment_name' => 'Consultation', 'guided_duration_minutes' => 30,
            'guided_use_owner_resource' => true, 'guided_weekdays' => [1, 2, 3, 4, 5],
            'guided_start_time' => '09:00', 'guided_end_time' => '17:00',
        ]);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('startupChecklist', fn ($list) => $list['ready']);
        $organization->appointmentTypes()->first()->update(['is_active' => false]);
        $this->assertFalse($this->checklist($organization)['ready']);
        $organization->appointmentTypes()->first()->delete();
        $this->assertFalse($this->step($organization, 'appointment-types')['complete']);
    }

    public function test_free_unlisted_appointments_can_pass_without_resources_or_payment_accounts(): void
    {
        [, $organization] = $this->context();
        $this->type($organization, ['visibility' => 'unlisted']);
        $this->hours($organization);
        $this->assertTrue($this->checklist($organization)['ready']);
        $this->assertFalse($this->step($organization, 'resources')['required']);
        $this->assertTrue($this->step($organization, 'assignments')['notNeeded']);
        $this->assertTrue($this->step($organization, 'payments')['notNeeded']);
    }

    public function test_custom_hours_override_defaults_and_future_openings_count(): void
    {
        [, $organization] = $this->context();
        $type = $this->type($organization);
        $this->hours($organization);
        $custom = $this->hours($organization, 'appointment_type', $type->getKey());
        $custom->update(['is_active' => false]);
        $this->assertFalse($this->step($organization, 'availability')['complete']);
        $custom->update(['is_active' => true]);
        $custom->rules()->delete();
        $this->assertFalse($this->step($organization, 'availability')['complete']);
        $opening = $custom->exceptions()->create([
            'mode' => 'available', 'starts_at_utc' => now()->addDay(),
            'ends_at_utc' => now()->addDay()->addHour(), 'timezone' => 'UTC',
        ]);
        $this->assertTrue($this->step($organization, 'availability')['complete']);
        $opening->update(['starts_at_utc' => now()->subDays(2), 'ends_at_utc' => now()->subDay()]);
        $this->assertFalse($this->step($organization, 'availability')['complete']);
        $custom->delete();
        $this->assertTrue($this->step($organization, 'availability')['complete']);
    }

    public function test_shared_required_resources_use_current_organization_hours_and_inventory(): void
    {
        [, $organization] = $this->context();
        $owner = Organization::factory()->create();
        $type = $this->type($organization);
        $this->hours($organization);
        $resource = Resource::create(['organization_id' => $owner->getKey(), 'type' => 'equipment', 'name' => 'Shared projector', 'quantity_enabled' => true, 'inventory_quantity' => 2]);
        $organization->resources()->attach($resource, ['is_required_by_default' => true]);
        $type->resources()->attach($resource, ['requirement_mode' => 'inherit', 'quantity_required' => 2]);
        $this->assertTrue($this->step($organization, 'assignments')['complete']);
        $resource->update(['inventory_quantity' => 1]);
        $this->assertFalse($this->step($organization, 'assignments')['complete']);
        $resource->update(['inventory_quantity' => 2, 'is_active' => false]);
        $this->assertFalse($this->step($organization, 'assignments')['complete']);
        $resource->update(['is_active' => true]);
        $schedule = $this->hours($organization, 'resource', $resource->getKey());
        $schedule->update(['is_active' => false]);
        $this->assertFalse($this->step($organization, 'assignments')['complete']);
        $schedule->delete();
        $organization->resources()->detach($resource);
        $this->assertFalse($this->step($organization, 'assignments')['complete']);
    }

    public function test_replacement_groups_need_one_usable_member_and_optional_resources_do_not_block(): void
    {
        [, $organization] = $this->context();
        $type = $this->type($organization);
        $this->hours($organization);
        foreach ([false, true] as $active) {
            $resource = Resource::create(['organization_id' => $organization->getKey(), 'type' => 'room', 'name' => 'Room', 'is_active' => $active]);
            $type->resources()->attach($resource, ['requirement_mode' => 'replacement', 'replacement_group' => 'Room']);
        }
        $this->assertTrue($this->step($organization, 'assignments')['complete']);
        $resource->update(['is_active' => false]);
        $this->assertFalse($this->step($organization, 'assignments')['complete']);
        foreach ($type->resources as $resource) {
            $type->resources()->updateExistingPivot($resource, ['requirement_mode' => 'optional']);
        }
        $this->assertTrue($this->step($organization, 'assignments')['complete']);
    }

    public function test_payments_require_one_live_provider_with_webhook_credentials(): void
    {
        [, $organization] = $this->context();
        $this->type($organization, ['pricing_mode' => 'fixed', 'fixed_price_minor' => 1000]);
        $this->assertTrue($this->step($organization, 'payments')['required']);
        $settings = $organization->paymentSettings()->create(['stripe_enabled' => true, 'stripe_test_mode' => true, 'stripe_secret_key' => 'sk_test_private', 'stripe_webhook_secret' => 'whsec_private']);
        $this->assertFalse($this->step($organization, 'payments')['complete']);
        $this->get(route('dashboard'))->assertOk()->assertSee('Test mode is enabled')->assertDontSee('sk_test_private')->assertDontSee('whsec_private');
        $settings->update(['stripe_test_mode' => false]);
        $this->assertFalse($this->step($organization, 'payments')['complete']);
        $settings->update(['stripe_test_mode' => false, 'stripe_secret_key' => 'sk_live_private']);
        $this->assertTrue($this->step($organization, 'payments')['complete']);
        $settings->update(['stripe_webhook_secret' => null]);
        $this->assertFalse($this->step($organization, 'payments')['complete']);
        $settings->update(['paypal_enabled' => true, 'paypal_sandbox' => false, 'paypal_client_id' => 'client', 'paypal_client_secret' => 'secret', 'paypal_webhook_id' => 'webhook']);
        $this->assertTrue($this->step($organization, 'payments')['complete']);
        $settings->update(['paypal_enabled' => false]);
        $this->assertFalse($this->step($organization, 'payments')['complete']);
    }

    public function test_free_base_price_with_paid_questions_or_deposits_still_needs_payments(): void
    {
        [, $organization] = $this->context();
        $type = $this->type($organization);
        $question = $type->questions()->create(['type' => 'text', 'label' => 'Extra', 'pricing_adjustment_type' => 'fixed', 'pricing_amount_minor' => 1000]);
        $this->assertTrue($this->step($organization, 'payments')['required']);
        $question->update(['is_active' => false]);
        $this->assertFalse($this->step($organization, 'payments')['required']);
        $type->update(['deposit_override_minor' => 500]);
        $this->assertTrue($this->step($organization, 'payments')['required']);
        $type->update(['is_active' => false]);
        $this->assertFalse($this->step($organization, 'payments')['required']);
    }

    public function test_ticket_seat_fees_and_short_notice_fees_need_payment_setup(): void
    {
        [, $organization] = $this->context();
        $type = $this->type($organization, ['ticketing_enabled' => true, 'ticket_seat_blocks' => [['seat_fee_minor' => 500]]]);
        $this->assertTrue($this->step($organization, 'payments')['required']);
        $type->update(['ticketing_enabled' => false, 'ticket_seat_blocks' => []]);
        $type->shortNoticeFeeRules()->create(['threshold_value' => 24, 'threshold_unit' => 'hour', 'adjustment_type' => 'fixed', 'fixed_amount_minor' => 100]);
        $this->assertTrue($this->step($organization, 'payments')['required']);
    }

    public function test_taxes_events_and_meeting_configuration_are_conditional(): void
    {
        [, $organization] = $this->context();
        $type = $this->type($organization, ['ticketing_enabled' => true, 'is_online' => false]);
        $this->assertFalse($this->step($organization, 'event-dates')['complete']);
        $this->assertFalse($this->step($organization, 'event-locations')['complete']);
        $type->eventOccurrences()->create(['starts_at_utc' => now()->addDay(), 'timezone' => 'UTC', 'is_active' => true]);
        $type->update(['event_location' => 'Private venue', 'location_disclosure_mode' => 'hours_before_event', 'location_disclosure_hours' => 2]);
        $this->assertTrue($this->step($organization, 'event-dates')['complete']);
        $this->assertTrue($this->step($organization, 'event-locations')['complete']);
        $type->update(['is_online' => true, 'meeting_provider' => 'zoom']);
        $this->assertFalse($this->step($organization, 'online-meetings')['complete']);
        $type->update(['meeting_provider' => 'jitsi']);
        $this->assertTrue($this->step($organization, 'online-meetings')['complete']);
        $organization->update(['collects_taxes' => true]);
        $this->assertFalse($this->step($organization, 'taxes')['complete']);
        $organization->taxes()->create(['name' => 'HST', 'rate_millionths' => 130000, 'position' => 0]);
        $this->assertTrue($this->step($organization, 'taxes')['complete']);
    }

    public function test_organization_switching_does_not_reuse_another_checklist(): void
    {
        [$user, $first] = $this->context();
        $this->type($first, ['name' => 'First organization service']);
        $this->hours($first);
        $second = Organization::factory()->create();
        OrganizationMembership::create(['organization_id' => $second->getKey(), 'person_id' => $user->person_id, 'role' => 'owner', 'status' => 'active']);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('startupChecklist', fn ($list) => $list['ready']);
        $this->post(route('organizations.switch', $second))->assertRedirect();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('First organization service')->assertViewHas('startupChecklist', fn ($list) => ! $list['ready']);
    }

    public function test_manager_has_only_authorized_actions_and_employees_do_not_see_setup(): void
    {
        [$user, $organization] = $this->context(MembershipRole::Manager);
        $this->type($organization, ['pricing_mode' => 'fixed', 'fixed_price_minor' => 100]);
        $response = $this->get(route('dashboard'))->assertOk()->assertSee('Owner or administrator');
        $steps = collect($response->viewData('startupChecklist')['steps'])->keyBy('id');
        $this->assertNull($steps['payments']['url']);
        $this->assertNull($steps['organization']['url']);
        $this->assertNotNull($steps['availability']['url']);
        $organization->memberships()->where('person_id', $user->person_id)->first()->update(['role' => 'employee']);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Startup checklist');
    }

    private function context(MembershipRole $role = MembershipRole::Owner): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::create(['organization_id' => $organization->getKey(), 'person_id' => $user->person_id, 'role' => $role, 'status' => 'active']);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);

        return [$user, $organization];
    }

    private function type(Organization $organization, array $attributes = []): AppointmentType
    {
        return $organization->appointmentTypes()->create(array_merge(['name' => 'Consultation', 'slug' => 'consultation', 'is_active' => true, 'pricing_mode' => 'free'], $attributes));
    }

    private function hours(Organization $organization, string $scope = 'organization', ?string $scopeId = null): AvailabilitySchedule
    {
        $schedule = $organization->availabilitySchedules()->create(['scope_type' => $scope, 'scope_id' => $scopeId ?? $organization->getKey(), 'timezone' => $organization->timezone, 'is_active' => true]);
        $schedule->rules()->create(['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'sort_order' => 0]);

        return $schedule;
    }

    private function checklist(Organization $organization): array
    {
        return app(OrganizationStartupChecklist::class)->forOrganization($organization->fresh(), true);
    }

    private function step(Organization $organization, string $id): array
    {
        return collect($this->checklist($organization)['steps'])->firstWhere('id', $id);
    }
}
