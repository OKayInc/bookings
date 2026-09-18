# Mail and backend email verification

## Backend user verification

M7-R1 requires every registered backend user to verify their email address before accessing organizations, scheduling, resources, bookings, calendars, or other authenticated administration pages.

Registration still creates the person, backend user, first organization, and owner membership immediately. Laravel dispatches the `Registered` event and sends the normal email-verification notification. The user remains authenticated but is redirected to `/email/verify` until the signed link is used.

Because this project stores internal IDs as UUIDv7 in `BINARY(16)`, M7-R1 customizes Laravel's verification URL to use the model's printable UUID string instead of the raw binary primary key.

The signed verification link lifetime is controlled by:

```dotenv
AUTH_EMAIL_VERIFICATION_EXPIRE=60
```

## Mailgun API transport

Mailgun is available as an API mail transport. It is not configured through `MAIL_HOST` / `MAIL_PORT`; Laravel uses Symfony's Mailgun transport and HTTP client.

Install/update the required packages in an existing deployment:

```bash
composer update symfony/mailgun-mailer symfony/http-client --with-dependencies
```

US Mailgun example:

```dotenv
MAIL_MAILER=mailgun
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
MAILGUN_DOMAIN=mg.example.com
MAILGUN_SECRET=key-...
MAILGUN_ENDPOINT=api.mailgun.net
```

EU Mailgun example:

```dotenv
MAILGUN_ENDPOINT=api.eu.mailgun.net
```

The from-domain/address must satisfy the sending-domain rules configured in the Mailgun account. Sandbox domains can send only to authorized recipients.

For development you may continue using `MAIL_MAILER=log`; SMTP remains supported with `MAIL_MAILER=smtp`.

## Quick test

```bash
php artisan tinker
```

Then:

```php
Illuminate\Support\Facades\Mail::raw('Mail transport test', function ($message) {
    $message->to('you@example.com')->subject('Appointment Software mail test');
});
```
