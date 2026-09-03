<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Appointment;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class M9R4ConditionalResourceRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 12:00:00 UTC'));
        Notification::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_owner_can_configure_a_one_of_n_rule_while_resources_remain_normally_optional(): void
    {
        [$owner, $organization, $type] = $this->context();
        $first = $this->optionalResource($organization, $type, 'Video recorder A');
        $second = $this->optionalResource($organization, $type, 'Video recorder B');

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.store', $type), [
                'type' => 'radio',
                'label' => 'Do you want video?',
                'position' => 1,
                'is_active' => '1',
                'options' => [
                    0 => ['label' => 'No', 'pricing_adjustment_type' => 'none'],
                    1 => ['label' => 'Yes', 'pricing_adjustment_type' => 'none'],
                ],
                'resource_requirement_enabled' => '1',
                'resource_requirement_trigger_option_index' => 1,
                'resource_unavailable_default_option_index' => 0,
                'resource_requirement_group_name' => 'Video',
                'resource_requirement_fulfillment_mode' => 'one_of',
                'resource_requirement_resource_uuids' => [$first->uuid, $second->uuid],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appointment-types.questionnaire.index', $type));

        $question = $type->questions()->where('label', 'Do you want video?')->firstOrFail();
        $rule = $question->resourceRequirementRule()->with(['triggerOption', 'unavailableDefaultOption', 'resources'])->firstOrFail();
        $this->assertSame('Yes', $rule->triggerOption->label);
        $this->assertSame('No', $rule->unavailableDefaultOption->label);
        $this->assertSame('one_of', $rule->fulfillment_mode->value);
        $this->assertEqualsCanonicalizing([$first->uuid, $second->uuid], $rule->resources->pluck('uuid')->all());
        $this->assertFalse((bool) $type->fresh('resources')->resources->firstWhere('uuid', $first->uuid)->pivot->is_required);
    }

    public function test_yes_promotes_every_available_one_of_n_candidate_on_the_booking_snapshot(): void
    {
        [, $organization, $type] = $this->context();
        $first = $this->optionalResource($organization, $type, 'Video recorder A');
        $second = $this->optionalResource($organization, $type, 'Video recorder B');
        [$question, , $yes] = $this->questionAndRule($type, [$first, $second], 'one_of');

        [$token, $continueUrl] = $this->hold($type);
        $this->get($continueUrl)
            ->assertOk()
            ->assertSee('data-resource-unavailable="0"', false);

        $this->post(route('public.booking-holds.store', $token), [
            'first_name' => 'Video',
            'last_name' => 'Client',
            'email' => 'video@example.test',
            'answers' => [$question->uuid => $yes->uuid],
        ])->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $resources = $booking->appointment->fresh('resources')->resources;
        $this->assertEqualsCanonicalizing([$first->uuid, $second->uuid], $resources->pluck('uuid')->all());
        foreach ($resources as $resource) {
            $this->assertTrue((bool) $resource->pivot->is_required);
            $this->assertSame('Video', $resource->pivot->replacement_group);
        }
        $this->assertSame($yes->uuid, data_get($booking->answers()->firstOrFail()->value_json, 'value.uuid'));
    }

    public function test_unavailable_group_hides_question_and_server_forces_configured_no_answer(): void
    {
        [, $organization, $type] = $this->context();
        $first = $this->optionalResource($organization, $type, 'Video recorder A');
        $second = $this->optionalResource($organization, $type, 'Video recorder B');
        [$question, $no, $yes] = $this->questionAndRule($type, [$first, $second], 'one_of');
        $this->occupyResourcesOnAnotherAppointmentType($organization, [$first, $second]);

        [$token, $continueUrl] = $this->hold($type);
        $this->get($continueUrl)
            ->assertOk()
            ->assertSee('data-resource-unavailable="1"', false)
            ->assertSee('data-resource-default-control', false);

        // Even a forged Yes is replaced by the server-owned unavailable default.
        $this->post(route('public.booking-holds.store', $token), [
            'first_name' => 'No Video',
            'last_name' => 'Client',
            'email' => 'no-video@example.test',
            'answers' => [$question->uuid => $yes->uuid],
        ])->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame($no->uuid, data_get($booking->answers()->firstOrFail()->value_json, 'value.uuid'));
        $this->assertCount(0, $booking->appointment->fresh('resources')->resources);
    }

    public function test_all_mode_is_unavailable_unless_every_member_can_be_held(): void
    {
        [, $organization, $type] = $this->context();
        $first = $this->optionalResource($organization, $type, 'Camera');
        $second = $this->optionalResource($organization, $type, 'Microphone');
        [$question, $no, $yes] = $this->questionAndRule($type, [$first, $second], 'all');
        $this->occupyResourcesOnAnotherAppointmentType($organization, [$second]);

        [$token, $continueUrl] = $this->hold($type);
        $this->get($continueUrl)->assertOk()->assertSee('data-resource-unavailable="1"', false);

        $this->post(route('public.booking-holds.store', $token), [
            'first_name' => 'All',
            'last_name' => 'Resources',
            'email' => 'all@example.test',
            'answers' => [$question->uuid => $yes->uuid],
        ])->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame($no->uuid, data_get($booking->answers()->firstOrFail()->value_json, 'value.uuid'));
        $camera = $booking->appointment->fresh('resources')->resources->firstWhere('uuid', $first->uuid);
        $this->assertNotNull($camera);
        $this->assertFalse((bool) $camera->pivot->is_required);
        $this->assertNull($camera->pivot->replacement_group);
    }

    public function test_all_mode_promotes_every_member_without_creating_a_replacement_group(): void
    {
        [, $organization, $type] = $this->context();
        $first = $this->optionalResource($organization, $type, 'Camera');
        $second = $this->optionalResource($organization, $type, 'Microphone');
        [$question, , $yes] = $this->questionAndRule($type, [$first, $second], 'all');

        [$token, $continueUrl] = $this->hold($type);
        $this->get($continueUrl)->assertOk()->assertSee('data-resource-unavailable="0"', false);
        $this->post(route('public.booking-holds.store', $token), [
            'first_name' => 'Complete',
            'last_name' => 'Crew',
            'email' => 'crew@example.test',
            'answers' => [$question->uuid => $yes->uuid],
        ])->assertRedirect();

        $resources = Booking::query()->firstOrFail()->appointment->fresh('resources')->resources;
        $this->assertEqualsCanonicalizing([$first->uuid, $second->uuid], $resources->pluck('uuid')->all());
        foreach ($resources as $resource) {
            $this->assertTrue((bool) $resource->pivot->is_required);
            $this->assertNull($resource->pivot->replacement_group);
        }
    }

    private function context(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'timezone' => 'America/Toronto',
            'currency' => 'CAD',
        ]);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $owner->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Conditional Video Session',
            'slug' => 'conditional-video-session',
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
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']],
        );

        return [$owner, $organization, $type];
    }

    private function optionalResource(Organization $organization, AppointmentType $type, string $name): Resource
    {
        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'equipment',
            'name' => $name,
            'timezone' => 'America/Toronto',
            'is_active' => true,
            'is_required_by_default' => false,
        ]);
        $type->resources()->attach($resource->getKey(), [
            'is_required' => false,
            'requirement_mode' => 'optional',
        ]);

        return $resource;
    }

    private function questionAndRule(AppointmentType $type, array $resources, string $mode): array
    {
        $question = $type->questions()->create([
            'type' => 'radio',
            'label' => 'Do you want video?',
            'is_required' => true,
            'is_active' => true,
            'position' => 1,
        ]);
        $no = $question->options()->create(['label' => 'No', 'value' => 'no', 'position' => 0, 'is_active' => true]);
        $yes = $question->options()->create(['label' => 'Yes', 'value' => 'yes', 'position' => 1, 'is_active' => true]);
        $rule = $question->resourceRequirementRule()->create([
            'trigger_option_id' => $yes->getKey(),
            'unavailable_default_option_id' => $no->getKey(),
            'group_name' => 'Video',
            'fulfillment_mode' => $mode,
        ]);
        $rule->resources()->sync(collect($resources)->map->getKey()->all());

        return [$question, $no, $yes];
    }

    private function hold(AppointmentType $type): array
    {
        $lease = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-09-07 09:00', 'America/Toronto')->utc(),
            60,
            'America/Toronto',
            1,
        );

        return [$lease->token, route('public.booking-holds.edit', $lease->token)];
    }

    private function occupyResourcesOnAnotherAppointmentType(Organization $organization, array $resources): void
    {
        $other = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Other session',
            'slug' => 'other-session-'.count($resources),
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'email_verification_mode' => 'none',
            'is_active' => true,
        ]);
        $startsAt = CarbonImmutable::parse('2026-09-07 09:00', 'America/Toronto')->utc();
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $other->getKey(),
            'starts_at_utc' => $startsAt,
            'ends_at_utc' => $startsAt->addHour(),
            'blocked_starts_at_utc' => $startsAt,
            'blocked_ends_at_utc' => $startsAt->addHour(),
            'scheduling_timezone' => 'America/Toronto',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => 'scheduled',
        ]);
        foreach ($resources as $resource) {
            $appointment->resources()->attach($resource->getKey(), [
                'is_required' => true,
                'quantity_reserved' => 1,
            ]);
        }
    }
}
