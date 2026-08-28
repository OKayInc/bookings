# Upgrade M7 to M7-R1

1. Back up the application and preserve `.env` and runtime `storage`.
2. Apply the M7→M7-R1 source patch.
3. Add the Mailgun transport dependencies to the existing Composer lock/vendor tree:

```bash
composer update symfony/mailgun-mailer symfony/http-client --with-dependencies
```

4. Configure a real mail transport. For Mailgun API:

```dotenv
MAIL_MAILER=mailgun
MAIL_FROM_ADDRESS=no-reply@YOUR_VERIFIED_DOMAIN
MAIL_FROM_NAME="${APP_NAME}"
MAILGUN_DOMAIN=YOUR_MAILGUN_SENDING_DOMAIN
MAILGUN_SECRET=YOUR_MAILGUN_API_KEY
MAILGUN_ENDPOINT=api.mailgun.net
AUTH_EMAIL_VERIFICATION_EXPIRE=60
```

Use `api.eu.mailgun.net` for an EU-region Mailgun domain.

5. Clear configuration/views and run tests:

```bash
php artisan optimize:clear
php artisan view:clear
php artisan test
```

There are no database migrations in M7-R1. Existing backend users are not retroactively invalidated if `email_verified_at` is already populated. Any existing user with a null `email_verified_at` will be required to verify before accessing the backend; they can request a new link from `/email/verify`.
