<?php

namespace App\Domain\Payments;

use App\Enums\PaymentProvider;
use App\Models\Organization;

class PaymentProviderCatalog
{
    /** @return list<PaymentProvider> */
    public function available(Organization $organization): array
    {
        $settings = $organization->paymentSettings;
        if ($settings === null) {
            return [];
        }

        $providers = array_values(array_filter(
            PaymentProvider::cases(),
            fn (PaymentProvider $provider): bool => $settings->isConfigured($provider),
        ));

        if ($settings->default_provider !== null) {
            usort($providers, fn (PaymentProvider $left, PaymentProvider $right): int => match (true) {
                $left === $settings->default_provider => -1,
                $right === $settings->default_provider => 1,
                default => $left->value <=> $right->value,
            });
        }

        return $providers;
    }

    public function isAvailable(Organization $organization, PaymentProvider $provider): bool
    {
        return in_array($provider, $this->available($organization), true);
    }
}
