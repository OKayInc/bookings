<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Enums\AvailabilityScope;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\User;
use App\Notifications\BookingAccessEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GuestBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 14:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_guest_books_without_creating_backend_user(): void
    {
        Notification::fake();
        [$organization, $type] = $this->availableType();

        $slots = $this->getJson(route('public.booking.slots', $type).'?'.http_build_query([
            'access_mode' => 'direct',
            'timezone' => 'America/Toronto',
            'date' => '2026-08-31',
            'duration_value' => 60,
            'attendee_count' => 1,
        ]));

        $slots->assertOk();
        $slots->assertJsonPath('slots.0.client_label', '9:00 AM – 10:00 AM');
        $slots->assertJsonPath('slots.0.organization_label', '9:00 AM – 10:00 AM');
        $start = $slots->json('slots.0.starts_at_utc');
        $this->assertNotEmpty($start);

        $hold = $this->postJson(route('public.booking.holds.store', $type), [
            'access_mode' => 'direct',
            'timezone' => 'America/Toronto',
            'starts_at_utc' => $start,
            'duration_value' => 60,
            'attendee_count' => 1,
        ])->assertOk();

        $continue = $hold->json('continue_url');
        $token = basename((string) parse_url($continue, PHP_URL_PATH));

        $this->get($continue)->assertOk()->assertSee('Complete your booking');
        $response = $this->post(route('public.booking-holds.store', $token), [
            'first_name' => 'Guest',
            'last_name' => 'Client',
            'email' => 'guest@example.test',
        ]);

        $booking = Booking::firstOrFail();
        $response->assertRedirect(route('public.bookings.received', $booking->reference));
        $this->assertSame(0, User::count());
        $this->assertSame(1, OrganizationContact::count());
        $this->assertSame(1, Appointment::count());
        $this->assertSame('confirmed', $booking->status->value);
        Notification::assertSentOnDemand(BookingAccessEmail::class);
    }

    private function availableType(): array
    {
        $organization = Organization::factory()->create([
            'slug' => 'demo',
            'timezone' => 'America/Toronto',
            'currency' => 'CAD',
        ]);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Guest Session',
            'slug' => 'guest-session',
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

        return [$organization, $type->fresh(['organization', 'resources'])];
    }
}
