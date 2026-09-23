<?php

namespace Tests\Unit;

use App\Domain\Plans\PlanStripeGateway;
use App\Enums\PlanAddon;
use App\Models\Organization;
use App\Models\OrganizationPlanAddon;
use App\Models\OrganizationPlanSubscription;
use App\Models\User;
use App\Support\Uuid\UuidBinary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class M11PlanValueTest extends TestCase
{
    public static function addonUnits(): array
    {
        return [
            'member' => [PlanAddon::Members, 1],
            'appointment type' => [PlanAddon::AppointmentTypes, 1],
            'booking block' => [PlanAddon::BookingBlocks, 25],
            'resource' => [PlanAddon::Resources, 1],
            'calendar' => [PlanAddon::CalendarConnections, 1],
            'storage GB' => [PlanAddon::StorageGb, 1024],
            'distance block' => [PlanAddon::DistanceLookupBlocks, 250],
        ];
    }

    #[DataProvider('addonUnits')]
    public function test_addon_quantities_map_to_the_designed_capacity(PlanAddon $addon, int $units): void
    {
        $this->assertSame($units, $addon->unitsPerQuantity());
    }

    public function test_trial_and_grace_access_require_unexpired_deadlines(): void
    {
        $trial = new OrganizationPlanSubscription([
            'status' => 'trialing',
            'trial_ends_at_utc' => CarbonImmutable::now('UTC')->addMinute(),
        ]);
        $this->assertTrue($trial->providesBusinessAccess());

        $trial->trial_ends_at_utc = null;
        $this->assertFalse($trial->providesBusinessAccess());

        $pastDue = new OrganizationPlanSubscription([
            'status' => 'past_due',
            'grace_ends_at_utc' => CarbonImmutable::now('UTC')->subMinute(),
        ]);
        $this->assertFalse($pastDue->providesBusinessAccess());

        $pastDue->grace_ends_at_utc = CarbonImmutable::now('UTC')->addMinute();
        $this->assertTrue($pastDue->providesBusinessAccess());
    }

    public function test_pending_addon_reduction_keeps_capacity_until_its_effective_time(): void
    {
        $now = CarbonImmutable::now('UTC');
        $addon = new OrganizationPlanAddon([
            'addon' => PlanAddon::Resources,
            'quantity' => 4,
            'pending_quantity' => 1,
            'pending_effective_at_utc' => $now->addDay(),
        ]);

        $this->assertSame(4, $addon->effectiveQuantity($now));
        $this->assertSame(1, $addon->effectiveQuantity($now->addDay()));
    }

    public function test_checkout_uses_addon_prices_matching_the_subscription_interval(): void
    {
        config([
            'plans.stripe.secret_key' => 'sk_test_m11',
            'plans.stripe.monthly_price_id' => 'price_business_monthly',
            'plans.stripe.annual_price_id' => 'price_business_annual',
            'plans.addons.members.monthly_price_id' => 'price_member_monthly',
            'plans.addons.members.annual_price_id' => 'price_member_annual',
        ]);
        $organization = new Organization;
        $organization->setRawAttributes([
            'id' => UuidBinary::toBytes((string) Str::uuid()),
            'name' => 'Interval Test',
        ]);
        $organization->setRelation('planSubscription', null);
        $user = new User(['email' => 'billing@example.test']);
        $payloads = [];
        Http::fake(function (Request $request) use (&$payloads) {
            $payloads[] = $request->data();

            return Http::response(['id' => 'cs_test', 'url' => 'https://checkout.example.test']);
        });

        $gateway = app(PlanStripeGateway::class);
        foreach (['monthly', 'annual'] as $interval) {
            $gateway->createCheckout(
                $organization,
                $user,
                $interval,
                [PlanAddon::Members->value => 2],
                'https://appointment.test/success',
                'https://appointment.test/cancel',
            );
        }

        $this->assertSame('price_business_monthly', data_get($payloads, '0.line_items.0.price'));
        $this->assertSame('price_member_monthly', data_get($payloads, '0.line_items.1.price'));
        $this->assertSame('price_business_annual', data_get($payloads, '1.line_items.0.price'));
        $this->assertSame('price_member_annual', data_get($payloads, '1.line_items.1.price'));
        $this->assertArrayNotHasKey('billing_mode', data_get($payloads, '1.subscription_data'));
    }

    public function test_addon_reductions_change_next_renewal_without_removing_paid_capacity_early(): void
    {
        config([
            'plans.stripe.secret_key' => 'sk_test_m11',
            'plans.addons.members.monthly_price_id' => 'price_member_monthly',
        ]);
        $organization = new Organization;
        $organization->setRawAttributes([
            'id' => UuidBinary::toBytes((string) Str::uuid()),
            'name' => 'Reduction Test',
        ]);
        $organization->setRelation('planSubscription', new OrganizationPlanSubscription([
            'provider_subscription_id' => 'sub_reduction',
            'billing_interval' => 'monthly',
        ]));
        $organization->setRelation('planAddons', new EloquentCollection([
            new OrganizationPlanAddon([
                'addon' => PlanAddon::Members,
                'quantity' => 5,
                'provider_item_id' => 'si_member',
            ]),
        ]));
        $updates = [];
        Http::fake(function (Request $request) use (&$updates) {
            if ($request->method() === 'GET') {
                return Http::response(['items' => ['data' => [[
                    'id' => 'si_member',
                    'price' => ['id' => 'price_member_monthly'],
                    'quantity' => 5,
                ]]]]);
            }
            $updates[] = $request->data();

            return Http::response(['id' => 'si_member']);
        });

        app(PlanStripeGateway::class)->syncAddons(
            $organization,
            [PlanAddon::Members->value => 3],
            [PlanAddon::Members->value => 5],
        );

        $this->assertSame(3, data_get($updates, '0.quantity'));
        $this->assertSame('none', data_get($updates, '0.proration_behavior'));
    }

    public function test_only_addon_capacity_above_the_paid_through_quantity_is_prorated(): void
    {
        config([
            'plans.stripe.secret_key' => 'sk_test_m11',
            'plans.addons.members.monthly_price_id' => 'price_member_monthly',
        ]);
        $organization = new Organization;
        $organization->setRawAttributes([
            'id' => UuidBinary::toBytes((string) Str::uuid()),
            'name' => 'Proration Test',
        ]);
        $organization->setRelation('planSubscription', new OrganizationPlanSubscription([
            'provider_subscription_id' => 'sub_proration',
            'billing_interval' => 'monthly',
        ]));
        $organization->setRelation('planAddons', new EloquentCollection([
            new OrganizationPlanAddon([
                'addon' => PlanAddon::Members,
                'quantity' => 5,
                'provider_item_id' => 'si_member',
            ]),
        ]));
        $updates = [];
        Http::fake(function (Request $request) use (&$updates) {
            if ($request->method() === 'GET') {
                return Http::response(['items' => ['data' => [[
                    'id' => 'si_member',
                    'price' => ['id' => 'price_member_monthly'],
                    'quantity' => 3,
                ]]]]);
            }
            $updates[] = $request->data();

            return Http::response(['id' => 'si_member']);
        });

        // The provider currently has the pending reduction of three. Restore
        // the five paid-through units without proration, then prorate unit six.
        app(PlanStripeGateway::class)->syncAddons(
            $organization,
            [PlanAddon::Members->value => 6],
            [PlanAddon::Members->value => 5],
        );

        $this->assertSame(5, data_get($updates, '0.quantity'));
        $this->assertSame('none', data_get($updates, '0.proration_behavior'));
        $this->assertSame(6, data_get($updates, '1.quantity'));
        $this->assertSame('create_prorations', data_get($updates, '1.proration_behavior'));
    }
}
