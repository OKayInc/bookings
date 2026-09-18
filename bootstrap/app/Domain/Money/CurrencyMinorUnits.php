<?php

namespace App\Domain\Money;

final class CurrencyMinorUnits
{
    /**
     * Currency exponents used by this application's payment-compatible money
     * model. HUF and TWD are deliberately treated as zero-decimal here because
     * PayPal's REST payment support requires whole-unit amounts for them, even
     * though Stripe can accept fractional charges for those currencies.
     * Unknown currencies default to 2 for internal/test compatibility.
     */
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'HUF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG',
        'RWF', 'TWD', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    private const THREE_DECIMAL = [
        'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND',
    ];

    public function exponent(string $currency): int
    {
        $currency = strtoupper(trim($currency));

        if (in_array($currency, self::ZERO_DECIMAL, true)) {
            return 0;
        }

        if (in_array($currency, self::THREE_DECIMAL, true)) {
            return 3;
        }

        return 2;
    }
}
