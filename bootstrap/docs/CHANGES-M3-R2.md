# M3 Revision 2

- Replaced organization currency text input with a dropdown.
- Added centralized `PaymentCurrencyCatalog` containing currencies supported by both Stripe and PayPal REST payments.
- Restricted organization create/update validation to the common provider currency list.
- Applied the same dropdown and validation to first-organization registration for consistency.
- Normalizes submitted currency codes to uppercase before validation.
- Treats HUF and TWD as zero-decimal for shared Stripe/PayPal compatibility.
- Added organization-currency feature tests and money regression coverage.
- No database migration is required.
