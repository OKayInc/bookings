<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\EventAdmissionApprovalService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Domain\Tickets\EventLocationDisclosureService;
use App\Enums\AvailabilityScope;
use App\Enums\EventAdmissionApprovalStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\EventAdmissionApproval;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\EventAdmissionApprovalRequestEmail;
use App\Notifications\EventLocationDisclosedEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class M9R11PrivateTicketedEventTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_free_private_event_waits_for_coordinator_and_hides_qr_until_accepted(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow('2026-09-16 12:00:00 UTC');
        [$owner, $organization] = $this->ownerContext();
        $type = $this->privateEvent($organization, [
            'location_disclosure_mode' => 'after_acceptance',
        ]);
        $this->availability($organization);

        [$booking, $manageToken] = $this->book($type, CarbonImmutable::parse('2026-09-21 09:00', 'America/Toronto')->utc());
        $approval = EventAdmissionApproval::firstOrFail();
        $approvalToken = 'known-event-approval-token';
        $approval->update(['response_token_hash' => hash('sha256', $approvalToken, true)]);

        $this->assertSame('pending_event_approval', $booking->status->value);
        $this->assertSame('reserved', $booking->tickets->first()->status->value);
        Notification::assertSentOnDemand(EventAdmissionApprovalRequestEmail::class);

        $booking->answers()->create([
            'appointment_question_id' => null,
            'question_uuid_snapshot' => (string) Str::uuid(),
            'question_label' => 'Why would you like to attend?',
            'question_type' => 'text',
            'value_json' => ['value' => 'I support the local arts community.'],
            'position' => 1,
        ]);
        $emailHtml = (new EventAdmissionApprovalRequestEmail(
            $approval->fresh(['booking.appointmentType', 'booking.appointment', 'booking.answers.files']),
            $approvalToken,
        ))->toMail((object) [])->render();
        $this->assertStringContainsString('Why would you like to attend?', (string) $emailHtml);
        $this->assertStringContainsString('I support the local arts community.', (string) $emailHtml);
        $this->get(route('public.event-admission-approvals.show', [$approval, $approvalToken]))
            ->assertOk()
            ->assertSee('Why would you like to attend?')
            ->assertSee('I support the local arts community.');

        $ticket = $booking->tickets->first();
        $this->get(route('public.bookings.manage', [$booking, $manageToken]))
            ->assertOk()
            ->assertSee('QR-code tickets will appear here only if your request is accepted.')
            ->assertDontSee('View / print ticket');
        $this->get(route('public.bookings.tickets.show', [$booking, $manageToken, $ticket]))->assertForbidden();

        $this->post(route('public.event-admission-approvals.respond', [$approval, $approvalToken]), [
            'action' => 'accepted',
            'response_note' => 'Approved guest.',
        ])->assertOk()->assertSee('Decision saved');

        $this->assertSame('confirmed', $booking->fresh()->status->value);
        $this->assertSame('issued', $ticket->fresh()->status->value);
        $this->assertSame('accepted', $approval->fresh()->status->value);
        Notification::assertSentOnDemand(EventLocationDisclosedEmail::class);
        $this->get(route('public.bookings.manage', [$booking, $manageToken]))
            ->assertOk()
            ->assertSee('123 Secret Street')
            ->assertSee('View / print ticket');
    }

    public function test_first_coordinator_decision_supersedes_other_links_and_decline_voids_tickets(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow('2026-09-16 12:00:00 UTC');
        [, $organization] = $this->ownerContext();
        $manager = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $manager->person_id,
            'role' => MembershipRole::Manager,
            'status' => MembershipStatus::Active,
        ]);
        $type = $this->privateEvent($organization);
        $this->availability($organization);
        [$booking] = $this->book($type, CarbonImmutable::parse('2026-09-21 09:00', 'America/Toronto')->utc());
        $approvals = EventAdmissionApproval::orderBy('created_at')->get();

        app(EventAdmissionApprovalService::class)->respond(
            $approvals->first(),
            EventAdmissionApprovalStatus::Declined,
            'Guest list is full.',
        );

        $this->assertSame('declined', $booking->fresh()->status->value);
        $this->assertSame('voided', $booking->tickets->first()->fresh()->status->value);
        $this->assertSame('superseded', $approvals->last()->fresh()->status->value);
        $this->expectException(\RuntimeException::class);
        app(EventAdmissionApprovalService::class)->respond(
            $approvals->last(),
            EventAdmissionApprovalStatus::Accepted,
        );
    }

    public function test_location_is_released_to_accepted_attendee_at_configured_hour_threshold(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow('2026-09-16 12:00:00 UTC');
        [, $organization] = $this->ownerContext();
        $type = $this->privateEvent($organization, [
            'location_disclosure_mode' => 'hours_before_event',
            'location_disclosure_hours' => 24,
        ]);
        $this->availability($organization);
        [$booking] = $this->book($type, CarbonImmutable::parse('2026-09-21 09:00', 'America/Toronto')->utc());
        app(EventAdmissionApprovalService::class)->respond(
            EventAdmissionApproval::firstOrFail(),
            EventAdmissionApprovalStatus::Accepted,
        );

        $locations = app(EventLocationDisclosureService::class);
        $this->assertStringContainsString('Mystery location', $locations->attendeeLabel($booking->fresh('appointment')));
        Notification::assertSentOnDemandTimes(EventLocationDisclosedEmail::class, 0);

        CarbonImmutable::setTestNow($booking->appointment->show_starts_at_utc->subHours(24));
        $this->artisan('appointments:disclose-event-locations')->assertSuccessful();

        $this->assertStringContainsString('123 Secret Street', $locations->attendeeLabel($booking->fresh('appointment')));
        $this->assertNotNull($booking->fresh()->location_notification_sent_at_utc);
        Notification::assertSentOnDemand(EventLocationDisclosedEmail::class);
    }

    public function test_private_event_configuration_requires_ticketing_free_pricing_and_a_mystery_location(): void
    {
        [$owner, $organization] = $this->ownerContext();

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $this->configuration([
                'private_event_enabled' => '1',
                'pricing_mode' => 'per_attendee',
                'attendee_pricing_mode' => 'flat',
                'attendee_price' => '10.00',
                'location_disclosure_mode' => 'after_acceptance',
                'event_location' => '',
            ]))
            ->assertSessionHasErrors(['private_event_enabled', 'event_location']);
    }

    private function ownerContext(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto', 'currency' => 'CAD']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $owner->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$owner, $organization];
    }

    private function privateEvent(Organization $organization, array $overrides = []): AppointmentType
    {
        $type = AppointmentType::create(array_replace([
            'organization_id' => $organization->getKey(),
            'name' => 'Secret Concert', 'slug' => 'secret-concert', 'visibility' => 'public',
            'attendance_mode' => 'group', 'capacity' => 20, 'ticketing_enabled' => true,
            'private_event_enabled' => true, 'event_location' => "Secret Hall\n123 Secret Street",
            'location_disclosure_mode' => 'after_acceptance', 'location_disclosure_hours' => null,
            'show_start_offset_minutes' => 60, 'show_end_offset_minutes' => 120,
            'ticket_seating_scheme' => 'consecutive', 'ticket_seat_optional' => false, 'ticket_seat_blocks' => [],
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 180,
            'start_interval_minutes' => 180, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'free', 'email_verification_mode' => 'none', 'is_active' => true,
        ], $overrides));
        $type->eventOccurrences()->create([
            'starts_at_utc' => CarbonImmutable::parse('2026-09-21 09:00', 'America/Toronto')->utc(),
            'timezone' => 'America/Toronto', 'venue' => $type->event_location, 'is_active' => true,
        ]);
        return $type;
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

    private function book(AppointmentType $type, CarbonImmutable $start): array
    {
        $lease = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            $start,
            180,
            'America/Toronto',
            1,
        );
        $result = app(BookingCreationService::class)->createFromHold(
            $lease->token,
            ['first_name' => 'Prospective', 'last_name' => 'Guest', 'email' => 'guest@example.test'],
        );

        return [$result->booking->fresh(['appointment', 'appointmentType', 'tickets']), $result->manageToken];
    }

    private function configuration(array $overrides = []): array
    {
        return array_replace([
            'event_date' => '2026-09-21', 'event_time' => '09:00',
            'name' => 'Secret Concert', 'visibility' => 'public', 'attendance_mode' => 'group',
            'capacity' => 20, 'ticketing_enabled' => '1', 'show_start_offset_minutes' => 60,
            'show_end_offset_minutes' => 180, 'ticket_seating_scheme' => 'consecutive',
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 240,
            'start_interval_minutes' => 60, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'free', 'email_verification_mode' => 'none', 'is_active' => '1',
        ], $overrides);
    }
}
