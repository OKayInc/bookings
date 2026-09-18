<?php
namespace Tests\Feature;

use App\Domain\Organizations\OrganizationPurgeService;
use App\Domain\Webhooks\WebhookDispatcher;
use App\Domain\Webhooks\WebhookPublisher;
use App\Domain\Webhooks\WebhookTransport;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class M10R2WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function org(string $role = 'owner', string $plan = 'paid'): Organization
    {
        $org = Organization::factory()->create(['plan_tier' => $plan]);
        $user = User::factory()->create();
        OrganizationMembership::create(['organization_id' => $org->getKey(), 'person_id' => $user->person_id, 'role' => $role, 'status' => 'active']);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $org->uuid]);
        return $org;
    }
    private function endpoint(Organization $org, ?array $events = null): WebhookEndpoint
    {
        return WebhookEndpoint::create(['organization_id' => $org->getKey(), 'name' => 'Integration',
            'url' => 'https://receiver.example.com/hooks', 'secret' => str_repeat('a', 64),
            'events' => $events ?? WebhookPublisher::EVENTS, 'version' => 1, 'is_active' => true]);
    }
    private function queued(WebhookEndpoint $endpoint): WebhookDelivery
    {
        app(WebhookPublisher::class)->publish($endpoint->organization, 'webhook.test', ['message' => 'test'], $endpoint);
        return WebhookDelivery::where('webhook_endpoint_id', $endpoint->getKey())->latest()->firstOrFail();
    }
    private function booking(Organization $org): Booking
    {
        $type = AppointmentType::create(['organization_id' => $org->getKey(), 'name' => 'Session', 'slug' => Str::random(12),
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1, 'duration_mode' => 'fixed',
            'duration_unit' => 'minute', 'duration_value' => 60, 'start_interval_minutes' => 60,
            'pricing_mode' => 'free', 'email_verification_mode' => 'none', 'is_active' => true]);
        $start = now('UTC')->addDays(10);
        $appointment = Appointment::create(['organization_id' => $org->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $start->copy()->addHour(), 'blocked_starts_at_utc' => $start,
            'blocked_ends_at_utc' => $start->copy()->addHour(), 'scheduling_timezone' => 'America/Toronto', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled']);
        $email = Str::random(10).'@example.test';
        $contact = OrganizationContact::create(['organization_id' => $org->getKey(), 'first_name' => 'Guest', 'email' => $email]);
        return Booking::create(['organization_id' => $org->getKey(), 'appointment_id' => $appointment->getKey(), 'appointment_type_id' => $type->getKey(),
            'organization_contact_id' => $contact->getKey(), 'reference' => strtoupper(Str::random(12)), 'status' => 'pending_payment', 'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto', 'base_price_minor' => 0, 'price_minor' => 0, 'currency' => 'CAD',
            'first_name' => 'Guest', 'last_name' => 'Test', 'email' => $email, 'email_normalized' => $email, 'manage_token_hash' => random_bytes(32)]);
    }

    public function test_admin_can_create_encrypted_secret_shown_only_once_and_queue_test(): void
    {
        $this->org();
        $response = $this->post(route('webhooks.store'), ['name' => 'CRM', 'url' => 'https://receiver.example.com/hooks', 'events' => ['booking.created']]);
        $response->assertOk()->assertViewHas('newSecret');
        $secret = $response->viewData('newSecret'); $endpoint = WebhookEndpoint::firstOrFail();
        $this->assertSame($secret, $endpoint->secret);
        $this->assertStringNotContainsString($secret, $endpoint->getRawOriginal('secret'));
        $this->get(route('webhooks.index'))->assertOk()->assertDontSee($secret)->assertSee('CRM');
        $this->post(route('webhooks.test', $endpoint))->assertRedirect();
        $this->assertSame('webhook.test', WebhookDelivery::firstOrFail()->event_type);
    }

    public function test_manager_cannot_manage_webhooks_and_free_plan_cannot_create(): void
    {
        $this->org('manager');
        $this->get(route('webhooks.index'))->assertForbidden();
        $this->org('owner', 'free');
        $this->post(route('webhooks.store'), ['name' => 'CRM', 'url' => 'https://receiver.example.com', 'events' => ['booking.created']])->assertForbidden();
    }

    public function test_foreign_endpoint_and_delivery_are_not_accessible(): void
    {
        $foreign = $this->endpoint(Organization::factory()->create(['plan_tier' => 'paid']));
        $delivery = $this->queued($foreign);
        $this->org();
        $this->post(route('webhooks.test', $foreign))->assertNotFound();
        $this->delete(route('webhooks.destroy', $foreign))->assertNotFound();
        $this->get(route('webhooks.deliveries.show', $delivery))->assertNotFound();
        $this->post(route('webhooks.deliveries.retry', $delivery))->assertNotFound();
    }

    public function test_event_selection_and_tenant_scope_and_transaction_rollback(): void
    {
        $org = $this->org(); $endpoint = $this->endpoint($org, ['booking.created']);
        $this->endpoint($org, ['payment.succeeded']);
        $this->endpoint(Organization::factory()->create(['plan_tier' => 'paid']));
        DB::beginTransaction();
        app(WebhookPublisher::class)->publish($org, 'booking.created', ['id' => (string) Str::uuid()]);
        $this->assertSame(1, WebhookDelivery::count());
        $this->assertSame($endpoint->getKey(), WebhookDelivery::first()->webhook_endpoint_id);
        DB::rollBack();
        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_model_transitions_publish_once_without_private_contact_data(): void
    {
        $org = $this->org(); $this->endpoint($org);
        $booking = $this->booking($org);
        $booking->update(['status' => 'confirmed']);
        $booking->update(['status' => 'confirmed']);
        $booking->update(['status' => 'cancelled']);
        $this->assertSame(['booking.created', 'booking.confirmed', 'booking.cancelled'], WebhookDelivery::orderBy('id')->pluck('event_type')->all());
        foreach (WebhookDelivery::all() as $delivery) {
            $data = json_decode($delivery->payload, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($booking->uuid, $data['data']['id']);
            $this->assertArrayNotHasKey('email', $data['data']);
            $this->assertStringNotContainsString($booking->email, $delivery->payload);
        }
    }

    public function test_payments_refunds_and_checkins_publish_real_events(): void
    {
        $org = $this->org(); $this->endpoint($org); $booking = $this->booking($org);
        $payment = PaymentTransaction::create(['organization_id' => $org->getKey(), 'booking_id' => $booking->getKey(),
            'provider' => 'stripe', 'purpose' => 'initial', 'status' => 'pending', 'amount_minor' => 1000, 'currency' => 'CAD',
            'idempotency_key' => (string) Str::uuid(), 'return_token_hash' => random_bytes(32)]);
        $payment->update(['status' => 'succeeded']);
        $payment->update(['status' => 'succeeded']);
        $refund = PaymentRefund::create(['organization_id' => $org->getKey(), 'booking_id' => $booking->getKey(), 'payment_transaction_id' => $payment->getKey(),
            'provider' => 'stripe', 'status' => 'pending', 'amount_minor' => 1000, 'currency' => 'CAD', 'idempotency_key' => (string) Str::uuid()]);
        $refund->update(['status' => 'succeeded']);
        $attendee = $booking->attendees()->create(['first_name' => 'Guest', 'last_name' => 'Test', 'position' => 1]);
        $ticket = Ticket::create(['organization_id' => $org->getKey(), 'booking_id' => $booking->getKey(), 'appointment_id' => $booking->appointment_id,
            'booking_attendee_id' => $attendee->getKey(), 'code' => Str::random(20), 'status' => 'issued']);
        $ticket->update(['status' => 'checked_in', 'checked_in_at_utc' => now('UTC')]);
        $this->assertSame(1, WebhookDelivery::where('event_type', 'payment.succeeded')->count());
        $this->assertSame(1, WebhookDelivery::where('event_type', 'refund.succeeded')->count());
        $this->assertSame(1, WebhookDelivery::where('event_type', 'ticket.checked_in')->count());
    }

    public function test_signature_covers_exact_body_and_success_is_not_delivered_twice(): void
    {
        $org = $this->org(); $endpoint = $this->endpoint($org); $delivery = $this->queued($endpoint);
        $this->mock(WebhookTransport::class, function ($mock) use ($delivery, $endpoint): void {
            $mock->shouldReceive('send')->once()->withArgs(function ($url, $body, $headers) use ($delivery, $endpoint): bool {
                $this->assertSame($delivery->payload, $body);
                $signature = collect($headers)->first(fn ($h) => str_starts_with($h, 'X-Appointment-Signature:'));
                preg_match('/t=(\\d+),v1=([a-f0-9]{64})/', $signature, $matches);
                $this->assertSame(hash_hmac('sha256', $matches[1].'.'.$body, $endpoint->secret), $matches[2]);
                $this->assertContains('X-Appointment-Event-Id: '.$delivery->event_id, $headers);
                return true;
            })->andReturn(204);
        });
        $dispatcher = app(WebhookDispatcher::class);
        $this->assertTrue($dispatcher->deliver($delivery->getKey()));
        $this->assertSame('succeeded', $delivery->fresh()->status);
        $this->assertFalse($dispatcher->deliver($delivery->getKey()));
        $this->assertSame(1, $delivery->attemptsLog()->count());
    }

    public function test_six_failures_are_bounded_and_manual_retry_preserves_id_and_body(): void
    {
        $org = $this->org(); $delivery = $this->queued($this->endpoint($org));
        $this->mock(WebhookTransport::class, fn ($mock) => $mock->shouldReceive('send')->times(6)->andReturn(503));
        for ($i = 1; $i <= 6; $i++) {
            $this->travelTo($delivery->fresh()->available_at);
            app(WebhookDispatcher::class)->deliver($delivery->getKey());
            $this->assertSame($i, $delivery->fresh()->attempts);
        }
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->post(route('webhooks.deliveries.retry', $delivery))->assertRedirect();
        $this->assertSame('pending', $delivery->fresh()->status);
        $this->assertSame($delivery->event_id, $delivery->fresh()->event_id);
        $this->assertSame($delivery->payload, $delivery->fresh()->payload);
        $this->assertSame(0, $delivery->fresh()->cycle_attempts);
        $this->travelBack();
    }

    public function test_future_and_inflight_deliveries_are_not_claimed_and_stale_claim_is_recovered(): void
    {
        $org = $this->org(); $delivery = $this->queued($this->endpoint($org));
        $delivery->update(['available_at' => now('UTC')->addHour()]);
        $this->assertFalse(app(WebhookDispatcher::class)->deliver($delivery->getKey()));
        $delivery->update(['status' => 'delivering', 'claimed_at' => now('UTC'), 'claim_token' => (string) Str::uuid()]);
        $this->assertFalse(app(WebhookDispatcher::class)->deliver($delivery->getKey()));
        $delivery->update(['claimed_at' => now('UTC')->subMinutes(3)]);
        $this->mock(WebhookTransport::class, fn ($mock) => $mock->shouldReceive('send')->once()->andReturn(200));
        $this->assertTrue(app(WebhookDispatcher::class)->deliver($delivery->getKey()));
        $this->assertSame('succeeded', $delivery->fresh()->status);
    }

    public function test_disabled_rotated_and_downgraded_endpoints_do_not_send(): void
    {
        $org = $this->org(); $endpoint = $this->endpoint($org); $delivery = $this->queued($endpoint);
        $this->mock(WebhookTransport::class, fn ($mock) => $mock->shouldNotReceive('send'));
        $this->post(route('webhooks.rotate', $endpoint))->assertOk();
        $this->assertSame('skipped', $delivery->fresh()->status);
        $this->assertFalse(app(WebhookDispatcher::class)->deliver($delivery->getKey()));
        $next = $this->queued($endpoint->fresh());
        $org->update(['plan_tier' => 'free']);
        $this->assertFalse(app(WebhookDispatcher::class)->deliver($next->getKey()));
        $this->assertSame('skipped', $next->fresh()->status);
    }

    public function test_purge_removes_deliveries_but_preserves_endpoint_only_at_configuration_level(): void
    {
        $org = $this->org(); $endpoint = $this->endpoint($org); $this->queued($endpoint);
        app(OrganizationPurgeService::class)->purge($org, 'configuration');
        $this->assertSame(0, WebhookDelivery::count());
        $this->assertSame(1, WebhookEndpoint::count());
        app(OrganizationPurgeService::class)->purge($org, 'all');
        $this->assertSame(0, WebhookEndpoint::count());
    }

    public function test_invalid_urls_and_event_names_are_rejected(): void
    {
        $this->org();
        $this->post(route('webhooks.store'), ['name' => 'Bad', 'url' => 'http://127.0.0.1/', 'events' => ['booking.created']])->assertSessionHasErrors('url');
        $this->post(route('webhooks.store'), ['name' => 'Bad', 'url' => 'https://receiver.example.com/', 'events' => ['unrecognized']])->assertSessionHasErrors('events.0');
    }
}
