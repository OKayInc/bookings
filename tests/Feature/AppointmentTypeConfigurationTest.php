<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTypeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_group_variable_rate_appointment_type(): void
    {
        [$user, $organization] = $this->ownerContext();
        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'name' => 'Luis',
            'type' => 'person',
            'timezone' => 'America/Toronto',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), [
                'name' => 'Event Photography',
                'visibility' => 'public',
                'attendance_mode' => 'group',
                'capacity' => 50,
                'duration_mode' => 'variable',
                'duration_unit' => 'minute',
                'minimum_duration_value' => 30,
                'maximum_duration_value' => 180,
                'duration_increment_value' => 15,
                'booking_notice_value' => 1,
                'booking_notice_unit' => 'month',
                'maximum_booking_notice_value' => 6,
                'maximum_booking_notice_unit' => 'month',
                'buffer_before_minutes' => 10,
                'buffer_after_minutes' => 30,
                'pricing_mode' => 'rate',
                'rate_amount' => '150.00',
                'rate_unit' => 'hour',
                'resource_uuids' => [$resource->uuid],
                'requires_resource_confirmation' => '1',
                'email_verification_mode' => 'before_payment',
                'cancellation_allowed' => '1',
                'cancellation_notice_value' => 48,
                'cancellation_notice_unit' => 'hour',
                'cancellation_policy_text' => 'Cancel at least 48 hours ahead.',
                'rescheduling_allowed' => '1',
                'rescheduling_notice_value' => 2,
                'rescheduling_notice_unit' => 'day',
                'rescheduling_max_count' => 3,
                'rescheduling_policy_text' => 'Up to three changes.',
                'reminder_enabled' => '1',
                'reminder_threshold_basis' => 'lead_time',
                'reminder_threshold_days' => 7,
                'reminder_before_value' => 1,
                'reminder_before_unit' => 'day',
                'reminder_clients' => '1',
                'reminder_resources' => '1',
                'redirect_url' => 'https://example.test/thanks',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('appointment-types.index'));
        $type = AppointmentType::where('name', 'Event Photography')->firstOrFail();

        $this->assertSame('group', $type->attendance_mode->value);
        $this->assertSame(50, $type->capacity);
        $this->assertSame('variable', $type->duration_mode->value);
        $this->assertSame(30, $type->minimum_duration_value);
        $this->assertSame(180, $type->maximum_duration_value);
        $this->assertSame(15, $type->duration_increment_value);
        $this->assertSame(1, $type->booking_notice_value);
        $this->assertSame('month', $type->booking_notice_unit->value);
        $this->assertSame(6, $type->maximum_booking_notice_value);
        $this->assertSame('month', $type->maximum_booking_notice_unit->value);
        $this->assertSame(15000, $type->rate_amount_minor);
        $this->assertSame('hour', $type->rate_unit->value);
        $this->assertTrue($type->requires_resource_confirmation);
        $this->assertSame('before_payment', $type->email_verification_mode->value);
        $this->assertTrue($type->cancellation_allowed);
        $this->assertSame(48, $type->cancellation_notice_value);
        $this->assertSame('hour', $type->cancellation_notice_unit->value);
        $this->assertTrue($type->rescheduling_allowed);
        $this->assertSame(3, $type->rescheduling_max_count);
        $this->assertTrue($type->reminder_enabled);
        $this->assertSame('lead_time', $type->reminder_threshold_basis->value);
        $this->assertSame(1, $type->resources()->count());
    }

    public function test_variable_duration_increment_must_land_on_maximum(): void
    {
        [$user, $organization] = $this->ownerContext();

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), [
                'name' => 'Invalid Duration',
                'visibility' => 'public',
                'attendance_mode' => 'single',
                'duration_mode' => 'variable',
                'duration_unit' => 'minute',
                'minimum_duration_value' => 30,
                'maximum_duration_value' => 100,
                'duration_increment_value' => 15,
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'pricing_mode' => 'free',
                'is_active' => '1',
            ]);

        $response->assertSessionHasErrors('duration_increment_value');
        $this->assertSame(0, AppointmentType::count());
    }

    public function test_confirmation_requires_at_least_one_resource(): void
    {
        [$user, $organization] = $this->ownerContext();

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), [
                'name' => 'Needs Approval',
                'visibility' => 'public',
                'attendance_mode' => 'single',
                'duration_mode' => 'fixed',
                'duration_unit' => 'hour',
                'duration_value' => 1,
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'pricing_mode' => 'free',
                'requires_resource_confirmation' => '1',
                'is_active' => '1',
            ]);

        $response->assertSessionHasErrors('resource_uuids');
    }


    public function test_edit_form_disables_inactive_conditional_fields_in_browser(): void
    {
        [$user, $organization] = $this->ownerContext();

        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Legacy Free Session',
            'slug' => 'legacy-free-session',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('appointment-types.edit', $type));

        $response->assertOk();
        $response->assertSee('id="short-notice-fee-list"', false);
        $response->assertSee("control.disabled = !enabled", false);
        $response->assertSee("setRequired(document.getElementById('capacity'), groupAttendance)", false);
    }

    public function test_owner_can_update_legacy_single_free_type_to_rate_pricing(): void
    {
        [$user, $organization] = $this->ownerContext();

        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Legacy Free Session',
            'slug' => 'legacy-free-session',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('appointment-types.update', $type), [
                'name' => 'Legacy Free Session',
                'slug' => 'legacy-free-session',
                'visibility' => 'public',
                'attendance_mode' => 'single',
                'duration_mode' => 'fixed',
                'duration_unit' => 'minute',
                'duration_value' => 60,
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'pricing_mode' => 'rate',
                'rate_amount' => '150.00',
                'rate_unit' => 'hour',
                'is_active' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('appointment-types.index'));

        $type->refresh();
        $this->assertSame('rate', $type->pricing_mode->value);
        $this->assertSame(15000, $type->rate_amount_minor);
        $this->assertSame('hour', $type->rate_unit->value);
        $this->assertSame(1, $type->capacity);
    }

    private function ownerContext(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD']);

        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }
}
