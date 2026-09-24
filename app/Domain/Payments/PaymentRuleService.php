<?php

namespace App\Domain\Payments;

use App\Enums\PaymentRuleType;
use App\Models\CustomerAccessEntry;
use App\Models\Organization;
use App\Models\OrganizationContact;
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

    public function assertMayBook(Organization $organization, string $email, ?string $phone = null): ?PaymentRule
    {
        $reputation = $this->reputationEntry($organization, $email, $phone);
        if ($reputation?->list_type === 'blacklist') {
            throw new RuntimeException('This customer cannot make an online booking with this organization. Please contact the organization directly.');
        }

        $rule = $this->matchingRule($organization, $email);
        if ($rule?->rule_type === PaymentRuleType::Blocklist) {
            throw new RuntimeException('This email address cannot make an online booking with this organization. Please contact the organization directly.');
        }

        return $rule?->rule_type === PaymentRuleType::Allowlist ? $rule : null;
    }

    public function isReputationAllowlisted(Organization $organization, string $email, ?string $phone = null): bool
    {
        return $this->reputationEntry($organization, $email, $phone)?->list_type === 'whitelist';
    }

    private function reputationEntry(Organization $organization, string $email, ?string $phone): ?CustomerAccessEntry
    {
        $normalizedEmail = OrganizationContact::normalizeEmail($email);
        $normalizedPhone = preg_replace('/\\D+/', '', (string) $phone);

        $contacts = $organization->contacts()
            ->where(function ($query) use ($normalizedEmail, $normalizedPhone): void {
                $query->where('email_normalized', $normalizedEmail);
                if ($normalizedPhone !== '') {
                    $query->orWhere('phone_normalized', $normalizedPhone);
                }
            })
            ->pluck('id');

        if ($contacts->isEmpty()) {
            return null;
        }

        return CustomerAccessEntry::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('organization_contact_id', $contacts)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('expires_at_utc')->orWhere('expires_at_utc', '>', now('UTC'));
            })
            ->orderByRaw("CASE WHEN list_type = 'blacklist' THEN 0 ELSE 1 END")
            ->latest()
            ->first();
    }
}
