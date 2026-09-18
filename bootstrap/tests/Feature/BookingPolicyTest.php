<?php

namespace Tests\Feature;

use App\Domain\Bookings\BookingPolicyService;
use App\Domain\Bookings\BookingRescheduleService;
use App\Enums\BookingHoldStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Organization;
use App\Models\OrganizationContact;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_policy_deadlines_and_max_reschedules_are_enforced(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-25 12:00 UTC'));
        $booking = $this->booking(now('UTC')->addDays(2));
        $policy = app(BookingPolicyService::class);
        $this->assertTrue($policy->canCancel($booking));
        $this->assertTrue($policy->canReschedule($booking));

        $booking->update(['rescheduling_max_count' => 1, 'reschedule_count' => 1]);
        $this->assertFalse($policy->canReschedule($booking->fresh(['appointment', 'organization'])));

        CarbonImmutable::setTestNow(CarbonImmutable::instance($booking->appointment->starts_at_utc)->subHours(12));
        $this->assertFalse($policy->canCancel($booking->fresh(['appointment', 'organization'])));
        CarbonImmutable::setTestNow();
    }

    public function test_reschedule_moves_booking_and_records_history(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-25 12:00 UTC'));
        $booking = $this->booking(now('UTC')->addDays(2));
        $token = Str::random(64);
        $newStart = now('UTC')->addDays(4)->startOfHour();
        BookingHold::create([
            'organization_id' => $booking->organization_id, 'appointment_type_id' => $booking->appointment_type_id,
            'token_hash' => hash('sha256', $token, true), 'starts_at_utc' => $newStart, 'ends_at_utc' => $newStart->addHour(),
            'blocked_starts_at_utc' => $newStart, 'blocked_ends_at_utc' => $newStart->addHour(),
            'booking_timezone' => 'America/Toronto', 'duration_value' => 60, 'attendee_count' => 1,
            'status' => BookingHoldStatus::Active, 'expires_at_utc' => now('UTC')->addMinutes(15),
        ]);

        app(BookingRescheduleService::class)->applyFromHold($booking, $token);
        $booking->refresh();
        $this->assertSame(1, $booking->reschedule_count);
        $this->assertTrue($booking->appointment->starts_at_utc->equalTo($newStart));
        $this->assertCount(1, $booking->reschedules);
        CarbonImmutable::setTestNow();
    }

    private function booking($start): Booking
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Policy Session', 'slug' => 'policy-session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1, 'duration_mode' => 'fixed',
            'duration_unit' => 'minute', 'duration_value' => 60, 'start_interval_minutes' => 60,
            'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'pricing_mode' => 'free',
            'email_verification_mode' => 'none', 'is_active' => true,
        ]);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $start->copy()->addHour(),
            'blocked_starts_at_utc' => $start, 'blocked_ends_at_utc' => $start->copy()->addHour(),
            'scheduling_timezone' => 'America/Toronto', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        $contact = OrganizationContact::create(['organization_id' => $organization->getKey(), 'first_name' => 'A', 'last_name' => 'B', 'email' => 'policy@example.test']);
        return Booking::create([
            'organization_id' => $organization->getKey(), 'appointment_id' => $appointment->getKey(), 'appointment_type_id' => $type->getKey(),
            'organization_contact_id' => $contact->getKey(), 'reference' => 'POLICY000001', 'status' => 'confirmed', 'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto', 'base_price_minor' => 0, 'price_minor' => 0, 'currency' => 'CAD',
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'policy@example.test', 'email_normalized' => 'policy@example.test',
            'manage_token_hash' => random_bytes(32), 'cancellation_allowed' => true, 'cancellation_notice_value' => 24,
            'cancellation_notice_unit' => 'hour', 'rescheduling_allowed' => true, 'rescheduling_notice_value' => 24,
            'rescheduling_notice_unit' => 'hour', 'rescheduling_max_count' => 0,
        ]);
    }
}
