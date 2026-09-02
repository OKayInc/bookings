<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingCancellationService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\BookingPolicyService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_ticketed_event_configuration_supports_optional_section_seats(): void
    {
        [$user, $organization] = $this->ownerContext();

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $this->configuration([
                'ticket_seating_scheme' => 'section_seat',
                'ticket_seat_optional' => '1',
                'capacity' => 4,
                'pricing_mode' => 'per_attendee',
                'attendee_pricing_mode' => 'flat',
                'attendee_price' => '25.00',
                'ticket_seat_blocks' => [
                    ['section' => 'Floor', 'first_seat' => 1, 'last_seat' => 2, 'quantity' => null, 'seat_fee' => '10.00'],
                    ['section' => 'Balcony', 'first_seat' => null, 'last_seat' => null, 'quantity' => 2],
                ],
            ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('appointment-types.index'));

        $type = AppointmentType::where('name', 'Ticketed Concert')->firstOrFail();
        $this->assertTrue($type->ticketing_enabled);
        $this->assertSame('group', $type->attendance_mode->value);
        $this->assertSame('fixed', $type->duration_mode->value);
        $this->assertSame(60, $type->show_start_offset_minutes);
        $this->assertSame(180, $type->show_end_offset_minutes);
        $this->assertSame('section_seat', $type->ticket_seating_scheme->value);
        $this->assertTrue($type->ticket_seat_optional);
        $this->assertSame(1000, $type->ticket_seat_blocks[0]['seat_fee_minor']);
        $this->assertSame(0, $type->ticket_seat_blocks[1]['seat_fee_minor']);
        $this->assertSame(2, $type->ticket_seat_blocks[1]['quantity']);
    }

    public function test_ticket_configuration_rejects_single_variable_and_non_ticket_pricing_modes(): void
    {
        [$user, $organization] = $this->ownerContext();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $this->configuration([
                'attendance_mode' => 'single',
                'duration_mode' => 'variable',
                'minimum_duration_value' => 1,
                'maximum_duration_value' => 4,
                'duration_increment_value' => 1,
                'pricing_mode' => 'fixed',
                'fixed_price' => '100.00',
            ]))
            ->assertSessionHasErrors(['attendance_mode', 'duration_mode', 'pricing_mode']);

        $this->assertSame(0, AppointmentType::count());
    }

    public function test_free_ticket_configuration_rejects_seating_fees(): void
    {
        [$user, $organization] = $this->ownerContext();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $this->configuration([
                'capacity' => 2,
                'ticket_seating_scheme' => 'section_seat',
                'ticket_seat_blocks' => [
                    ['section' => 'Floor', 'first_seat' => 1, 'last_seat' => 2, 'seat_fee' => '5.00'],
                ],
            ]))
            ->assertSessionHasErrors('ticket_seat_blocks');

        $this->assertSame(0, AppointmentType::count());
    }

    public function test_ticket_configuration_rejects_times_outside_booking_and_inventory_mismatch(): void
    {
        [$user, $organization] = $this->ownerContext();

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $this->configuration([
                'show_start_offset_minutes' => 300,
                'ticket_seating_scheme' => 'row_seat',
                'capacity' => 5,
                'ticket_seat_blocks' => [
                    ['row' => 'A', 'first_seat' => 1, 'last_seat' => 4],
                ],
            ]));

        $response->assertSessionHasErrors(['show_start_offset_minutes', 'ticket_seat_blocks']);
        $this->assertSame(0, AppointmentType::count());
    }

    public function test_booking_issues_one_numbered_ticket_per_attendee_and_check_in_is_single_use(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00 UTC'));
        [, $organization] = $this->ownerContext();
        $type = $this->ticketType($organization, ['capacity' => 5]);
        $this->availability($organization);
        $start = CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc();

        $first = $this->book($type, $start, 2, 'first@example.test');
        $this->assertSame('confirmed', $first->status->value);
        $this->assertTrue($first->appointment->ticketing_enabled);
        $this->assertSame('2026-08-31 10:00', $first->appointment->show_starts_at_utc->setTimezone('America/Toronto')->format('Y-m-d H:i'));
        $this->assertSame('2026-08-31 11:00', $first->appointment->show_ends_at_utc->setTimezone('America/Toronto')->format('Y-m-d H:i'));
        $this->assertSame(['1', '2'], $first->tickets->pluck('seat_label')->all());
        $this->assertSame(['issued', 'issued'], $first->tickets->pluck('status')->map->value->all());

        $second = $this->book($type->fresh(['organization', 'resources']), $start, 2, 'second@example.test');
        $this->assertTrue($second->appointment->is($first->appointment));
        $this->assertSame(['3', '4'], $second->tickets->pluck('seat_label')->all());

        $employee = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $employee->person_id,
            'role' => MembershipRole::Employee,
            'status' => MembershipStatus::Active,
        ]);
        $ticket = $first->tickets->first();
        $this->actingAs($employee)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('tickets.check-in'), ['code' => strtolower($ticket->code)])
            ->assertSessionHas('success');
        $this->assertSame('checked_in', $ticket->fresh()->status->value);
        $this->assertSame($employee->person_id, $ticket->fresh()->checked_in_by_person_id);
        $this->actingAs($employee)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('tickets.show', [$first, $ticket]))
            ->assertOk()
            ->assertSee($ticket->code)
            ->assertSee('<svg', false);
        $policy = app(BookingPolicyService::class);
        $this->assertFalse($policy->canReschedule($first));
        $this->assertSame('A booking with checked-in tickets cannot be rescheduled.', $policy->reschedulingStatus($first));
        $this->assertSame('Cancellation is available.', $policy->cancellationStatus($first));

        $this->actingAs($employee)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('tickets.check-in'), ['code' => $ticket->code])
            ->assertSessionHas('error');
        $this->assertSame(1, Ticket::where('status', 'checked_in')->count());
    }

    public function test_cancelled_tickets_are_voided_and_their_seats_can_be_sold_again(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00 UTC'));
        [, $organization] = $this->ownerContext();
        $type = $this->ticketType($organization, ['capacity' => 4]);
        $this->availability($organization);
        $start = CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc();

        $first = $this->book($type, $start, 2, 'first@example.test');
        $second = $this->book($type->fresh(['organization', 'resources']), $start, 2, 'second@example.test');
        app(BookingCancellationService::class)->cancelByStaff($second->load('appointment'));

        $this->assertSame(['voided', 'voided'], $second->tickets()->get()->pluck('status')->map->value->all());
        $this->assertSame([null, null], $second->tickets()->get()->pluck('seat_key')->all());

        $replacement = $this->book($type->fresh(['organization', 'resources']), $start, 2, 'replacement@example.test');
        $this->assertSame(['3', '4'], $replacement->tickets->pluck('seat_label')->all());
        $this->assertSame(6, Ticket::count());
        $this->assertSame(['1', '2'], $first->tickets->pluck('seat_label')->all());
    }

    public function test_ticket_inventory_definition_is_locked_while_a_future_event_has_bookings(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00 UTC'));
        [$user, $organization] = $this->ownerContext();
        $type = $this->ticketType($organization, ['capacity' => 5]);
        $this->availability($organization);
        $this->book(
            $type,
            CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc(),
            1,
            'locked@example.test',
        );

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('appointment-types.update', $type), $this->configuration([
                'name' => $type->name,
                'capacity' => 6,
                'duration_value' => 180,
                'show_end_offset_minutes' => 120,
            ]))
            ->assertSessionHasErrors('ticketing_enabled');

        $this->assertSame(5, $type->fresh()->capacity);
    }

    public function test_paid_ticket_stays_reserved_until_the_payments_milestone_confirms_booking(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00 UTC'));
        [, $organization] = $this->ownerContext();
        $type = $this->ticketType($organization, [
            'capacity' => 2,
            'pricing_mode' => 'per_attendee',
            'attendee_pricing_mode' => 'flat',
            'attendee_price_minor' => 2500,
        ]);
        $this->availability($organization);

        $booking = $this->book(
            $type,
            CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc(),
            1,
            'paid@example.test',
        );

        $this->assertSame('pending_payment', $booking->status->value);
        $this->assertSame('reserved', $booking->tickets->first()->status->value);
    }

    public function test_held_section_fees_are_included_in_booking_price_and_ticket_snapshots(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00 UTC'));
        [, $organization] = $this->ownerContext();
        $type = $this->ticketType($organization, [
            'capacity' => 4,
            'ticket_seating_scheme' => 'section_seat',
            'ticket_seat_blocks' => [
                ['section' => 'Floor', 'row' => null, 'first_seat' => 1, 'last_seat' => 2, 'quantity' => 2, 'seat_fee_minor' => 500],
                ['section' => 'Balcony', 'row' => null, 'first_seat' => 1, 'last_seat' => 2, 'quantity' => 2, 'seat_fee_minor' => 0],
            ],
            'pricing_mode' => 'per_attendee',
            'attendee_pricing_mode' => 'flat',
            'attendee_price_minor' => 2000,
        ]);
        $this->availability($organization);
        $start = CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc();

        $floor = $this->book($type, $start, 2, 'floor@example.test');
        $this->assertSame(5000, $floor->base_price_minor);
        $this->assertSame(5000, $floor->price_minor);
        $this->assertSame([500, 500], $floor->tickets->pluck('seat_fee_minor')->all());
        $this->assertSame(['Floor', 'Floor'], $floor->tickets->pluck('section_label')->all());
        $this->assertSame(1000, (int) $floor->priceLines()->where('source_type', 'ticket_seating')->sum('amount_minor'));

        $balcony = $this->book($type->fresh(['organization', 'resources']), $start, 1, 'balcony@example.test');
        $this->assertSame(2000, $balcony->base_price_minor);
        $this->assertSame([0], $balcony->tickets->pluck('seat_fee_minor')->all());
        $this->assertSame(['Balcony'], $balcony->tickets->pluck('section_label')->all());
    }

    public function test_expired_unverified_booking_voids_tickets_and_releases_seats(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00 UTC'));
        [, $organization] = $this->ownerContext();
        $type = $this->ticketType($organization, [
            'capacity' => 2,
            'email_verification_mode' => 'before_confirmation',
        ]);
        $this->availability($organization);
        $booking = $this->book(
            $type,
            CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc(),
            1,
            'unverified@example.test',
        );
        $this->assertSame('pending_email_verification', $booking->status->value);
        $this->assertSame('reserved', $booking->tickets->first()->status->value);

        CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addHours(25));
        $this->artisan('appointments:expire-pending-bookings')->assertSuccessful();

        $ticket = $booking->tickets->first()->fresh();
        $this->assertSame('voided', $ticket->status->value);
        $this->assertNull($ticket->seat_key);
    }

    private function configuration(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Ticketed Concert',
            'visibility' => 'public',
            'attendance_mode' => 'group',
            'capacity' => 10,
            'ticketing_enabled' => '1',
            'show_start_offset_minutes' => 60,
            'show_end_offset_minutes' => 180,
            'ticket_seating_scheme' => 'consecutive',
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 240,
            'start_interval_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'email_verification_mode' => 'none',
            'is_active' => '1',
        ], $overrides);
    }

    private function ticketType(Organization $organization, array $overrides = []): AppointmentType
    {
        return AppointmentType::create(array_replace([
            'organization_id' => $organization->getKey(),
            'name' => 'Live Concert', 'slug' => 'live-concert', 'visibility' => 'public',
            'attendance_mode' => 'group', 'capacity' => 5,
            'ticketing_enabled' => true, 'show_start_offset_minutes' => 60, 'show_end_offset_minutes' => 120,
            'ticket_seating_scheme' => 'consecutive', 'ticket_seat_optional' => false, 'ticket_seat_blocks' => [],
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 180,
            'start_interval_minutes' => 180, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'free', 'email_verification_mode' => 'none', 'is_active' => true,
        ], $overrides));
    }

    private function availability(Organization $organization): void
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

    private function book(AppointmentType $type, CarbonImmutable $start, int $count, string $email)
    {
        $lease = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            $start,
            180,
            'America/Toronto',
            $count,
        );
        $result = app(BookingCreationService::class)->createFromHold(
            $lease->token,
            ['first_name' => 'Ticket', 'last_name' => 'Buyer', 'email' => $email],
            array_fill(0, max(0, $count - 1), ['first_name' => 'Guest']),
        );

        return $result->booking->fresh(['appointment', 'tickets']);
    }

    private function ownerContext(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto', 'currency' => 'CAD']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }
}
