<?php

namespace App\Http\Controllers;

use App\Domain\Plans\PlanAddonService;
use App\Domain\Plans\PlanAuditService;
use App\Domain\Plans\PlanBillingException;
use App\Domain\Plans\PlanEntitlementService;
use App\Domain\Plans\PlanLimitException;
use App\Domain\Plans\PlanLimitService;
use App\Domain\Plans\PlanPromotionService;
use App\Domain\Plans\PlanStripeGateway;
use App\Enums\PlanAddon;
use App\Enums\PlanLevel;
use App\Models\OrganizationPlanSubscription;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(
        OrganizationContext $context,
        PlanEntitlementService $entitlements,
        PlanLimitService $limits,
        PlanStripeGateway $stripe,
    ): View {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $records = $organization->planAddons()->get()->keyBy(fn ($record) => $record->addon->value);
        $selections = collect(PlanAddon::cases())->mapWithKeys(function (PlanAddon $addon) use ($records): array {
            $record = $records->get($addon->value);

            return [$addon->value => max(0, (int) ($record?->pending_quantity ?? $record?->quantity ?? 0))];
        })->all();

        return view('plans.index', [
            'organization' => $organization,
            'entitlement' => $entitlements->for($organization),
            'subscription' => $organization->planSubscription()->first(),
            'addonTypes' => PlanAddon::cases(),
            'addonRecords' => $records,
            'addonSelections' => $selections,
            'usage' => $limits->usage($organization),
            'limits' => collect([
                'members', 'active_appointment_types', 'monthly_bookings', 'resources',
                'person_resources', 'calendar_connections', 'storage_mb',
                'monthly_distance_lookups', 'questions',
            ])->mapWithKeys(fn (string $key): array => [$key => $limits->limit($organization, $key)])->all(),
            'billingConfigured' => $stripe->isConfigured(),
        ]);
    }

    public function updateAddons(
        Request $request,
        OrganizationContext $context,
        PlanAddonService $addons,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $rules = [];
        foreach (PlanAddon::cases() as $addon) {
            $rules['addons.'.$addon->value] = ['nullable', 'integer', 'between:0,100000'];
        }
        $validated = $request->validate($rules);

        try {
            $addons->update($organization, $request->user(), (array) ($validated['addons'] ?? []));
        } catch (PlanLimitException|PlanBillingException $exception) {
            return back()->withInput()->withErrors(['addons' => $exception->getMessage()]);
        }

        return back()->with('success', 'Add-on selections saved. Paid increases apply after Stripe accepts them; reductions keep their current capacity through the paid period.');
    }

    public function checkout(
        Request $request,
        OrganizationContext $context,
        PlanAddonService $addons,
        PlanStripeGateway $stripe,
        PlanAuditService $audit,
        PlanEntitlementService $entitlements,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $data = $request->validate(['interval' => ['required', Rule::in(['monthly', 'annual'])]]);
        if ($entitlements->for($organization)->level === PlanLevel::Complimentary) {
            return back()->withErrors(['billing' => 'Complimentary Unlimited does not require a paid subscription.']);
        }
        $existing = $organization->planSubscription()->first();
        if ($existing?->provider_subscription_id !== null
            && in_array($existing->status, ['trialing', 'active', 'past_due'], true)) {
            return back()->withErrors(['billing' => 'A Business subscription already exists. Use the billing portal to manage it.']);
        }
        if ($existing?->status === 'incomplete'
            && $existing->checkout_session_id !== null
            && $existing->updated_at?->isAfter(now('UTC')->subMinutes(30))) {
            return back()->withErrors(['billing' => 'A checkout is already pending. Wait a few minutes for Stripe confirmation before starting another.']);
        }

        try {
            $session = $stripe->createCheckout(
                $organization,
                $request->user(),
                $data['interval'],
                $addons->quantities($organization),
                route('plans.checkout-return').'?session_id={CHECKOUT_SESSION_ID}',
                route('plans.index'),
            );
        } catch (PlanBillingException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }
        if (! is_string($session['id'] ?? null) || ! is_string($session['url'] ?? null)) {
            return back()->withErrors(['billing' => 'Stripe returned an incomplete checkout session.']);
        }

        DB::transaction(function () use ($organization, $session, $data): void {
            $subscription = OrganizationPlanSubscription::query()
                ->where('organization_id', $organization->getKey())
                ->lockForUpdate()
                ->first();
            $values = [
                'provider' => 'stripe',
                'checkout_session_id' => $session['id'],
                'billing_interval' => $data['interval'],
            ];
            $authoritative = $subscription?->provider_subscription_id !== null
                && in_array($subscription->status, ['trialing', 'active', 'past_due'], true);
            if (! $authoritative) {
                $values += [
                    'provider_subscription_id' => null,
                    'status' => 'incomplete',
                    'trial_ends_at_utc' => null,
                    'current_period_ends_at_utc' => null,
                    'grace_ends_at_utc' => null,
                    'cancel_at_period_end' => false,
                ];
            }

            if ($subscription === null) {
                OrganizationPlanSubscription::create([
                    'organization_id' => $organization->getKey(),
                    ...$values,
                ]);
            } else {
                $subscription->update($values);
            }
        }, 3);
        $audit->record('subscription.checkout_started', $organization, $request->user(), [
            'billing_interval' => $data['interval'],
        ]);

        return redirect()->away($session['url']);
    }

    public function checkoutReturn(): RedirectResponse
    {
        return redirect()->route('plans.index')->with(
            'success',
            'Checkout was completed. Business access is enabled when the signed Stripe webhook confirms the subscription.',
        );
    }

    public function portal(
        OrganizationContext $context,
        PlanStripeGateway $stripe,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $subscription = $organization->planSubscription()->first();
        if ($subscription === null) {
            return back()->withErrors(['billing' => 'This organization does not have a Stripe subscription.']);
        }

        try {
            $portal = $stripe->createPortal($subscription, route('plans.index'));
        } catch (PlanBillingException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        return redirect()->away((string) $portal['url']);
    }

    public function cancel(
        Request $request,
        OrganizationContext $context,
        PlanStripeGateway $stripe,
        PlanAuditService $audit,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $subscription = $organization->planSubscription()->first();
        if ($subscription === null) {
            return back()->withErrors(['billing' => 'No subscription is available to cancel.']);
        }

        try {
            $stripe->cancelAtPeriodEnd($subscription);
        } catch (PlanBillingException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }
        $subscription->update(['cancel_at_period_end' => true]);
        $audit->record('subscription.cancellation_scheduled', $organization, $request->user());

        return back()->with('success', 'The Business subscription will end after the current paid period. No organization data was deleted.');
    }

    public function redeem(
        Request $request,
        OrganizationContext $context,
        PlanPromotionService $promotions,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);
        try {
            $promotions->redeem($organization, $request->user(), $data['code']);
        } catch (PlanLimitException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()]);
        }

        return back()->with('success', 'Complimentary Unlimited has been applied to this organization.');
    }

    public function branding(
        Request $request,
        OrganizationContext $context,
        PlanEntitlementService $entitlements,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        if (! $entitlements->hasBusinessFeatures($organization)) {
            return back()->withErrors(['branding' => 'Removing Appointment.to branding requires Business or Complimentary Unlimited.']);
        }
        $organization->update(['hide_platform_branding' => $request->boolean('hide_platform_branding')]);

        return back()->with('success', 'Public-page branding preference updated.');
    }
}
