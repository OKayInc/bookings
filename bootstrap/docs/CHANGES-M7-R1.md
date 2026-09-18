# M7-R1 changes

- Backend `User` now implements Laravel `MustVerifyEmail`.
- Registration dispatches Laravel's `Registered` event and redirects to the verification notice.
- Added verification notice, signed verify route, resend route, and `verified` middleware on backend administration.
- Verification signed URLs use printable UUIDs instead of raw `BINARY(16)` primary keys.
- Added Mailgun HTTP API mail transport configuration.
- Added `symfony/mailgun-mailer` and `symfony/http-client` requirements.
- Added Mailgun US/EU endpoint configuration to `.env.example`.
- Added tests for registration notification, verified middleware, UUID verification links, and Mailgun configuration.
- No database migration is required because `users.email_verified_at` already exists.
