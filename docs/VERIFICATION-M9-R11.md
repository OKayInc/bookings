# M9-R11 verification

## Checks completed in this workspace

- `git diff --check` passes.
- Private-event configuration, validation, booking snapshots, coordinator authorization, first-response locking, ticket lifecycle, and location privacy paths were reviewed together.
- The new feature suite covers pending admission, hidden QR tickets, acceptance, decline, multiple coordinators, questionnaire visibility, and delayed location release.
- `M9R11PrivateTicketedEventTest` passes: 4 tests and 34 assertions.
- The private-event and existing ticketing suites pass together: 14 tests and 103 assertions.
- The complete test suite passes: 434 tests and 2,545 assertions.

The test runs used PHP 8.4 with MariaDB 10.11. Production deployment should still run the normal deployment checks:

```bash
php artisan migrate:fresh --env=testing
php artisan view:cache --env=testing
php artisan test --filter=M9R11PrivateTicketedEventTest
php artisan test
```

Production deployment should follow `UPGRADE-M9-R10-TO-M9-R11.md` and use the deployment environment's normal MariaDB configuration.
