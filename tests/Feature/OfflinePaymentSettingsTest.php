<?php

namespace Tests\Feature;

use App\Domain\Payments\OfflineBookingPaymentService;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflinePaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC'));
    }

    public function test_payment_settings_show_offline_setup_for_only_the_current_organization(): void
    {
        [$user, $organization] = $this->context();
        $enabled = $this->type($organization, 'Photo session');
        $disabled = $this->type($organization, 'Consultation');
        $disabled->forceFill(['offline_payment_enabled' => false])->save();
        $inactive = $this->type($organization, 'Old session');
        $inactive->update(['is_active' => false]);
        $incomplete = $this->type($organization, 'Incomplete instructions');
        $incomplete->forceFill(['offline_payment_instructions' => '<p><br></p>'])->save();
        $other = $this->type(Organization::factory()->create(), 'Other tenant secret');

        $this->signIn($user, $organization);
        $this->get(route('payment-settings.edit'))->assertOk()
            ->assertSee('Offline payments / e-Transfer')
            ->assertSee('Photo session')->assertSee('Consultation')->assertSee('Old session')
            ->assertSee('Enabled')->assertSee('Disabled')->assertSee('Needs instructions')
            ->assertSee('Inactive appointment type')->assertSee('1 day')
            ->assertSee(route('appointment-types.offline-payments.edit', $enabled), false)
            ->assertSee(route('booking-payment-review.index'), false)
            ->assertDontSee('Other tenant secret')
            ->assertDontSee(route('appointment-types.offline-payments.edit', $other), false)
            ->assertDontSee('<option value="offline"', false)
            ->assertSee('Stripe')->assertSee('PayPal');
    }

    public function test_payment_settings_offer_setup_when_no_appointment_types_exist(): void
    {
        [$user, $organization] = $this->context();
        $this->signIn($user, $organization);
        $this->get(route('payment-settings.edit'))->assertOk()
            ->assertSee('Offline payments / e-Transfer')
            ->assertSee('No appointment types yet.')
            ->assertSee(route('appointment-types.create'), false);
    }

    public function test_editor_uses_shared_layout_tinymce_and_readable_time_units(): void
    {
        [$user, $organization] = $this->context();
        $type = $this->type($organization);
        $type->forceFill(['offline_payment_window_minutes' => 120,
            'offline_payment_instructions' => "Send the transfer.\nInclude the reference."])->save();
        $this->signIn($user, $organization);
        $this->get(route('appointment-types.offline-payments.edit', $type))->assertOk()
            ->assertSee('class="section-card"', false)
            ->assertSee('class="field"', false)
            ->assertSee('class="inline-check"', false)
            ->assertSee('class="sticky-actions"', false)
            ->assertSee('data-rich-text-editor', false)
            ->assertSee('vendor/tinymce/tinymce.min.js', false)
            ->assertSee('js/rich-text-editor.js', false)
            ->assertSee('value="2"', false)
            ->assertSee('value="hour" selected', false)
            ->assertSee(e("Send the transfer.<br>\nInclude the reference."), false)
            ->assertSee(route('payment-settings.edit').'#offline-payments', false);
    }

    public function test_saving_instructions_retains_formatting_and_filters_unsafe_html(): void
    {
        [$user, $organization] = $this->context();
        $type = $this->type($organization);
        $this->signIn($user, $organization);
        $html = '<p onclick="alert(1)"><strong>Send payment</strong> to finance@example.test.</p>'
            .'<ul><li>Include your reference.</li></ul><script>alert(1)</script><img src=x onerror="alert(1)">';
        $this->put(route('appointment-types.offline-payments.update', $type), $this->payload($html))
            ->assertRedirect()->assertSessionHasNoErrors();
        $expected = '<p><strong>Send payment</strong> to finance@example.test.</p><ul><li>Include your reference.</li></ul>';
        $this->assertSame($expected, $type->fresh()->offline_payment_instructions);
        $this->assertSame(120, (int) $type->fresh()->offline_payment_window_minutes);
        $this->get(route('appointment-types.offline-payments.edit', $type))->assertOk()
            ->assertSee(e($expected), false);
        $this->assertNull($organization->paymentSettings()->first());
    }

    public function test_empty_or_unsafe_only_html_cannot_enable_offline_payment(): void
    {
        [$user, $organization] = $this->context();
        $type = $this->type($organization);
        $type->forceFill(['offline_payment_enabled' => false])->save();
        $this->signIn($user, $organization);
        foreach (['<p><br></p>', '<p>&nbsp;</p>', '<script>alert(1)</script>'] as $html) {
            $this->from(route('appointment-types.offline-payments.edit', $type))
                ->put(route('appointment-types.offline-payments.update', $type), $this->payload($html))
                ->assertRedirect()->assertSessionHasErrors('instructions');
            $this->assertFalse((bool) $type->fresh()->offline_payment_enabled);
        }
    }

    public function test_disabling_offline_payment_allows_empty_instructions(): void
    {
        [$user, $organization] = $this->context();
        $type = $this->type($organization);
        $this->signIn($user, $organization);
        $this->put(route('appointment-types.offline-payments.update', $type),
            array_replace($this->payload('<p><br></p>'), ['offline_payment_enabled' => '0']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse((bool) $type->fresh()->offline_payment_enabled);
        $this->assertNull($type->fresh()->offline_payment_instructions);
    }

    public function test_public_instructions_render_safe_html_and_filter_old_snapshots(): void
    {
        [, $organization] = $this->context();
        $booking = $this->booking($this->type($organization));
        $booking->forceFill([
            'offline_payment_selected_at_utc' => now('UTC'),
            'offline_payment_instructions' => '<p onmouseover="alert(1)"><strong>Pay here</strong></p>'
                .'<ol><li>Use the reference.</li></ol><script>alert(1)</script>',
        ])->save();
        $html = view('public.bookings.offline-payments', ['booking' => $booking, 'manageToken' => 'manage-token'])->render();
        $this->assertStringContainsString('<p><strong>Pay here</strong></p><ol><li>Use the reference.</li></ol>', $html);
        $this->assertStringNotContainsString('&lt;strong&gt;', $html);
        $this->assertStringNotContainsString('onmouseover', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_new_instructions_do_not_rewrite_old_booking_snapshots_or_deadlines(): void
    {
        [$user, $organization] = $this->context();
        $type = $this->type($organization);
        $old = "Transfer to old@example.test.\nUse your reference.";
        $type->forceFill(['offline_payment_instructions' => $old])->save();
        $booking = app(OfflineBookingPaymentService::class)->select($this->booking($type));
        $deadline = $booking->offline_payment_deadline_at_utc;
        $this->signIn($user, $organization);
        $this->put(route('appointment-types.offline-payments.update', $type), $this->payload('<p>New instructions</p>'))
            ->assertRedirect()->assertSessionHasNoErrors();
        $fresh = $booking->fresh();
        $this->assertSame($old, $fresh->offline_payment_instructions);
        $this->assertTrue($deadline->equalTo($fresh->offline_payment_deadline_at_utc));
        $html = view('public.bookings.offline-payments', ['booking' => $fresh, 'manageToken' => 'manage-token'])->render();
        $this->assertStringContainsString("Transfer to old@example.test.<br>\nUse your reference.", $html);
        $this->assertStringNotContainsString('New instructions', $html);
    }

    public function test_settings_cannot_read_or_update_another_organization_type(): void
    {
        [$user, $organization] = $this->context();
        $other = $this->type(Organization::factory()->create());
        $this->signIn($user, $organization);
        $this->get(route('appointment-types.offline-payments.edit', $other))->assertNotFound();
        $this->put(route('appointment-types.offline-payments.update', $other), $this->payload('<p>Changed</p>'))
            ->assertNotFound();
        $this->assertSame('Send your transfer to payments@example.test.', $other->fresh()->offline_payment_instructions);
    }

    private function payload(string $instructions): array
    {
        return ['offline_payment_enabled' => '1', 'window_value' => 2, 'window_unit' => 'hour', 'instructions' => $instructions];
    }

    private function signIn(User $user, Organization $organization): void
    {
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD', 'timezone' => 'America/Toronto']);
        OrganizationMembership::create(['organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => MembershipRole::Owner, 'status' => MembershipStatus::Active]);
        return [$user, $organization];
    }

    private function type(Organization $organization, string $name = 'Appointment A'): AppointmentType
    {
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => $name, 'slug' => 'offline-'.Str::lower(Str::random(10)),
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'start_interval_minutes' => 60, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'fixed', 'fixed_price_minor' => 5000,
            'payment_collection_mode' => 'retainer', 'retainer_type' => 'fixed', 'retainer_amount_minor' => 2000,
            'email_verification_mode' => 'none', 'is_active' => true,
        ]);
        $type->forceFill(['offline_payment_enabled' => true, 'offline_payment_window_minutes' => 1440,
            'offline_payment_instructions' => 'Send your transfer to payments@example.test.'])->save();
        return $type;
    }

    private function booking(AppointmentType $type): Booking
    {
        $start = CarbonImmutable::now('UTC')->addDays(3);
        $appointment = Appointment::create([
            'organization_id' => $type->organization_id, 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $start->addHour(),
            'blocked_starts_at_utc' => $start, 'blocked_ends_at_utc' => $start->addHour(),
            'scheduling_timezone' => 'America/Toronto', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        $contact = OrganizationContact::create(['organization_id' => $type->organization_id,
            'first_name' => 'Offline', 'last_name' => 'Customer', 'email' => 'offline@example.test']);
        return Booking::create([
            'organization_id' => $type->organization_id, 'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(), 'organization_contact_id' => $contact->getKey(),
            'reference' => Str::upper(Str::random(12)), 'status' => 'pending_payment', 'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto', 'base_price_minor' => 5000, 'price_minor' => 5000,
            'currency' => 'CAD', 'payment_collection_mode' => 'retainer', 'initial_payment_due_minor' => 2000,
            'payment_status' => 'unpaid', 'email_verified_at' => now('UTC'), 'expires_at_utc' => now('UTC')->addHour(),
            'first_name' => 'Offline', 'last_name' => 'Customer', 'email' => $contact->email,
            'email_normalized' => $contact->email, 'manage_token_hash' => hash('sha256', 'manage-token', true),
        ]);
    }
}
