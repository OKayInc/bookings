<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\CouponOffer;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CouponPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_and_offer_pages_are_independent_ordered_and_tenant_scoped(): void
    {
        $organization = $this->signIn();
        $other = Organization::factory()->create();
        $this->coupon($other, 'OTHER-SECRET');
        $this->offer($other, 'Other offer');
        $expected = [];
        $created = now()->startOfDay();
        foreach (range(1, 53) as $i) {
            $coupon = $this->coupon($organization, 'CODE-'.$i);
            $coupon->forceFill(['created_at' => $created])->save();
            $expected[] = $coupon->uuid;
        }
        foreach (range(1, 27) as $i) {
            $this->offer($organization, 'Offer '.$i)->forceFill(['created_at' => $created])->save();
        }
        rsort($expected);
        $hydrated = 0;
        Coupon::retrieved(function () use (&$hydrated): void { $hydrated++; });
        $seen = [];
        foreach ([1 => 25, 2 => 25, 3 => 3] as $page => $count) {
            $hydrated = 0;
            $response = $this->get(route('coupons.index', ['coupons_page' => $page, 'offers_page' => 2]))
                ->assertOk()->assertDontSee('OTHER-SECRET')->assertDontSee('Other offer');
            $coupons = $response->viewData('coupons');
            $offers = $response->viewData('offers');
            $this->assertCount($count, $coupons);
            $this->assertSame($count, $hydrated);
            $this->assertSame(53, $coupons->total());
            $this->assertCount(2, $offers);
            $this->assertSame(27, $offers->total());
            $this->assertStringContainsString('offers_page=2', $coupons->url(1));
            $this->assertStringContainsString('coupons_page='.$page, $offers->url(1));
            $this->assertStringEndsWith('#issued-coupons', $coupons->url(1));
            $this->assertStringEndsWith('#coupon-offers', $offers->url(1));
            $seen = [...$seen, ...$coupons->pluck('uuid')->all()];
        }
        $this->assertSame($expected, $seen);
    }

    public function test_coupon_page_preserves_destroy_and_retry_controls_without_loading_payment_or_redemption_history(): void
    {
        $organization = $this->signIn();
        $unused = $this->coupon($organization, 'UNUSED');
        $redeemed = $this->coupon($organization, 'PARTLY-USED');
        $type = AppointmentType::create(['organization_id' => $organization->getKey(), 'name' => 'Gift service',
            'slug' => 'gift-service', 'visibility' => 'public', 'pricing_mode' => 'free', 'is_active' => true]);
        $start = now()->addDay();
        $appointment = Appointment::create(['organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $start->copy()->addHour(), 'blocked_starts_at_utc' => $start,
            'blocked_ends_at_utc' => $start->copy()->addHour(), 'scheduling_timezone' => 'UTC', 'duration_value' => 60,
            'capacity' => 1, 'status' => 'scheduled']);
        $contact = OrganizationContact::create(['organization_id' => $organization->getKey(), 'email' => 'gift@example.test']);
        $booking = Booking::create(['organization_id' => $organization->getKey(), 'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(), 'organization_contact_id' => $contact->getKey(), 'reference' => 'GIFTPAGE0001',
            'status' => 'confirmed', 'attendee_count' => 1, 'booking_timezone' => 'UTC', 'base_price_minor' => 0,
            'price_minor' => 0, 'currency' => 'CAD', 'first_name' => 'Gift', 'last_name' => 'Client',
            'email' => 'gift@example.test', 'email_normalized' => 'gift@example.test', 'manage_token_hash' => random_bytes(32)]);
        $redeemed->redemptions()->create(['organization_id' => $organization->getKey(), 'booking_id' => $booking->getKey(),
            'discount_minor' => 1000, 'redeemed_at_utc' => now()]);
        $payment = PaymentTransaction::create(['organization_id' => $organization->getKey(), 'coupon_id' => $unused->getKey(),
            'provider' => 'stripe', 'purpose' => 'coupon_purchase', 'status' => 'succeeded', 'amount_minor' => 5000,
            'currency' => 'CAD', 'idempotency_key' => (string) Str::uuid(), 'return_token_hash' => random_bytes(32)]);
        $refunds = [];
        foreach (['pending', 'failed', 'succeeded'] as $status) {
            $refunds[$status] = PaymentRefund::create(['organization_id' => $organization->getKey(), 'coupon_id' => $unused->getKey(),
                'payment_transaction_id' => $payment->getKey(), 'provider' => 'stripe', 'status' => $status, 'amount_minor' => 100,
                'currency' => 'CAD', 'idempotency_key' => (string) Str::uuid(), 'reason' => 'Pagination test']);
        }
        $queries = [];
        DB::listen(function ($query) use (&$queries): void { $queries[] = $query->sql; });
        $response = $this->get(route('coupons.index'))->assertOk();
        $loaded = $response->viewData('coupons')->keyBy('uuid');
        $this->assertFalse($loaded[$unused->uuid]->redemptions_exists);
        $this->assertTrue($loaded[$redeemed->uuid]->redemptions_exists);
        foreach ($loaded as $coupon) {
            foreach (['offer', 'redemptions', 'payments'] as $relation) {
                $this->assertFalse($coupon->relationLoaded($relation));
            }
        }
        $this->assertSame([], array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'from `payment_transactions`'))));
        $this->assertCount(2, $loaded[$unused->uuid]->refunds);
        $response->assertSee('action="'.route('coupons.destroy', $unused).'"', false)
            ->assertDontSee('action="'.route('coupons.destroy', $redeemed).'"', false)
            ->assertSee(route('coupons.refunds.retry', [$unused, $refunds['pending']]), false)
            ->assertSee(route('coupons.refunds.retry', [$unused, $refunds['failed']]), false)
            ->assertDontSee(route('coupons.refunds.retry', [$unused, $refunds['succeeded']]), false);
        $this->delete(route('coupons.destroy', $redeemed), ['reason' => 'Must reject used coupon'])
            ->assertSessionHas('error');
        $this->assertNotSame('destroyed', $redeemed->fresh()->status->value);
    }

    public function test_empty_coupon_page_and_read_only_member_authorization_are_preserved(): void
    {
        $organization = $this->signIn();
        $this->get(route('coupons.index'))->assertOk()->assertSee('No offers yet.')->assertSee('No codes issued yet.');
        $organization->memberships()->update(['role' => 'employee']);
        $this->get(route('coupons.index'))->assertForbidden();
    }

    private function signIn(): Organization
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD']);
        OrganizationMembership::create(['organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => 'owner', 'status' => 'active']);
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);

        return $organization;
    }

    private function coupon(Organization $organization, string $code): Coupon
    {
        $token = Str::random(64);

        return Coupon::create(['organization_id' => $organization->getKey(), 'source' => 'manual', 'status' => 'active',
            'code' => $code, 'code_hash' => hash('sha256', $code, true), 'view_token' => $token,
            'view_token_hash' => hash('sha256', $token, true), 'discount_type' => 'fixed', 'amount_minor' => 5000,
            'remaining_amount_minor' => 4000, 'applies_to_all' => true, 'delivery_method' => 'print',
            'password_hash' => 'unused-test-password-hash']);
    }

    private function offer(Organization $organization, string $name): CouponOffer
    {
        return CouponOffer::create(['organization_id' => $organization->getKey(), 'name' => $name, 'discount_type' => 'fixed',
            'amount_minor' => 5000, 'purchase_price_minor' => 5000, 'applies_to_all' => true, 'is_public' => true, 'is_active' => true]);
    }
}
