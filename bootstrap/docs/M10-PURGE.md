# Organization purge

`organizations:purge` is a server-administrator CLI operation. It is not exposed by the API or website. It preserves the organization row and API key, global persons/users and their client keys, and other organizations' data.

| `--level` | Preserved within the organization |
| --- | --- |
| `all` | Organization identity and keys only; memberships removed |
| `members` | Identity, keys and memberships |
| `resources` | Above plus resources and resource links |
| `types` | Above plus appointment types, their resource assignments and event occurrence definitions |
| `configuration` (default) | Above plus questions, availability schedules, contract templates, galleries/logos, email templates, calendar connections/selections, taxes, payment/conference settings, coupon offers and other configuration |

All levels remove booking history: appointments, holds, attendees, questionnaire answers/uploads, signed contracts, tickets, approvals, confirmations, reminders, schedule changes/proposals, contacts, issued coupons/redemptions, payment/refund/webhook records, local appointment-calendar synchronization records, invitations and OAuth states. No automatic refunds, cancellation emails, provider meeting deletions or remote calendar deletions occur. Remote provider records remain and must be reconciled separately before purging if required.

For levels below `configuration`, supporting configuration tables and uploaded branding are removed. Retained resource/type rows retain their core fields (such as resource quantity, type duration/pricing and event occurrence definitions); review and rebuild missing schedules/integrations/questions before re-enabling bookings. Organization identity, plan, timezone, currency and core profile fields remain. At `all` and `members`, shared physical resource records remain where other organizations reference them, while this organization's resource links are detached. Ownership stays with the preserved organization row. The `all` level also removes owner memberships; a server administrator must restore membership before backend access is possible.

Preview:

```bash
php artisan organizations:purge ORGANIZATION_UUID --level=configuration --dry-run
```

The preview reports root/history counts; cascade children and files are additional. `--dry-run` never changes records or deletes files. Unknown levels and invalid/missing organizations fail.

Before executing, back up the database and private/public uploads, stop scheduled commands and queue workers, and enter maintenance mode. Drain/review pending and failed jobs, including any external queue backend; the command refuses non-empty database `jobs`/`failed_jobs` tables and never clears a global queue.

```bash
php artisan down
php artisan organizations:purge ORGANIZATION_UUID --level=configuration
php artisan up
```

The command asks you to type the exact organization UUID. `--force` skips that prompt for deliberate scripted use but does not bypass maintenance mode or queue checks. Maintenance mode cannot stop an already running worker or scheduled command; stop those separately.

Deletion uses a database transaction with foreign-key checks enabled and the correct dependent-first ordering. If a target calendar is referenced by another organization's appointment types or calendar-sync records, configuration deletion fails and rolls back rather than cascading into that organization.

File deletion tasks are committed with the purge, then processed after the database transaction commits. Files still referenced by surviving records are retained. If storage cleanup fails, the command reports failure and retains the cleanup tasks; retry without deleting more database records:

```bash
php artisan down
php artisan organizations:purge ORGANIZATION_UUID --retry-files --force
php artisan up
```

The current application stores notification history as reminder deliveries and has no organization audit-log table. Global user sessions, global password-reset records and server/application logs are not tenant purge targets.
