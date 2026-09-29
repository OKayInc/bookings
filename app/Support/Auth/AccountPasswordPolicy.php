<?php

namespace App\Support\Auth;

use Illuminate\Validation\Rules\Password;

final class AccountPasswordPolicy
{
    public static function rule(): Password
    {
        return Password::min(12)->letters()->mixedCase()->numbers();
    }

    /** @return array<string, string> */
    public static function checklist(): array
    {
        $rules = self::rule()->appliedRules();
        $items = ['length' => "At least {$rules['min']} characters"];

        if ($rules['max'] !== null) {
            $items['maximum'] = "No more than {$rules['max']} characters";
        }

        if ($rules['mixedCase']) {
            $items['lowercase'] = 'At least 1 lowercase letter (a–z)';
            $items['uppercase'] = 'At least 1 uppercase letter (A–Z)';
        } elseif ($rules['letters']) {
            $items['letters'] = 'At least 1 letter';
        }

        if ($rules['numbers']) {
            $items['number'] = 'At least 1 number (0–9)';
        }

        if ($rules['symbols']) {
            $items['symbol'] = 'At least 1 symbol (for example, ! @ # $)';
        }

        return $items;
    }
}
