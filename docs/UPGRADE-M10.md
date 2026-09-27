# Upgrade to Appointment.to M10

Base: OKayInc/bookings commit `b38fd7b` (email template support), checked against the latest supplied email-template release on September 18, 2026.

1. Back up the database and uploaded files. Deploy this complete source tree while preserving your server's `.env`, uploaded files and existing dependency directory.
2. Use your normal PHP 8.4 / MariaDB 10.11 environment. No Composer dependencies were added. On a fresh deployment, install dependencies with `composer install`.
3. Run:

   ```bash
   php artisan optimize:clear
   php artisan migrate --force
   ```

   The two additive migrations add nullable key hashes/timestamps to users and organizations, and a UUID-based retry queue for purge-file deletion. Existing users/organizations start without API keys. No purge runs during installation.
4. Open **Organization → API keys** in an organization with `plan_tier=paid`. Generate the personal key and, as owner/administrator, the organization key. Keep each plaintext key when it is displayed.
5. Verify `/api/v1/me` using both documented headers. API and command reference: `docs/M10-API.md` and `docs/M10-PURGE.md`.
6. Run the regression tests against a dedicated MariaDB database whose name includes `test`:

   ```bash
   php artisan test --filter=M10
   ```

   The project intentionally rejects SQLite and production database names for testing. Use your existing test database configuration.

## Verification status

Static PHP parsing and whitespace checks were performed for the new/changed PHP code. The ZIP was checked for archive integrity and omission of environment secrets/dependencies/runtime files.

The PHPUnit/MariaDB tests could not be executed in the build environment because PHP, Composer and MariaDB were unavailable. Added tests cover two-key authentication, hashing/rotation/revocation, plan/email/membership/role restrictions, tenant isolation, API validation/rate limits, key management, all five purge levels, shared resources, file cleanup and dry-run behavior. Run them before using purge against real data.

The archive includes the full application source and existing changes, without `.env`, `.env.testing`, `vendor`, `.git`, generated caches, logs or user uploads. `.env.example` is included. Install dependencies on a fresh server; an existing deployment can keep its current `vendor` directory.
