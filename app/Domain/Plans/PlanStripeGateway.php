<?php

namespace App\Domain\Plans;

use App\Enums\PlanAddon;
use App\Models\Organization;
use App\Models\OrganizationPlanSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PlanStripeGateway
{
    public function isConfigured(): bool
    {
        return filled(config('plans.stripe.secret_key'))
            && filled(config('plans.stripe.monthly_price_id'))
            && filled(config('plans.stripe.annual_price_id'));
    }

    /** @return array<string,mixed> */
    public function createCheckout(
        Organization $organization,
        User $user,
        string $interval,
        array $addonQuantities,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $this->assertConfigured();
        $basePrice = (string) config('plans.stripe.'.($interval === 'annual' ? 'annual_price_id' : 'monthly_price_id'));
        $lineItems = [['price' => $basePrice, 'quantity' => 1]];

        foreach (PlanAddon::cases() as $addon) {
            $quantity = max(0, (int) ($addonQuantities[$addon->value] ?? 0));
            $priceId = $this->addonPriceId($addon, $interval);
            if ($quantity > 0) {
                if ($priceId === '') {
                    throw new PlanBillingException('The Stripe '.$interval.' price ID is not configured for '.$addon->label().'.');
                }
                $lineItems[] = ['price' => $priceId, 'quantity' => $quantity];
            }
        }

        $subscriptionData = [
            'metadata' => [
                'organization_uuid' => $organization->uuid,
                'appointment_plan' => 'business',
                'billing_interval' => $interval,
            ],
        ];
        $trialDays = max(0, (int) config('plans.trial_days', 14));
        $existingSubscription = $organization->planSubscription;
        $trialEligible = $existingSubscription === null
            || ($existingSubscription->status === 'incomplete'
                && $existingSubscription->provider_subscription_id === null);
        if ($trialDays > 0 && $trialEligible) {
            $subscriptionData['trial_period_days'] = $trialDays;
        }
        $subscription = $existingSubscription;
        $payload = [
            'mode' => 'subscription',
            'client_reference_id' => $organization->uuid,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => $lineItems,
            'subscription_data' => $subscriptionData,
            'metadata' => [
                'organization_uuid' => $organization->uuid,
                'billing_interval' => $interval,
            ],
            'billing_address_collection' => 'required',
            'tax_id_collection' => ['enabled' => true],
        ];
        if ($subscription?->provider_customer_id) {
            $payload['customer'] = $subscription->provider_customer_id;
        } else {
            $payload['customer_email'] = $user->email;
        }

        return $this->payload(
            $this->request()
                ->withHeaders(['Idempotency-Key' => 'plan-checkout-'.$organization->uuid.'-'.Str::uuid()])
                ->asForm()
                ->post($this->url('/v1/checkout/sessions'), $payload),
            'Stripe could not create the Business checkout session.',
        );
    }

    /** @return array<string,mixed> */
    public function createPortal(OrganizationPlanSubscription $subscription, string $returnUrl): array
    {
        if (! $subscription->provider_customer_id) {
            throw new PlanBillingException('The Stripe customer reference is not available yet.');
        }

        return $this->payload(
            $this->request()->asForm()->post($this->url('/v1/billing_portal/sessions'), [
                'customer' => $subscription->provider_customer_id,
                'return_url' => $returnUrl,
            ]),
            'Stripe could not open the billing portal.',
        );
    }

    public function cancelAtPeriodEnd(OrganizationPlanSubscription $subscription): void
    {
        if (! $subscription->provider_subscription_id) {
            throw new PlanBillingException('The Stripe subscription reference is unavailable.');
        }

        $this->payload(
            $this->request()->asForm()->post(
                $this->url('/v1/subscriptions/'.rawurlencode($subscription->provider_subscription_id)),
                ['cancel_at_period_end' => 'true'],
            ),
            'Stripe could not schedule the subscription cancellation.',
        );
    }

    /**
     * @param  array<string,int>  $quantities
     * @param  array<string,int>  $paidThroughQuantities
     * @return array<string,string> addon value => provider subscription item ID
     */
    public function syncAddons(
        Organization $organization,
        array $quantities,
        array $paidThroughQuantities = [],
    ): array {
        $subscription = $organization->planSubscription;
        if (! $subscription?->provider_subscription_id) {
            return [];
        }

        $remote = $this->payload(
            $this->request()->get(
                $this->url('/v1/subscriptions/'.rawurlencode($subscription->provider_subscription_id)),
                ['expand' => ['items.data.price']],
            ),
            'Stripe could not retrieve the Business subscription.',
        );
        $items = collect((array) data_get($remote, 'items.data', []));
        $addonRecords = $organization->relationLoaded('planAddons')
            ? $organization->getRelation('planAddons')
            : $organization->planAddons()->whereNotNull('provider_item_id')->get();
        $knownItemIds = $addonRecords
            ->whereNotNull('provider_item_id')
            ->mapWithKeys(fn ($record): array => [$record->addon->value => $record->provider_item_id]);
        $result = [];
        $interval = $subscription->billing_interval === 'annual' ? 'annual' : 'monthly';

        foreach (PlanAddon::cases() as $addon) {
            $priceId = $this->addonPriceId($addon, $interval);
            $desired = max(0, (int) ($quantities[$addon->value] ?? 0));
            $knownItemId = (string) ($knownItemIds->get($addon->value) ?? '');
            $item = $items->first(function (array $candidate) use ($addon, $priceId, $knownItemId): bool {
                return ($priceId !== '' && (string) data_get($candidate, 'price.id') === $priceId)
                    || ($knownItemId !== '' && (string) ($candidate['id'] ?? '') === $knownItemId)
                    || (string) data_get($candidate, 'metadata.appointment_addon') === $addon->value;
            });
            if ($priceId === '') {
                if ($desired > 0) {
                    throw new PlanBillingException('The Stripe '.$interval.' price ID is not configured for '.$addon->label().'.');
                }

                if (! is_array($item)) {
                    continue;
                }
            }
            $remoteQuantity = is_array($item) ? max(0, (int) ($item['quantity'] ?? 0)) : 0;
            $paidThrough = array_key_exists($addon->value, $paidThroughQuantities)
                ? max(0, (int) $paidThroughQuantities[$addon->value])
                : $remoteQuantity;

            if (is_array($item) && $desired === 0) {
                $this->payload(
                    $this->request()->asForm()->delete(
                        $this->url('/v1/subscription_items/'.rawurlencode((string) $item['id'])),
                        ['proration_behavior' => 'none'],
                    ),
                    'Stripe could not remove '.$addon->label().'.',
                );

                continue;
            }

            if (is_array($item)) {
                if ((string) data_get($item, 'price.id') === $priceId
                    && $remoteQuantity === $desired) {
                    $result[$addon->value] = (string) $item['id'];

                    continue;
                }
                if ($remoteQuantity < $paidThrough && $desired > $paidThrough) {
                    $this->updateAddonItem(
                        (string) $item['id'],
                        $priceId,
                        $paidThrough,
                        'none',
                        $addon,
                    );
                    $remoteQuantity = $paidThrough;
                }
                $updated = $this->payload(
                    $this->request()->asForm()->post(
                        $this->url('/v1/subscription_items/'.rawurlencode((string) $item['id'])),
                        [
                            'price' => $priceId,
                            'quantity' => $desired,
                            'proration_behavior' => $desired <= max($remoteQuantity, $paidThrough)
                                ? 'none'
                                : 'create_prorations',
                            'metadata' => ['appointment_addon' => $addon->value],
                        ],
                    ),
                    'Stripe could not update '.$addon->label().'.',
                );
                $result[$addon->value] = (string) $updated['id'];

                continue;
            }

            if ($desired > 0) {
                $initialQuantity = $paidThrough > 0 && $desired > $paidThrough
                    ? $paidThrough
                    : $desired;
                $created = $this->payload(
                    $this->request()->asForm()->post($this->url('/v1/subscription_items'), [
                        'subscription' => $subscription->provider_subscription_id,
                        'price' => $priceId,
                        'quantity' => $initialQuantity,
                        'proration_behavior' => $initialQuantity <= $paidThrough
                            ? 'none'
                            : 'create_prorations',
                        'metadata' => ['appointment_addon' => $addon->value],
                    ]),
                    'Stripe could not add '.$addon->label().'.',
                );
                if ($desired > $initialQuantity) {
                    $created = $this->updateAddonItem(
                        (string) $created['id'],
                        $priceId,
                        $desired,
                        'create_prorations',
                        $addon,
                    );
                }
                $result[$addon->value] = (string) $created['id'];
            }
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public function verifyWebhook(string $payload, string $signatureHeader): array
    {
        $secret = (string) config('plans.stripe.webhook_secret');
        if ($secret === '') {
            throw new PlanBillingException('The plan billing webhook secret is not configured.');
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key !== null && $value !== null) {
                $parts[$key][] = $value;
            }
        }
        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
        $tolerance = max(0, (int) config('plans.stripe.webhook_tolerance_seconds', 300));
        if ($timestamp <= 0 || abs(CarbonImmutable::now('UTC')->timestamp - $timestamp) > $tolerance) {
            throw new PlanBillingException('The Stripe billing webhook timestamp is invalid or expired.');
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $valid = collect($parts['v1'] ?? [])->contains(fn (string $signature): bool => hash_equals($expected, $signature));
        if (! $valid) {
            throw new PlanBillingException('The Stripe billing webhook signature is invalid.');
        }
        $event = json_decode($payload, true);
        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null)) {
            throw new PlanBillingException('The Stripe billing webhook payload is invalid.');
        }

        return $event;
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new PlanBillingException('Business billing is not fully configured. Add the platform Stripe key and Business price IDs.');
        }
    }

    private function addonPriceId(PlanAddon $addon, string $interval): string
    {
        $priceKey = $interval === 'annual' ? 'annual_price_id' : 'monthly_price_id';

        return trim((string) config('plans.addons.'.$addon->value.'.'.$priceKey));
    }

    /** @return array<string,mixed> */
    private function updateAddonItem(
        string $itemId,
        string $priceId,
        int $quantity,
        string $prorationBehavior,
        PlanAddon $addon,
    ): array {
        return $this->payload(
            $this->request()->asForm()->post(
                $this->url('/v1/subscription_items/'.rawurlencode($itemId)),
                [
                    'price' => $priceId,
                    'quantity' => $quantity,
                    'proration_behavior' => $prorationBehavior,
                    'metadata' => ['appointment_addon' => $addon->value],
                ],
            ),
            'Stripe could not update '.$addon->label().'.',
        );
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->withBasicAuth((string) config('plans.stripe.secret_key'), '')
            ->timeout((int) config('plans.stripe.request_timeout_seconds', 20));
        $version = trim((string) config('plans.stripe.api_version'));

        return $version === '' ? $request : $request->withHeaders(['Stripe-Version' => $version]);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('plans.stripe.api_url'), '/').$path;
    }

    /** @return array<string,mixed> */
    private function payload(Response $response, string $fallback): array
    {
        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload)) {
            $message = is_array($payload) ? data_get($payload, 'error.message') : null;
            throw new PlanBillingException(is_string($message) && $message !== '' ? $message : $fallback);
        }

        return $payload;
    }
}
