<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Enums\AvailabilityScope;
use App\Models\AppointmentType;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_guest_email_verification_advances_booking_without_registration(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00 UTC'));
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Verify Me', 'slug' => 'verify-me',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'start_interval_minutes' => 60, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'free', 'email_verification_mode' => 'before_confirmation', 'is_active' => true,
        ]);
        app(AvailabilityScheduleService::class)->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
        ]);

        $lease = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc(),
            60, 'America/Toronto', 1,
        );
        $result = app(BookingCreationService::class)->createFromHold($lease->token, [
            'first_name' => 'Verify', 'last_name' => 'Client', 'email' => 'verify@example.test',
        ]);

        $this->assertNotNull($result->emailVerificationToken);
        $this->assertSame('pending_email_verification', $result->booking->status->value);

        $response = $this->get(route('public.bookings.verify', [$result->booking, $result->emailVerificationToken]));
        $response->assertRedirect();

        $booking = $result->booking->fresh(['contact']);
        $this->assertNotNull($booking->email_verified_at);
        $this->assertNotNull($booking->contact->email_verified_at);
        $this->assertSame('confirmed', $booking->status->value);
    }
}
