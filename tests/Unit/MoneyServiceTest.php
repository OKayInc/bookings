<?php

namespace Tests\Unit;

use App\Domain\Money\MoneyService;
use InvalidArgumentException;
use Tests\TestCase;

class MoneyServiceTest extends TestCase
{
    public function test_cad_is_parsed_into_minor_units(): void
    {
        $money = app(MoneyService::class);
        $this->assertSame(15050, $money->parse('150.50', 'CAD'));
        $this->assertSame('CAD 150.50', $money->format(15050, 'CAD'));
    }

    public function test_zero_decimal_currency_rejects_fraction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(MoneyService::class)->parse('100.50', 'JPY');
    }

    public function test_paypal_zero_digit_currencies_reject_fractional_amounts(): void
    {
        $service = app(\App\Domain\Money\MoneyService::class);

        foreach (['HUF', 'TWD'] as $currency) {
            try {
                $service->parse('10.50', $currency);
                $this->fail($currency.' should reject fractional amounts for shared Stripe/PayPal compatibility.');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
