<?php

namespace App\Domain\Money;

/**
 * Organization currencies that are supported by both Stripe presentment
 * payments and PayPal REST payments.
 *
 * This is intentionally the intersection of the two providers rather than
 * Stripe's much larger presentment-currency list, because an organization may
 * enable either provider later without changing its accounting currency.
 *
 * Provider/account-country restrictions are still possible and will be
 * checked when payment accounts are connected in M8.
 */
final class PaymentCurrencyCatalog
{
    /** @var array<string, string> */
    private const CURRENCIES = [
        'AUD' => 'Australian Dollar',
        'BRL' => 'Brazilian Real',
        'CAD' => 'Canadian Dollar',
        'CNY' => 'Chinese Renminbi',
        'CZK' => 'Czech Koruna',
        'DKK' => 'Danish Krone',
        'EUR' => 'Euro',
        'HKD' => 'Hong Kong Dollar',
        'HUF' => 'Hungarian Forint',
        'ILS' => 'Israeli New Shekel',
        'JPY' => 'Japanese Yen',
        'MYR' => 'Malaysian Ringgit',
        'MXN' => 'Mexican Peso',
        'TWD' => 'New Taiwan Dollar',
        'NZD' => 'New Zealand Dollar',
        'NOK' => 'Norwegian Krone',
        'PHP' => 'Philippine Peso',
        'PLN' => 'Polish Zloty',
        'GBP' => 'Pound Sterling',
        'SGD' => 'Singapore Dollar',
        'SEK' => 'Swedish Krona',
        'CHF' => 'Swiss Franc',
        'THB' => 'Thai Baht',
        'USD' => 'United States Dollar',
    ];

    /** @return array<string, string> */
    public static function options(): array
    {
        return self::CURRENCIES;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::CURRENCIES);
    }

    public static function supports(string $currency): bool
    {
        return array_key_exists(strtoupper(trim($currency)), self::CURRENCIES);
    }
}
