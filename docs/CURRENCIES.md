# Organization currencies

M3-R2 restricts an organization's accounting/payment currency to the intersection of currencies supported by both Stripe presentment payments and PayPal REST payments.

Supported codes:

`AUD`, `BRL`, `CAD`, `CNY`, `CZK`, `DKK`, `EUR`, `HKD`, `HUF`, `ILS`, `JPY`, `MYR`, `MXN`, `TWD`, `NZD`, `NOK`, `PHP`, `PLN`, `GBP`, `SGD`, `SEK`, `CHF`, `THB`, `USD`.

The source of truth in the application is `App\Domain\Money\PaymentCurrencyCatalog`.

## Provider restrictions

This list means the currency exists in both providers' supported payment-currency sets. It does **not** mean every connected merchant account can accept every currency.

PayPal documents account/country restrictions for `BRL`, `CNY`, and `MYR`. Payment-account eligibility will be validated when Stripe and PayPal account connections are implemented in M9.

For compatibility with PayPal, this application treats `HUF`, `JPY`, and `TWD` as zero-decimal currencies. Stripe has different special handling for HUF/TWD, but whole-unit amounts are accepted by both providers and are therefore the safe common behavior.

Provider documentation reviewed: 2026-08-24.
