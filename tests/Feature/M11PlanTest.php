<?php

namespace Tests\Feature;

use App\Domain\Api\ApiKeyService;
use App\Domain\Plans\PlanAddonService;
use App\Domain\Plans\PlanBillingService;
use App\Domain\Plans\PlanEntitlementService;
use App\Domain\Plans\PlanLimitException;
use App\Domain\Plans\PlanLimitService;
use App\Domain\Plans\PlanPromotionService;
use App\Domain\Plans\PlanStripeGateway;
use App\Enums\PlanAddon;
use App\Enums\PlanLevel;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationPlanAddon;
use App\Models\OrganizationPlanSubscription;
use App\Models\PlanPromotionCode;
use App\Models\PlanWebhookEvent;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class M11PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_entitlement_precedence_and_cancelled_subscription_authority(): void
    {
        $organization = Organization::factory()->create(['plan_tier' => 'paid']);
        $entitlements = app(PlanEntitlementService::class);
        $this->assertSame(PlanLevel::Business, $entitlements->for($organization)->level);
        $this->assertSame('legacy_paid', $entitlements->for($organization)->source);
        OrganizationPlanAddon::create([
            'organization_id' => $organization->getKey(),
            'addon' => PlanAddon::Members->value,
            'quantity' => 99,
        ]);
        $this->assertSame(10, app(PlanLimitService::class)->limit($organization, 'members'));

        OrganizationPlanSubscription::create([
            'organization_id' => $organization->getKey(),
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_cancelled_m11',
            'status' => 'cancelled',
            'billing_interval' => 'monthly',
        ]);
        $this->assertSame(PlanLevel::Free, $entitlements->for($organization->refresh())->level);

        app(PlanBillingService::class)->processStripeEvent([
            'id' => 'evt_m11_late_paid_invoice',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_m11_late',
                'subscription' => 'sub_cancelled_m11',
            ]],
        ]);
        $this->assertSame('cancelled', $organization->planSubscription()->firstOrFail()->status);
        $this->assertSame(PlanLevel::Free, $entitlements->for($organization->refresh())->level);

        $actor = User::factory()->create();
        app(PlanPromotionService::class)->grant($organization, $actor, 'Founding customer');
        $this->assertSame(PlanLevel::Complimentary, $entitlements->for($organization->refresh())->level);
        $this->assertSame('owner', $entitlements->for($organization)->source);

        config(['plans.complimentary_organization_ids' => [strtolower($organization->uuid)]]);
        $this->assertSame('environment', $entitlements->for($organization)->source);
    }

    public function test_free_member_person_resource_and_question_caps_are_distinct(): void
    {
        config([
            'plans.limits.free.members' => 2,
            'plans.limits.free.resources' => 3,
            'plans.limits.free.person_resources' => 1,
            'plans.limits.free.questions' => 5,
        ]);
        $organization = Organization::factory()->create(['plan_tier' => 'free']);
        foreach (range(1, 2) as $index) {
            $user = User::factory()->create();
            OrganizationMembership::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $user->person_id,
                'role' => $index === 1 ? 'owner' : 'employee',
                'status' => 'active',
            ]);
        }
        $limits = app(PlanLimitService::class);
        $this->assertPlanLimit(fn () => $limits->assertCanInviteMember($organization), '2 members');

        Resource::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Owner',
            'type' => 'person',
            'is_active' => true,
            'is_required_by_default' => true,
        ]);
        $this->assertPlanLimit(fn () => $limits->assertCanActivateResource($organization, true), '1 active person resources');
        // Non-person capacity remains independently available until the total of three.
        $limits->assertCanActivateResource($organization, false);

        $type = $this->appointmentType($organization, 'Question cap');
        foreach (range(1, 5) as $position) {
            AppointmentQuestion::create([
                'appointment_type_id' => $type->getKey(),
                'type' => 'text',
                'label' => 'Question '.$position,
                'is_required' => false,
                'is_active' => true,
                'position' => $position,
            ]);
        }
        $this->assertPlanLimit(fn () => $limits->assertCanAddQuestion($organization), '5 questionnaire questions');
    }

    public function test_complimentary_grant_enables_api_and_promotion_plaintext_is_not_stored(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['plan_tier' => 'free']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $promotion = app(PlanPromotionService::class)->create($owner, 1);
        $this->assertStringNotContainsString($promotion['plaintext'], (string) $promotion['code']->getRawOriginal('code_hash'));
        app(PlanPromotionService::class)->redeem($organization, $user, strtolower($promotion['plaintext']));
        $this->assertSame(PlanLevel::Complimentary, app(PlanEntitlementService::class)->for($organization)->level);
        $this->assertSame(1, PlanPromotionCode::firstOrFail()->redemption_count);

        $keys = app(ApiKeyService::class);
        $headers = [
            'X-CLIENT-API-KEY' => $keys->regenerate($user),
            'X-ORGANIZATION-API-KEY' => $keys->regenerate($organization),
        ];
        $this->getJson('/api/v1/me', $headers)
            ->assertOk()
            ->assertJsonPath('data.organization_uuid', $organization->uuid);
    }

    public function test_only_configured_platform_owner_can_use_plan_administration(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        config(['plans.platform_owner_user_id' => strtolower($owner->uuid)]);

        $this->actingAs($other)->get(route('platform.plans.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('platform.plans.index'))->assertOk()->assertSee('Platform plan administration');
    }

    public function test_addon_reduction_remains_until_period_end_then_reduces_capacity(): void
    {
        config([
            'plans.addons.members.monthly_price_id' => 'price_member',
            'plans.stripe.secret_key' => 'sk_test_m11',
        ]);
        $organization = Organization::factory()->create(['plan_tier' => 'paid']);
        $actor = User::factory()->create();
        OrganizationPlanSubscription::create([
            'organization_id' => $organization->getKey(),
            'provider' => 'stripe',
            'provider_customer_id' => 'cus_m11',
            'provider_subscription_id' => 'sub_m11',
            'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_ends_at_utc' => now('UTC')->addMonth(),
        ]);
        OrganizationPlanAddon::create([
            'organization_id' => $organization->getKey(),
            'addon' => PlanAddon::Members->value,
            'quantity' => 2,
            'provider_item_id' => 'si_member',
        ]);
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response(['items' => ['data' => [[
                    'id' => 'si_member',
                    'price' => ['id' => 'price_member'],
                ]]]]);
            }

            return Http::response(['id' => 'si_member']);
        });

        $addons = app(PlanAddonService::class);
        $addons->update($organization, $actor, [PlanAddon::Members->value => 1]);
        $record = OrganizationPlanAddon::firstOrFail();
        $this->assertSame(2, $record->quantity);
        $this->assertSame(1, $record->pending_quantity);
        $this->assertSame(2, $record->effectiveQuantity());

        $this->travelTo($record->pending_effective_at_utc->addSecond());
        $this->assertTrue($addons->applyDue($organization->refresh()));
        $record->refresh();
        $this->assertSame(1, $record->quantity);
        $this->assertNull($record->pending_quantity);
        $this->assertSame(11, app(PlanLimitService::class)->limit($organization, 'members'));
        $this->travelBack();
    }

    public function test_signed_stripe_events_are_idempotent(): void
    {
        config(['plans.stripe.webhook_secret' => 'whsec_m11']);
        $organization = Organization::factory()->create(['plan_tier' => 'free']);
        $event = [
            'id' => 'evt_m11_checkout',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_m11',
                'customer' => 'cus_m11',
                'subscription' => 'sub_m11',
                'metadata' => [
                    'organization_uuid' => $organization->uuid,
                    'billing_interval' => 'monthly',
                ],
            ]],
        ];
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = now('UTC')->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_m11');
        $verified = app(PlanStripeGateway::class)->verifyWebhook($payload, "t={$timestamp},v1={$signature}");

        app(PlanBillingService::class)->processStripeEvent($verified);
        app(PlanBillingService::class)->processStripeEvent($verified);
        $this->assertSame(1, PlanWebhookEvent::count());
        $this->assertSame('incomplete', $organization->planSubscription()->firstOrFail()->status);
        $this->assertSame('free', $organization->refresh()->plan_tier->value);

        $subscriptionEvent = [
            'id' => 'evt_m11_subscription_created',
            'type' => 'customer.subscription.created',
            'data' => ['object' => [
                'id' => 'sub_m11',
                'customer' => 'cus_m11',
                'status' => 'trialing',
                'trial_end' => now('UTC')->addDays(14)->timestamp,
                'current_period_end' => now('UTC')->addMonth()->timestamp,
                'cancel_at_period_end' => false,
                'metadata' => [
                    'organization_uuid' => $organization->uuid,
                    'billing_interval' => 'monthly',
                ],
            ]],
        ];
        app(PlanBillingService::class)->processStripeEvent($subscriptionEvent);
        app(PlanBillingService::class)->processStripeEvent($subscriptionEvent);
        $this->assertSame(2, PlanWebhookEvent::count());
        $this->assertSame('trialing', $organization->planSubscription()->firstOrFail()->status);
        $this->assertSame('paid', $organization->refresh()->plan_tier->value);

        app(PlanBillingService::class)->processStripeEvent([
            'id' => 'evt_m11_stale_subscription_deleted',
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => [
                'id' => 'sub_older_m11',
                'customer' => 'cus_m11',
                'status' => 'canceled',
                'metadata' => ['organization_uuid' => $organization->uuid],
            ]],
        ]);
        $this->assertSame(3, PlanWebhookEvent::count());
        $this->assertSame('trialing', $organization->planSubscription()->firstOrFail()->status);
        $this->assertSame('paid', $organization->refresh()->plan_tier->value);
    }

    public function test_zero_owned_organization_cap_is_not_treated_as_unlimited(): void
    {
        config(['plans.limits.free.owned_organizations' => 0]);
        $user = User::factory()->create();

        $this->assertPlanLimit(
            fn () => app(PlanLimitService::class)->assertCanCreateFreeOrganization($user),
            '0 owned organizations',
        );
    }

    public function test_ads_and_branding_are_limited_to_eligible_free_public_pages(): void
    {
        config([
            'plans.adsense.enabled' => true,
            'plans.adsense.client' => 'ca-pub-123456789',
            'plans.adsense.slot' => '1234567890',
        ]);
        $free = Organization::factory()->create(['plan_tier' => 'free', 'hide_platform_branding' => true]);
        $public = $this->appointmentType($free, 'Public session');
        $password = $this->appointmentType($free, 'Private session', [
            'visibility' => 'password_protected',
            'access_password' => Hash::make('secret'),
        ]);
        $unlisted = $this->appointmentType($free, 'Unlisted session', [
            'visibility' => 'unlisted',
            'public_token' => Str::random(48),
        ]);

        $this->get(route('public.appointment-types.index', $free->slug))
            ->assertOk()->assertSee('pagead2.googlesyndication.com', false)->assertSee('Powered by Appointment.to');
        $this->get(route('public.appointment-types.show', [$free->slug, $public->slug]))
            ->assertOk()->assertSee('pagead2.googlesyndication.com', false);
        $this->get(route('public.appointment-types.show', [$free->slug, $password->slug]))
            ->assertOk()->assertDontSee('pagead2.googlesyndication.com', false);
        $this->get(route('public.appointment-types.unlisted', [$free->slug, $unlisted->public_token]))
            ->assertOk()->assertDontSee('pagead2.googlesyndication.com', false);

        $business = Organization::factory()->create(['plan_tier' => 'paid', 'hide_platform_branding' => true]);
        $this->appointmentType($business, 'Business session');
        $this->get(route('public.appointment-types.index', $business->slug))
            ->assertOk()
            ->assertDontSee('pagead2.googlesyndication.com', false)
            ->assertDontSee('Powered by Appointment.to');
    }

    private function appointmentType(Organization $organization, string $name, array $overrides = []): AppointmentType
    {
        return AppointmentType::create(array_merge([
            'organization_id' => $organization->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'start_interval_minutes' => 60,
            'pricing_mode' => 'free',
            'email_verification_mode' => 'none',
            'is_active' => true,
        ], $overrides));
    }

    private function assertPlanLimit(callable $callback, string $messageFragment): void
    {
        try {
            $callback();
            $this->fail('Expected a plan limit exception.');
        } catch (PlanLimitException $exception) {
            $this->assertStringContainsString($messageFragment, $exception->getMessage());
        }
    }
}
