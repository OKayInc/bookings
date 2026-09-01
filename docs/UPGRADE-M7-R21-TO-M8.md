# Upgrade M7-R21 → M8

This is an additive upgrade. Do not run `migrate:fresh` on an existing installation.

```bash
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

Deploy the updated PHP, Blade views, routes and CSS assets together. M8 adds no Composer package, API key, environment variable, queue requirement or new scheduled command.

## Post-upgrade checks

1. Open an existing appointment type and save it without enabling ticketing; confirm its public booking flow is unchanged.
2. Create a free group appointment type, enable tickets, set a fixed four-hour duration, show start at 60 minutes and show end at 180 minutes.
3. Test each seating scheme. For a block scheme, ensure its ranges/quantities total capacity; verify gaps in seat numbers are allowed between blocks but duplicate seats are rejected.
4. Book multiple tickets from two client browsers into the same event. Confirm every attendee receives a different ticket and numbered seats do not collide.
5. Open the private booking-management link and print a ticket. Confirm the barcode and human-readable code are both present.
6. Log in as an active employee, open **Scheduling → Ticket check-in**, scan the ticket and confirm a second scan is rejected.
7. Cancel another booking and confirm its tickets become Voided and replacement buyers can receive the released seats.
8. Create a priced event and confirm tickets remain Reserved while the booking is `pending_payment`.

Run the normal verification commands:

```bash
php artisan test
node --test tests/JavaScript/*.test.cjs
./vendor/bin/pint --test
```
