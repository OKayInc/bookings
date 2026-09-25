# Delete a user and their organizations

Run from the application directory as a server administrator. Use an email address or user UUID:

```bash
php artisan users:delete user@example.com --with-organizations --dry-run
```

Review the organizations shown. The application has owner memberships, but no historical creator field; the command treats organizations where the user is an owner as owned. It refuses to delete a co-owned organization, one with an active billing subscription, a resource shared with a surviving organization, or a person assigned as a resource at a surviving organization. Resolve these dependencies first. Omitting `--with-organizations` works only for users who own no organizations; other memberships are removed with their person record.

For deletion, put the app in maintenance mode, stop all workers and scheduled tasks, and drain/review `jobs` and `failed_jobs`. Then run:

```bash
php artisan down
php artisan users:delete user@example.com --with-organizations
php artisan up
```

Type the exact email when prompted. `--force` skips this interactive confirmation (for controlled automation only). This operation is permanent. Each organization is deleted through `OrganizationDeletionService`, including its booking data and uploaded files, before deleting the user and person. If a later step fails, earlier organization deletions may already be committed; inspect the output before retrying. The command does not issue refunds, send emails, cancel provider subscriptions, or delete remote calendar events. Back up the database and file storage first.
