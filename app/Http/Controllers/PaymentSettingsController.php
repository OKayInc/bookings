<?php

namespace App\Http\Controllers;

use App\Enums\PaymentProvider;
use App\Enums\PaymentRuleMatchType;
use App\Enums\PaymentRuleType;
use App\Http\Requests\UpdatePaymentSettingsRequest;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentSettingsController extends Controller
{
    private const SECRET_FIELDS = [
        'stripe_secret_key', 'stripe_webhook_secret',
        'paypal_client_id', 'paypal_client_secret', 'paypal_webhook_id',
    ];

    public function edit(OrganizationContext $context): View
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);

        return view('payments.settings', [
            'organization' => $organization,
            'settings' => $organization->paymentSettings()->firstOrNew(),
            'rules' => $organization->paymentRules()->get(),
            'providers' => PaymentProvider::cases(),
            'ruleTypes' => PaymentRuleType::cases(),
            'matchTypes' => PaymentRuleMatchType::cases(),
        ]);
    }

    public function update(UpdatePaymentSettingsRequest $request, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $data = $request->validated();
        $settings = $organization->paymentSettings()->firstOrNew();
        $settings->organization_id = $organization->getKey();
        $settings->default_provider = $data['default_provider'] ?? null;
        $settings->stripe_enabled = $request->boolean('stripe_enabled');
        $settings->stripe_test_mode = $request->boolean('stripe_test_mode');
        $settings->paypal_enabled = $request->boolean('paypal_enabled');
        $settings->paypal_sandbox = $request->boolean('paypal_sandbox');

        foreach (self::SECRET_FIELDS as $field) {
            if ($request->boolean('clear_'.$field)) {
                $settings->{$field} = null;
            } elseif (isset($data[$field]) && trim((string) $data[$field]) !== '') {
                $settings->{$field} = trim((string) $data[$field]);
            }
        }
        $settings->save();

        return back()->with('success', 'Payment settings saved.');
    }
}
