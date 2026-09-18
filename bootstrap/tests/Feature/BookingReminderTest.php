<?php

namespace Tests\Feature;

use App\Domain\Bookings\BookingReminderService;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\Resource;
use App\Models\ReminderDelivery;
use App\Models\User;
use App\Notifications\BookingReminderEmail;
use App\Notifications\ResourceReminderEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_client_and_resource_reminders_are_sent_only_once(): void
    {
        Notification::fake();
        $now = CarbonImmutable::parse('2026-08-25 12:00 UTC');
        CarbonImmutable::setTestNow($now);
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $employee = User::factory()->create();
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Reminder Session', 'slug' => 'reminder-session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1, 'duration_mode' => 'fixed', 'duration_unit' => 'hour',
            'duration_value' => 1, 'start_interval_minutes' => 60, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'free', 'email_verification_mode' => 'none', 'reminder_enabled' => true,
            'reminder_threshold_basis' => 'lead_time', 'reminder_threshold_days' => 7, 'reminder_before_value' => 2,
            'reminder_before_unit' => 'day', 'reminder_clients' => true, 'reminder_resources' => true, 'is_active' => true,
        ]);
        $resource = Resource::create([
            'organization_id' => $organization->getKey(), 'person_id' => $employee->person_id, 'type' => 'person', 'name' => 'Staff',
            'timezone' => 'America/Toronto', 'is_active' => true, 'is_required_by_default' => true,
        ]);
        $start = $now->addDay();
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(), 'starts_at_utc' => $start,
            'ends_at_utc' => $start->addHour(), 'blocked_starts_at_utc' => $start, 'blocked_ends_at_utc' => $start->addHour(),
            'scheduling_timezone' => 'America/Toronto', 'duration_value' => 1, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        $appointment->resources()->attach($resource->getKey(), ['is_required' => true]);
        $contact = OrganizationContact::create(['organization_id' => $organization->getKey(), 'first_name' => 'R', 'last_name' => 'C', 'email' => 'reminder@example.test']);
        $booking = Booking::create([
            'organization_id' => $organization->getKey(), 'appointment_id' => $appointment->getKey(), 'appointment_type_id' => $type->getKey(),
            'organization_contact_id' => $contact->getKey(), 'reference' => 'REMIND000001', 'status' => 'confirmed', 'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto', 'base_price_minor' => 0, 'price_minor' => 0, 'currency' => 'CAD', 'first_name' => 'R',
            'last_name' => 'C', 'email' => 'reminder@example.test', 'email_normalized' => 'reminder@example.test', 'manage_token_hash' => random_bytes(32),
        ]);
        DB::table('bookings')->where('id', $booking->getKey())->update(['created_at' => $now->subDays(10)->format('Y-m-d H:i:s.u')]);

        $service = app(BookingReminderService::class);
        $this->assertSame(2, $service->sendDue($now));
        $this->assertSame(2, ReminderDelivery::count());
        Notification::assertSentOnDemand(BookingReminderEmail::class);
        Notification::assertSentOnDemand(ResourceReminderEmail::class);
        $this->assertSame(0, $service->sendDue($now));
        $this->assertSame(2, ReminderDelivery::count());
        CarbonImmutable::setTestNow();
    }
}
