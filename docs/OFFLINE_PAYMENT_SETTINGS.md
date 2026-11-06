# Offline payment settings and rich-text instructions

## Where to configure

Open **Organization → Payments** (`/payment-settings`). The **Offline payments / e-Transfer** section now contains the organization-wide defaults: enabled/disabled state, payment window and rich-text customer instructions. New appointment types inherit these defaults automatically.

The same section lists every appointment type and shows whether its effective configuration comes from the **Organization default** or a **Custom override**. Use **Configure / override** to open `/appointment-types/{appointmentType}/offline-payments`, where inheritance can be turned on or off. Existing appointment types are migrated to custom overrides so deployment does not silently change their current behavior.

Offline payments do not require Stripe or PayPal credentials. The default hosted-checkout provider remains an online-only choice. When a customer selects offline payment, the effective settings are snapshotted onto the booking; later edits to organization defaults or appointment overrides do not rewrite that booking.

## Editor and appearance

The offline-payment editor uses the shared application layout, section cards, labelled field controls, inline checkbox and sticky save actions used by other appointment forms. Payment windows are displayed in an exact readable unit: for example, 1 day instead of 1,440 minutes, or 2 hours instead of 120 minutes.

The payment instructions field uses the existing shared TinyMCE assets and initialization. Its toolbar supports the same text formatting, colour and lists as other rich-text fields. No new editor dependency, build process or provider account is introduced.

Instructions are sanitized on save and on customer display using the existing rich-text allowlist. Links, media, scripts and unsupported attributes are removed. Enabling offline payment requires visible instructions; an empty editor such as `<p><br></p>` does not satisfy that requirement. Customer pages display the permitted formatting rather than escaped HTML tags.

Older plain-text instructions retain their line breaks when opened in the editor or displayed to customers. Existing booking instruction snapshots and deadlines are not rewritten by this change or by saving new appointment-type instructions.

## Scope and deployment

This change adds a database migration for organization offline-payment defaults and appointment-type inheritance. It does not alter payment amounts, gateway credentials, authorization, payment-review notifications or the manual verification workflow. See [Offline payments and post-appointment balance review](OFFLINE_PAYMENTS.md) for those workflows.

After deploying the code, run the migration before rebuilding the usual Laravel caches:

```sh
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

When PHP OPcache is configured not to notice updated files, reload the site's PHP-FPM/FCGI handler using your normal deployment procedure.

## Regression coverage

```sh
# Development/staging with a separate test database:
php artisan test --filter='OfflinePaymentSettingsTest|OfflinePaymentInstructionsTest|OfflineBookingPaymentsTest'
php artisan view:cache
```

`OfflinePaymentSettingsTest` covers the payment-settings entry point, empty state, tenant isolation, shared editor/layout, instruction saving, invalid/unsafe HTML, customer rendering and preservation of existing booking snapshots. `OfflinePaymentInstructionsTest` covers permitted formatting, sanitization, legacy line breaks, empty content and encoded markup.

The five normalization test methods were checked in an isolated PHP assertion harness against a blob-verified copy of the repository sanitizer. PHP syntax checks passed for the checked source and test files. These checks are not a Laravel test run: the integration tests, Blade compilation and browser appearance still need validation in a complete application checkout.
