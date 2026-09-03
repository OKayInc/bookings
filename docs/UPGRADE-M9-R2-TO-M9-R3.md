# Upgrade M9-R2 to M9-R3

1. Back up the database and application key.
2. Deploy the M9-R3 files.
3. Run `php artisan optimize:clear`.
4. Review `php artisan migrate --pretend` and then run `php artisan migrate`.
5. Run `php artisan test` and `./vendor/bin/pint --test`.
6. In each organization that will sell gift cards, confirm Stripe or PayPal is fully configured under **Organization → Payments**.
7. Create a public offer under **Organization → Gift cards & coupons**, complete one sandbox purchase, open the protected link, scan the QR code, redeem part of a fixed card, and destroy/refund a second unused sandbox card.

No data backfill is required. Existing questionnaire options retain their stored positions; tied positions now use their labels as the deterministic secondary order.
