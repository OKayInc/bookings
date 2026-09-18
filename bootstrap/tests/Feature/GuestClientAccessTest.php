<?php

namespace Tests\Feature;

use App\Models\AppointmentType;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestClientAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_appointment_page_is_guest_facing_and_does_not_prompt_for_backend_registration(): void
    {
        $organization = Organization::factory()->create(['slug' => 'guest-demo']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Guest Session',
            'slug' => 'guest-session',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'hour',
            'duration_value' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'email_verification_mode' => 'before_confirmation',
            'is_active' => true,
        ]);

        $response = $this->get(route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]));

        $response->assertOk()
            ->assertSee('No account or registration is required for clients.')
            ->assertDontSee('Register')
            ->assertDontSee('Log in');
    }
}
