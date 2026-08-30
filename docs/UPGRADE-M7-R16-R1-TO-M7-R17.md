# Upgrade M7-R16-R1 to M7-R17

M7-R17 is an additive database and source upgrade.

1. Back up the MariaDB database, application `.env`, `APP_KEY`, and runtime `storage`.
2. Apply `m7-r16-r1-to-m7-r17.patch` to an M7-R16-R1 source tree, or replace the source with the M7-R17 full package while preserving `.env` and runtime `storage`.
3. Install the existing locked dependencies, run the additive migration, and clear cached configuration/routes/views:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

Do not run `migrate:fresh` on an existing installation.

4. Optionally add deployment-level conference settings:

```dotenv
CONFERENCE_HTTP_TIMEOUT_SECONDS=10
JITSI_BASE_URL=https://meet.jit.si
```

5. Open **Organization → Settings** separately for every organization. Configure its Google questionnaire API keys and only the conference providers that organization owns. Existing environment-level Google API keys remain fallbacks. Follow `CONFERENCE-INTEGRATIONS.md` for provider permissions and credential types.
6. Edit each appointment type that should be virtual, select **This is an online appointment**, choose an available provider, and save.
7. Run focused tests followed by the complete suite:

```bash
php artisan test --filter=ConferenceConfigurationTest
php artisan test --filter=ConferenceMeetingServiceTest
php artisan test --filter=GuestBookingFlowTest
php artisan test --filter=AppointmentTypeConfigurationTest
php artisan test --filter=OrganizationDeletionTest
php artisan test
```

Rolling back drops conference credentials and meeting snapshots/URLs. Take a backup first; provider meetings already created outside the application cannot be restored from a database rollback.
