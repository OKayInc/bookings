<?php

namespace App\Http\Controllers;

use App\Enums\PaymentRuleMatchType;
use App\Enums\PaymentRuleType;
use App\Models\PaymentRule;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class PaymentRuleController extends Controller
{
    public function store(Request $request, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $data = $request->validate([
            'rule_type' => ['required', Rule::enum(PaymentRuleType::class)],
            'match_type' => ['required', Rule::enum(PaymentRuleMatchType::class)],
            'pattern' => ['required', 'string', 'max:254'],
            'note' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $this->validatePattern($data['match_type'], $data['pattern']);

        $normalized = Str::lower(trim($data['pattern']));
        if ($data['match_type'] === PaymentRuleMatchType::Domain->value) {
            $normalized = ltrim($normalized, '@');
        }
        $organization->paymentRules()->updateOrCreate([
            'rule_type' => $data['rule_type'],
            'match_type' => $data['match_type'],
            'pattern_normalized' => $normalized,
        ], [
            'pattern' => $normalized,
            'note' => $data['note'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return back()->with('success', 'Payment rule added.');
    }

    public function toggle(PaymentRule $paymentRule, OrganizationContext $context): RedirectResponse
    {
        $this->sameOrganization($paymentRule, $context);
        $this->authorize('update', $context->organization());
        $paymentRule->update(['is_active' => ! $paymentRule->is_active]);

        return back()->with('success', 'Payment rule updated.');
    }

    public function destroy(PaymentRule $paymentRule, OrganizationContext $context): RedirectResponse
    {
        $this->sameOrganization($paymentRule, $context);
        $this->authorize('update', $context->organization());
        $paymentRule->delete();

        return back()->with('success', 'Payment rule deleted.');
    }

    private function validatePattern(string $matchType, string $pattern): void
    {
        $valid = $matchType === PaymentRuleMatchType::Email->value
            ? filter_var($pattern, FILTER_VALIDATE_EMAIL) !== false
            : preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', ltrim(trim($pattern), '@')) === 1;
        if (! $valid) {
            throw ValidationException::withMessages([
                'pattern' => $matchType === PaymentRuleMatchType::Email->value
                    ? 'Enter a valid email address.'
                    : 'Enter a valid email domain such as example.com.',
            ]);
        }
    }

    private function sameOrganization(PaymentRule $rule, OrganizationContext $context): void
    {
        abort_unless(hash_equals($rule->organization_id, $context->organization()->getKey()), 404);
    }
}
