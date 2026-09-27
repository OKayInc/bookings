<?php

namespace Tests\Feature;

use App\Domain\Calendars\CalendarSyncService;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingAnswer;
use App\Models\CalendarConnection;
use App\Models\ExternalCalendar;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_appointment_creates_event_on_configured_write_calendar(): void
    {
        $organization = Organization::factory()->create();
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Synced Session', 'slug' => 'synced-session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'pricing_mode' => 'free', 'is_active' => true,
        ]);
        $resource = Resource::create(['organization_id' => $organization->getKey(), 'type' => 'person', 'name' => 'Staff', 'is_active' => true, 'is_required_by_default' => true]);
        $type->resources()->attach($resource->getKey(), ['is_required' => true, 'requirement_mode' => 'inherit']);
        $connection = CalendarConnection::create([
            'organization_id' => $organization->getKey(), 'resource_id' => $resource->getKey(), 'provider' => 'google',
            'access_token' => 'token', 'refresh_token' => 'refresh', 'token_expires_at_utc' => now('UTC')->addHour(), 'status' => 'active',
        ]);
        $calendar = ExternalCalendar::create([
            'calendar_connection_id' => $connection->getKey(), 'external_id' => 'primary@example.test',
            'external_id_hash' => hash('sha256', 'primary@example.test', true), 'name' => 'Primary',
            'can_write' => true, 'is_primary' => true, 'is_active' => true,
        ]);
        $type->externalCalendars()->attach($calendar->getKey(), ['check_availability' => true, 'create_event' => true]);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => now('UTC')->addDay(), 'ends_at_utc' => now('UTC')->addDay()->addHour(),
            'blocked_starts_at_utc' => now('UTC')->addDay(), 'blocked_ends_at_utc' => now('UTC')->addDay()->addHour(),
            'scheduling_timezone' => 'America/Toronto', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        $appointment->resources()->attach($resource->getKey(), ['is_required' => true]);

        Http::fake(['https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['id' => 'event-123', 'etag' => 'etag-1'], 200)]);
        app(CalendarSyncService::class)->syncAppointment($appointment);

        $this->assertDatabaseCount('appointment_external_events', 1);
        $this->assertSame('event-123', $appointment->externalEvents()->firstOrFail()->provider_event_id);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request['description'], route('appointments.show', $appointment)));
    }

    public function test_free_event_links_to_staff_page_and_paid_event_includes_answers_and_address(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => 'owner', 'status' => 'active',
        ]);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Portrait session', 'slug' => 'portrait-session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'pricing_mode' => 'free', 'is_active' => true,
        ]);
        $start = now('UTC')->addDay();
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $start->copy()->addHour(),
            'blocked_starts_at_utc' => $start, 'blocked_ends_at_utc' => $start->copy()->addHour(),
            'scheduling_timezone' => 'America/Toronto', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        $contact = OrganizationContact::factory()->create(['organization_id' => $organization->getKey()]);
        $booking = Booking::create([
            'organization_id' => $organization->getKey(), 'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(), 'organization_contact_id' => $contact->getKey(),
            'reference' => 'CALTEST00001', 'status' => 'confirmed', 'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto', 'price_minor' => 0, 'currency' => 'CAD',
            'first_name' => 'Client', 'last_name' => 'Example', 'email' => 'client@example.test',
            'email_normalized' => 'client@example.test', 'manage_token_hash' => hash('sha256', 'calendar-test', true),
        ]);
        BookingAnswer::create([
            'booking_id' => $booking->getKey(), 'question_uuid_snapshot' => '00000000-0000-7000-8000-000000000001',
            'question_label' => 'What should we photograph?', 'question_type' => 'text',
            'value_json' => ['value' => 'Family portrait'], 'position' => 1,
        ]);
        BookingAnswer::create([
            'booking_id' => $booking->getKey(), 'question_uuid_snapshot' => '00000000-0000-7000-8000-000000000002',
            'question_label' => 'Address', 'question_type' => 'address',
            'value_json' => ['value' => '1 Main Street'], 'normalized_json' => ['formatted_address' => '1 Main Street, Cornwall, ON'], 'position' => 2,
        ]);
        BookingAnswer::create([
            'booking_id' => $booking->getKey(), 'question_uuid_snapshot' => '00000000-0000-7000-8000-000000000003',
            'question_label' => 'Package', 'question_type' => 'select',
            'value_json' => ['value' => ['uuid' => 'option-1', 'value' => 'family', 'label' => 'Family package']], 'position' => 3,
        ]);
        BookingAnswer::create([
            'booking_id' => $booking->getKey(), 'question_uuid_snapshot' => '00000000-0000-7000-8000-000000000004',
            'question_label' => 'Notes', 'question_type' => 'textarea',
            'value_json' => ['value' => '<p>Bring <strong>props</strong></p>'], 'position' => 4,
        ]);

        $sync = app(CalendarSyncService::class);
        $payload = (new \ReflectionMethod($sync, 'eventPayload'))->invoke($sync, $appointment->fresh(['appointmentType.organization', 'bookings.answers.files']), 'google');
        $this->assertStringContainsString(route('appointments.show', $appointment), $payload['description']);
        $this->assertStringNotContainsString('Family portrait', $payload['description']);
        $this->assertSame('1 Main Street, Cornwall, ON', $payload['location']);

        config()->set('plans.adsense.enabled', true);
        config()->set('plans.adsense.client', 'ca-pub-123');
        config()->set('plans.adsense.slot', '456');
        $this->get(route('appointments.show', $appointment))->assertRedirect(route('login'));
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
        $this->get(route('appointments.show', $appointment))->assertOk()
            ->assertSee('Advertisement')->assertSee(route('bookings.show', $booking), false)
            ->assertDontSee('client@example.test')->assertDontSee('Family portrait');
        $otherUser = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $otherUser->person_id,
            'role' => 'employee', 'status' => 'active',
        ]);
        $this->actingAs($otherUser)->withSession(['active_organization_uuid' => $organization->uuid]);
        $this->get(route('appointments.show', $appointment))->assertForbidden();
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);

        $organization->update(['plan_tier' => 'paid']);
        $paid = (new \ReflectionMethod($sync, 'eventPayload'))->invoke($sync, $appointment->fresh(['appointmentType.organization', 'bookings.answers.files']), 'google');
        $this->assertStringContainsString('Booking CALTEST00001: Client Example', $paid['description']);
        $this->assertStringContainsString('What should we photograph?: Family portrait', $paid['description']);
        $this->assertStringContainsString('Package: Family package', $paid['description']);
        $this->assertStringContainsString('Notes: Bring props', $paid['description']);
        $this->assertStringNotContainsString('<strong>', $paid['description']);
        $this->assertStringNotContainsString('View appointment details:', $paid['description']);
        $this->assertSame('1 Main Street, Cornwall, ON', $paid['location']);
        $outlook = (new \ReflectionMethod($sync, 'eventPayload'))->invoke($sync, $appointment->fresh(['appointmentType.organization', 'bookings.answers.files']), 'microsoft');
        $this->assertSame($paid['description'], $outlook['body']['content']);
        $this->assertSame(['displayName' => '1 Main Street, Cornwall, ON'], $outlook['location']);
        $appointment->update(['event_location' => 'Current event venue']);
        $type->update(['event_location' => 'Old type venue']);
        $venue = (new \ReflectionMethod($sync, 'eventPayload'))->invoke($sync, $appointment->fresh(['appointmentType.organization', 'bookings.answers.files']), 'google');
        $this->assertSame('Current event venue', $venue['location']);
        $this->get(route('appointments.show', $appointment))->assertOk()->assertDontSee('Advertisement');
    }
}
