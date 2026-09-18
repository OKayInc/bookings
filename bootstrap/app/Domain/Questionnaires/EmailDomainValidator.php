<?php

namespace App\Domain\Questionnaires;

class EmailDomainValidator
{
    public function exists(string $email): bool
    {
        if (! config('questionnaire.email_dns_validation', true)) {
            return true;
        }

        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));
        if ($domain === '') {
            return false;
        }

        if (function_exists('idn_to_ascii')) {
            $flags = defined('IDNA_DEFAULT') ? IDNA_DEFAULT : 0;
            $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 1;
            $domain = idn_to_ascii($domain, $flags, $variant) ?: $domain;
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }
}
