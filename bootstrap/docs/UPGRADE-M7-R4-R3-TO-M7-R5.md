# Upgrade M7-R4-R3 to M7-R5

M7-R5 is an additive upgrade. It preserves all existing users, organizations, memberships, resources, appointments, bookings, calendars, and stored files.

1. Back up the MariaDB database and application `.env`.
2. Apply the M7-R5 source patch or replace the source tree while preserving `.env` and runtime `storage`.
3. Optionally configure the invitation lifetime:

```dotenv
ORGANIZATION_MEMBER_INVITATION_TTL_DAYS=7
```

4. Clear cached application state and run the additive migration:

```bash
php artisan optimize:clear
php artisan migrate
```

M7-R5 adds:

- `2026_08_27_000049_create_organization_member_invitations_table.php`

5. Run focused and full tests:

```bash
php artisan test --filter=OrganizationMemberInvitationTest
php artisan test --filter=BookingResourceNotificationTest
php artisan test
```

No Composer dependency, queue change, or new scheduled command is required. Working outbound mail remains required for invitations and booking notifications.
