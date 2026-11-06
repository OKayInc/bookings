<?php

namespace Tests\Feature;

use App\Domain\Customers\CustomerReputationService;
use App\Domain\Customers\PostAppointmentReviewService;
use App\Domain\Payments\PaymentRuleService;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingOutcome;
use App\Models\CustomerAccessEvent;
use App\Models\CustomerAccessEntry;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\PostAppointmentOutcomeReviewEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CustomerHistoryReputationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_no_shows_can_automatically_create_a_policy_blacklist_entry(): void
    {
        [$user, $organization, $type, $contact] = $this->context();
        $organization->customerReputationSetting()->create([
            'post_appointment_review_enabled' => true,
            'review_roles' => ['owner'],
            'blacklist_mode' => 'automatic',
            'blacklist_no_show_threshold' => 2,
            'blacklist_window_days' => 180,
            'whitelist_mode' => 'disabled',
            'whitelist_success_threshold' => 5,
            'whitelist_min_revenue_minor' => 0,
            'whitelist_window_days' => 365,
            'whitelist_max_no_shows' => 0,
            'minimum_reviewed_appointments' => 1,
        ]);

        $first = $this->booking($organization, $type, $contact, 'NOSHOW000001', now('UTC')->subDays(10));
        $second = $this->booking($organization, $type, $contact, 'NOSHOW000002', now('UTC')->subDays(2));

        $service = app(CustomerReputationService::class);
        $service->recordOutcome($first, 'no_show', $user->person);
        $this->assertDatabaseMissing('customer_access_entries', ['organization_contact_id' => $contact->getKey(), 'status' => 'active']);

        $service->recordOutcome($second, 'no_show', $user->person);

        $entry = CustomerAccessEntry::query()->where('organization_contact_id', $contact->getKey())->firstOrFail();
        $this->assertSame('blacklist', $entry->list_type);
        $this->assertSame('active', $entry->status);
        $this->assertSame('policy', $entry->source);
        $this->assertSame('blacklist_no_shows', $entry->policy_key);
        $this->assertSame(2, $entry->policy_snapshot['no_shows']);
        $this->assertDatabaseHas('customer_access_events', [
            'organization_contact_id' => $contact->getKey(),
            'event_type' => 'policy_applied',
            'source' => 'policy',
        ]);
    }

    public function test_correcting_a_no_show_re_evaluates_and_removes_an_automatic_blacklist(): void
    {
        [$user, $organization, $type, $contact] = $this->context();
        $organization->customerReputationSetting()->create([
            'post_appointment_review_enabled' => true,
            'review_roles' => ['owner'],
            'blacklist_mode' => 'automatic',
            'blacklist_no_show_threshold' => 1,
            'blacklist_window_days' => 180,
            'whitelist_mode' => 'disabled',
            'whitelist_success_threshold' => 5,
            'whitelist_min_revenue_minor' => 0,
            'whitelist_window_days' => 365,
            'whitelist_max_no_shows' => 0,
            'minimum_reviewed_appointments' => 1,
        ]);

        $booking = $this->booking($organization, $type, $contact, 'CORRECT00001', now('UTC')->subDay());
        $service = app(CustomerReputationService::class);
        $service->recordOutcome($booking, 'no_show', $user->person);

        $entry = CustomerAccessEntry::query()
            ->where('organization_contact_id', $contact->getKey())
            ->where('policy_key', 'blacklist_no_shows')
            ->firstOrFail();
        $this->assertSame('active', $entry->status);

        $service->recordOutcome($booking, 'successful', $user->person);

        $this->assertSame('resolved', $entry->fresh()->status);
        $this->assertDatabaseHas('customer_access_events', [
            'customer_access_entry_id' => $entry->getKey(),
            'event_type' => 'policy_revoked',
            'source' => 'policy',
        ]);
    }

    public function test_customer_history_page_shows_revenue_coupon_column_and_outcome_controls(): void
    {
        [$user, $organization, $type, $contact] = $this->context();
        $booking = $this->booking($organization, $type, $contact, 'HISTORY00001', now('UTC')->subDay());
        $booking->forceFill(['paid_minor' => 12500, 'refunded_minor' => 2500])->save();
        app(CustomerReputationService::class)->recordOutcome($booking, 'successful', $user->person);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('customers.show', $contact))
            ->assertOk()
            ->assertSee('Appointment history')
            ->assertSee('Coupon')
            ->assertSee('Successful')
            ->assertSee('Lifetime net revenue');
    }

    public function test_post_appointment_review_email_is_sent_once_to_configured_privileged_staff(): void
    {
        Notification::fake();
        [$user, $organization, $type, $contact] = $this->context();
        $organization->customerReputationSetting()->create([
            'post_appointment_review_enabled' => true,
            'review_roles' => ['owner'],
            'blacklist_mode' => 'suggest',
            'blacklist_no_show_threshold' => 2,
            'blacklist_window_days' => 180,
            'whitelist_mode' => 'suggest',
            'whitelist_success_threshold' => 5,
            'whitelist_min_revenue_minor' => 0,
            'whitelist_window_days' => 365,
            'whitelist_max_no_shows' => 0,
            'minimum_reviewed_appointments' => 2,
        ]);
        $booking = $this->booking($organization, $type, $contact, 'REVIEW000001', now('UTC')->subHours(2));

        $service = app(PostAppointmentReviewService::class);
        $this->assertSame(1, $service->sendDue());
        Notification::assertSentTo($user, PostAppointmentOutcomeReviewEmail::class);
        $this->assertNotNull($booking->fresh()->outcome_review_requested_at_utc);
        $this->assertSame(0, $service->sendDue());
    }


    public function test_reputation_blacklist_blocks_and_whitelist_waives_prepayment(): void
    {
        [$user, $organization, , $contact] = $this->context();
        $service = app(CustomerReputationService::class);
        $rules = app(PaymentRuleService::class);

        $service->addManual($contact, 'whitelist', $user->person, 'Trusted customer');
        $this->assertTrue($rules->isReputationAllowlisted($organization, 'history@example.test', '+1 613 555 0123'));
        $this->assertNull($rules->assertMayBook($organization, 'history@example.test', '+1 613 555 0123'));

        $service->addManual($contact, 'blacklist', $user->person, 'Repeated no-shows');
        $this->expectException(\RuntimeException::class);
        $rules->assertMayBook($organization, 'history@example.test', '+1 613 555 0123');
    }

    public function test_manual_list_entry_keeps_manual_provenance(): void
    {
        [$user, , , $contact] = $this->context();

        $entry = app(CustomerReputationService::class)->addManual($contact, 'whitelist', $user->person, 'Known customer');

        $this->assertSame('manual', $entry->source);
        $this->assertNull($entry->policy_key);
        $this->assertDatabaseHas('customer_access_events', [
            'customer_access_entry_id' => $entry->getKey(),
            'event_type' => 'added',
            'source' => 'manual',
        ]);
    }

    public function test_customer_history_paginates_without_hydrating_all_bookings_or_changing_lifetime_totals(): void
    {
        [$user, $organization, $type, $contact] = $this->context();
        for ($i = 0; $i < 53; $i++) {
            $booking = $this->booking($organization, $type, $contact, sprintf('PAGED%07d', $i), now('UTC')->startOfDay()->addHours($i));
            $booking->update([
                'paid_minor' => 12500, 'refunded_minor' => $i === 0 ? 20000 : 2500,
                'status' => match ($i) { 0 => 'cancelled', 1 => 'declined', default => 'confirmed' },
            ]);
            if ($i < 14) {
                BookingOutcome::create([
                    'organization_id' => $organization->getKey(), 'booking_id' => $booking->getKey(),
                    'outcome' => $i < 10 ? 'successful' : 'no_show', 'source' => 'dashboard',
                    'recorded_at_utc' => now('UTC'),
                ]);
            }
        }
        $expected = ['appointments' => 53, 'successful' => 10, 'no_shows' => 4, 'cancelled' => 2, 'revenue_minor' => 520000];
        $hydrated = 0;
        Event::listen('eloquent.retrieved: '.Booking::class, function () use (&$hydrated): void { $hydrated++; });
        $seen = [];
        foreach ([1 => 25, 2 => 25, 3 => 3] as $page => $count) {
            $hydrated = 0;
            $response = $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid])
                ->get(route('customers.show', [$contact, 'bookings_page' => $page]))->assertOk()
                ->assertViewHas('summary', $expected);
            $bookings = $response->viewData('bookings');
            $this->assertCount($count, $bookings);
            $this->assertSame($count, $hydrated);
            $this->assertSame(53, $bookings->total());
            $seen = [...$seen, ...$bookings->pluck('reference')->all()];
            if ($page === 1) {
                $response->assertSee('bookings_page=2', false)->assertDontSee('PAGED0000000');
            }
        }
        $this->assertSame(array_map(fn ($i) => sprintf('PAGED%07d', $i), range(52, 0)), $seen);
    }

    public function test_empty_customer_history_is_zero_and_other_organizations_remain_inaccessible(): void
    {
        [$user, $organization, , $contact] = $this->context();
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('customers.show', $contact))->assertOk()->assertViewHas('summary', [
                'appointments' => 0, 'successful' => 0, 'no_shows' => 0, 'cancelled' => 0, 'revenue_minor' => 0,
            ])->assertSee('No appointment history.');
        $other = OrganizationContact::create(['organization_id' => Organization::factory()->create()->getKey(), 'email' => 'other@example.test']);
        $this->get(route('customers.show', [$other, 'bookings_page' => 2]))->assertNotFound();
    }

    public function test_customer_history_loads_only_the_latest_25_audit_events_and_open_access_entries(): void
    {
        [$user, $organization, , $contact] = $this->context();
        for ($i = 0; $i < 30; $i++) {
            CustomerAccessEvent::create([
                'organization_id' => $organization->getKey(), 'organization_contact_id' => $contact->getKey(),
                'event_type' => 'added', 'source' => 'manual', 'reason' => 'Audit row '.$i,
                'actor_person_id' => $user->person_id, 'occurred_at_utc' => now('UTC')->startOfDay()->addMinutes($i),
            ]);
        }
        foreach (['active', 'suggested', 'resolved'] as $status) {
            CustomerAccessEntry::create([
                'organization_id' => $organization->getKey(), 'organization_contact_id' => $contact->getKey(),
                'list_type' => 'whitelist', 'status' => $status, 'source' => 'manual',
            ]);
        }
        $response = $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('customers.show', $contact))->assertOk();
        $loaded = $response->viewData('customer');
        $this->assertCount(25, $loaded->accessEvents);
        $this->assertSame('Audit row 29', $loaded->accessEvents->first()->reason);
        $this->assertSame('Audit row 5', $loaded->accessEvents->last()->reason);
        $this->assertCount(2, $loaded->accessEntries);
        $this->assertFalse($loaded->relationLoaded('bookings'));
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD', 'timezone' => 'America/Toronto']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Customer History Session',
            'slug' => 'customer-history-session',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'start_interval_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'fixed',
            'fixed_price_minor' => 12500,
            'email_verification_mode' => 'none',
            'is_active' => true,
        ]);

        $contact = OrganizationContact::create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'History',
            'last_name' => 'Customer',
            'email' => 'history@example.test',
            'phone' => '+1 (613) 555-0123',
        ]);

        return [$user, $organization, $type, $contact];
    }

    private function booking(Organization $organization, AppointmentType $type, OrganizationContact $contact, string $reference, $start): Booking
    {
        $start = CarbonImmutable::parse($start);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start,
            'ends_at_utc' => $start->addHour(),
            'blocked_starts_at_utc' => $start,
            'blocked_ends_at_utc' => $start->addHour(),
            'scheduling_timezone' => 'America/Toronto',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => 'scheduled',
        ]);

        return Booking::create([
            'organization_id' => $organization->getKey(),
            'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(),
            'organization_contact_id' => $contact->getKey(),
            'reference' => $reference,
            'status' => 'confirmed',
            'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto',
            'base_price_minor' => 12500,
            'price_minor' => 12500,
            'currency' => 'CAD',
            'first_name' => 'History',
            'last_name' => 'Customer',
            'email' => 'history@example.test',
            'email_normalized' => 'history@example.test',
            'phone' => '+1 (613) 555-0123',
            'manage_token_hash' => random_bytes(32),
        ]);
    }
}
