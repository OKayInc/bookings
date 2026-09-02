<?php

namespace App\Domain\Payments;

use App\Enums\PaymentRuleType;
use App\Models\Organization;
use App\Models\PaymentRule;
use RuntimeException;

class PaymentRuleService
{
    public function matchingRule(Organization $organization, string $email): ?PaymentRule
    {
        $rules = $organization->paymentRules()
            ->reorder()
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN rule_type = 'blocklist' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN match_type = 'email' THEN 0 ELSE 1 END")
            ->get();

        return $rules->first(fn (PaymentRule $rule): bool => $rule->matches($email));
    }

    public function assertMayBook(Organization $organization, string $email): ?PaymentRule
    {
        $rule = $this->matchingRule($organization, $email);
        if ($rule?->rule_type === PaymentRuleType::Blocklist) {
            throw new RuntimeException('This email address cannot make an online booking with this organization. Please contact the organization directly.');
        }

        return $rule?->rule_type === PaymentRuleType::Allowlist ? $rule : null;
    }
}
