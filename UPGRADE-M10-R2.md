# Upgrade to M10-R2 — outgoing webhooks

This full source release builds on the latest M10 gallery/deposit changes and matches the application source in GitHub commit `8df41c8`. The unrelated duplicate application directory under GitHub's `bootstrap/` is not part of this package.

1. Back up your database and uploads. Deploy the source while preserving the server's `.env`, uploaded files and installed dependencies.
2. Ensure the web/CLI PHP configuration supports cURL, HTTPS, DNS and a valid CA bundle. This release adds no Composer packages.
3. Run:

   ```bash
   php artisan optimize:clear
   php artisan migrate --force
   ```

   One migration adds `webhook_endpoints`, `webhook_deliveries` and `webhook_attempts`, using binary UUID primary keys and tenant foreign keys. Deployment creates no endpoint and sends no test or production webhook automatically.
4. Keep your existing `php artisan schedule:run` cron every minute. R2 registers `webhooks:dispatch` with that scheduler. No continuous queue worker is needed.
5. In a paid organization, sign in as owner/administrator and open **Organization → Webhooks**. Follow its setup guide, create an endpoint, copy its signing secret and click **Send test** after configuring the receiver.
6. Open delivery history after the next scheduled run. The full reference and PHP verification example are in `docs/M10-R2-OUTGOING-WEBHOOKS.md`.

## Verification

Static PHP parsing and whitespace checks passed for the modified/new code; the archive was checked for integrity. Runtime PHPUnit/MariaDB tests could not be executed in the build environment. Run these in your configured dedicated test database before production deployment:

```bash
php artisan test --filter=M10R2
php artisan test --filter=BladeCompilationTest
php artisan test --filter=M10PurgeTest
```

The earlier M10 binary SHA-256 fixture correction, resource deposit override and gallery upload/ordering changes are preserved. R2 adds purge handling for webhook delivery history and endpoint configuration. No purge or real outbound webhook was executed while building this release.
