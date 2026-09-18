# M7-R1 verification report

Static release checks performed in the packaging environment:

- PHP syntax lint passed for all PHP files under `app`, `bootstrap`, `config`, `database`, `routes`, and `tests`.
- `composer.json` parses as valid JSON.
- `User` implements Laravel `MustVerifyEmail`.
- Registration dispatches Laravel's `Registered` event.
- Verification notice, signed verification handler, and resend routes are present.
- Backend administration is protected by `auth` + `verified` middleware.
- Verification URLs use the printable UUID model attribute rather than the raw `BINARY(16)` primary key.
- Mailgun is registered as a Laravel mailer transport.
- `config/services.php` contains Mailgun domain, secret, endpoint, and HTTPS scheme settings.
- `composer.json` requires `symfony/mailgun-mailer` and `symfony/http-client`.
- No database migration was added; the existing `users.email_verified_at` column is reused.

The packaging environment does not contain this project's Composer `vendor` tree or its MariaDB/Memcached test services, so the full Laravel test suite must be executed on the deployment/test host after Composer updates.

Recommended checks after upgrade:

```bash
composer update symfony/mailgun-mailer symfony/http-client --with-dependencies
php artisan optimize:clear
php artisan view:clear
php artisan test --filter=RegistrationTest
php artisan test --filter=BackendEmailVerificationTest
php artisan test --filter=MailgunConfigurationTest
php artisan test
```
