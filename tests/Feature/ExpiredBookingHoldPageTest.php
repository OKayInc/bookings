<?php

namespace Tests\Feature;

use App\Enums\BookingHoldStatus;
use App\Models\AppointmentType;
use App\Models\BookingHold;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiredBookingHoldPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_hold_displays_a_public_error_page_with_a_link_to_choose_again(): void
    {
        [$hold, $token] = $this->expiredHold();
        $expectedReturnUrl = route('public.appointment-types.show', [
            'organizationSlug' => $hold->organization->slug,
            'appointmentSlug' => $hold->appointmentType->slug,
        ]).'#booking-scheduler';

        $this->get(route('public.booking-holds.edit', $token))
            ->assertStatus(410)
            ->assertViewIs('errors.booking-hold-expired')
            ->assertSee('Your selected time has expired')
            ->assertSee('choose an available date and time again')
            ->assertSee('id="choose-another-time"', false)
            ->assertSee('href="'.$expectedReturnUrl.'"', false)
            ->assertDontSee('vendor/laravel/framework');
    }

    public function test_expired_hold_returns_structured_json_to_quote_requests(): void
    {
        [$hold, $token] = $this->expiredHold();
        $expectedReturnUrl = route('public.appointment-types.show', [
            'organizationSlug' => $hold->organization->slug,
            'appointmentSlug' => $hold->appointmentType->slug,
        ]).'#booking-scheduler';

        $this->postJson(route('public.booking-holds.quote', $token))
            ->assertStatus(410)
            ->assertJson([
                'message' => 'This booking hold has expired.',
                'return_url' => $expectedReturnUrl,
            ]);
    }

    private function expiredHold(): array
    {
        $organization = Organization::factory()->create(['slug' => 'expired-hold-demo']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Portrait Session',
            'slug' => 'portrait-session',
            'visibility' => 'public',
            'is_active' => true,
        ]);
        $token = str_repeat('x', 64);
        $hold = BookingHold::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $type->getKey(),
            'token_hash' => hash('sha256', $token, true),
            'starts_at_utc' => now('UTC')->addDay(),
            'ends_at_utc' => now('UTC')->addDay()->addHour(),
            'blocked_starts_at_utc' => now('UTC')->addDay(),
            'blocked_ends_at_utc' => now('UTC')->addDay()->addHour(),
            'booking_timezone' => 'America/Toronto',
            'duration_value' => 60,
            'attendee_count' => 1,
            'status' => BookingHoldStatus::Active,
            'expires_at_utc' => now('UTC')->subMinute(),
        ]);

        return [$hold->fresh(['organization', 'appointmentType']), $token];
    }
}
