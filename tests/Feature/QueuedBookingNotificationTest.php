<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\Resource;
use App\Notifications\BookingAccessEmail;
use App\Notifications\BookingResourceAssignedEmail;
use App\Notifications\VerifyBookingEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\SendQueuedNotifications;
use ReflectionMethod;
use Tests\TestCase;

class QueuedBookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_survive_encrypted_queue_round_trip_with_binary_ids_and_private_links(): void
    {
        $organization = Organization::factory()->create();
        $type = AppointmentType::create(['organization_id' => $organization->getKey(), 'name' => 'Session',
            'slug' => 'session', 'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60, 'pricing_mode' => 'free', 'is_active' => true]);
        $start = now('UTC')->addDay();
        $appointment = Appointment::create(['organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $start->copy()->addHour(),
            'blocked_starts_at_utc' => $start, 'blocked_ends_at_utc' => $start->copy()->addHour(),
            'scheduling_timezone' => 'UTC', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled']);
        $contact = OrganizationContact::factory()->create(['organization_id' => $organization->getKey()]);
        $booking = Booking::create(['organization_id' => $organization->getKey(), 'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(), 'organization_contact_id' => $contact->getKey(),
            'reference' => 'QUEUE000001', 'status' => 'confirmed', 'attendee_count' => 1,
            'booking_timezone' => 'UTC', 'price_minor' => 0, 'currency' => 'CAD', 'first_name' => 'Client',
            'last_name' => 'Example', 'email' => 'client@example.test', 'email_normalized' => 'client@example.test',
            'manage_token_hash' => hash('sha256', 'private-token', true)]);
        $resource = Resource::create(['organization_id' => $organization->getKey(), 'type' => 'person',
            'name' => 'Assigned host', 'is_active' => true, 'is_required_by_default' => true]);
        $notifiable = (new AnonymousNotifiable)->route('mail', 'client@example.test');
        $queue = app('queue')->connection('sync');
        $notifications = [new BookingAccessEmail($booking, 'private-token'), new VerifyBookingEmail($booking, 'private-token'),
            new BookingResourceAssignedEmail($booking, [$resource], 'Staff', 'UTC')];
        foreach ($notifications as $notification) {
            $job = new SendQueuedNotifications($notifiable, $notification, ['mail']);
            $json = (new ReflectionMethod($queue, 'createPayload'))->invoke($queue, $job, 'notifications');
            $this->assertStringNotContainsString('private-token', $json);
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $restored = unserialize(app('encrypter')->decrypt($payload['data']['command']));
            $this->assertTrue($restored->afterCommit);
            $mail = $restored->notification->toMail($notifiable);
            $this->assertStringContainsString($booking->uuid, $mail->actionUrl);
            if ($notification instanceof BookingResourceAssignedEmail) {
                $this->assertStringContainsString('Assigned host', implode(' ', $mail->introLines));
            } else {
                $this->assertStringContainsString('private-token', $mail->actionUrl);
            }
        }
    }
}
