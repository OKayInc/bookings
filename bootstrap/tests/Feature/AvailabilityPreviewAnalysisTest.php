<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvailabilityPreviewAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_preview_explains_required_resource_conflicts_and_optionally_shows_optional_resources(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10 12:00 UTC'));
        [$owner, $organization] = $this->ownerContext();
        $type = $this->appointmentType($organization, 'Portrait session');
        $required = $this->resource($organization, 'Studio camera', true);
        $optional = $this->resource($organization, 'Lighting assistant', false);
        $type->resources()->attach($required->getKey(), [
            'is_required' => true,
            'requirement_mode' => 'required',
        ]);
        $type->resources()->attach($optional->getKey(), [
            'is_required' => false,
            'requirement_mode' => 'optional',
        ]);
        $this->organizationHours($organization);

        $startsAt = CarbonImmutable::parse('2026-09-14 10:00', 'America/Toronto')->utc();
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
        $appointment->resources()->attach($required->getKey(), [
            'is_required' => true,
            'replacement_group' => null,
            'quantity_reserved' => 1,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('availability.preview').'?'.http_build_query([
                'appointment_type' => $type->uuid,
                'date' => '2026-09-14',
                'timezone' => 'America/Toronto',
                'include_optional' => 1,
            ]));

        $response->assertOk()
            ->assertSee('Availability analysis')
            ->assertSee('2 bookable starts')
            ->assertSee('data-analysis-row="organization-activity"', false)
            ->assertSee('data-analysis-row="required-'.$required->uuid.'"', false)
            ->assertSee('availability-analysis-row--required', false)
            ->assertSee('Scheduled appointment: Portrait session.')
            ->assertSee('data-analysis-row="optional-'.$optional->uuid.'"', false)
            ->assertSee('availability-analysis-row--optional', false)
            ->assertSee('Its availability does not block the base appointment.');
    }

    public function test_optional_resources_are_hidden_until_requested(): void
    {
        [$owner, $organization] = $this->ownerContext();
        $type = $this->appointmentType($organization, 'Consultation');
        $optional = $this->resource($organization, 'Optional projector', false);
        $type->resources()->attach($optional->getKey(), [
            'is_required' => false,
            'requirement_mode' => 'optional',
        ]);
        $this->organizationHours($organization);

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('availability.preview').'?'.http_build_query([
                'appointment_type' => $type->uuid,
                'date' => '2026-09-14',
                'timezone' => 'America/Toronto',
            ]))
            ->assertOk()
            ->assertSee('Show optional resources in the analysis')
            ->assertDontSee('data-analysis-row="optional-'.$optional->uuid.'"', false);
    }

    public function test_preview_explains_a_missing_effective_schedule(): void
    {
        [$owner, $organization] = $this->ownerContext();
        $type = $this->appointmentType($organization, 'Unscheduled service');

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('availability.preview').'?'.http_build_query([
                'appointment_type' => $type->uuid,
                'date' => '2026-09-14',
                'timezone' => 'America/Toronto',
            ]))
            ->assertOk()
            ->assertSee('0 bookable starts')
            ->assertSee('No effective availability schedule is configured.')
            ->assertSee('No free starts were found.');
    }

    public function test_replacement_resources_are_shown_as_a_required_one_of_group(): void
    {
        [$owner, $organization] = $this->ownerContext();
        $type = $this->appointmentType($organization, 'Photo session');
        $first = $this->resource($organization, 'Photographer A', true);
        $second = $this->resource($organization, 'Photographer B', true);
        foreach ([$first, $second] as $resource) {
            $type->resources()->attach($resource->getKey(), [
                'is_required' => true,
                'requirement_mode' => 'replacement',
                'replacement_group' => 'Photographer',
            ]);
        }
        $this->organizationHours($organization);

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('availability.preview').'?'.http_build_query([
                'appointment_type' => $type->uuid,
                'date' => '2026-09-14',
                'timezone' => 'America/Toronto',
            ]))
            ->assertOk()
            ->assertSee('Photographer group')
            ->assertSee('Required · one of 2')
            ->assertSee('data-analysis-row="replacement-'.$first->uuid.'"', false)
            ->assertSee('data-analysis-row="replacement-'.$second->uuid.'"', false)
            ->assertSee('Required alternative');
    }

    private function ownerContext(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $owner->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$owner, $organization];
    }

    private function appointmentType(Organization $organization, string $name): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => $name,
            'slug' => Str::slug($name),
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
            'is_active' => true,
        ]);
    }

    private function resource(Organization $organization, string $name, bool $required): Resource
    {
        return Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'equipment',
            'name' => $name,
            'is_active' => true,
            'is_required_by_default' => $required,
        ]);
    }

    private function organizationHours(Organization $organization): void
    {
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']],
        );
    }
}
