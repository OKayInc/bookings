# M9-R3 verification

## Automated coverage

- option order persists blank as zero and resolves ties alphabetically;
- manual fixed coupons snapshot selected appointment types and calculate a partial-balance discount;
- protected coupon pages reject an incorrect password and reveal the code after unlock;
- QR output is generated as an SVG;
- administrative destruction of an unused purchased coupon submits and records the original-provider refund;
- all new coupon views are included in Blade compilation coverage.

## Workspace verification

- JavaScript tests: 61 passed, 0 failed.
- The dependency-free QR encoder's version 5-L/mask-0 matrix matched the bundled reference implementation with zero differing modules for a protected coupon URL.
- `git diff --check`: clean.

The packaging workspace does not provide PHP, Composer, MariaDB or Memcached. Run the host verification below before deployment.

## Required host verification

```bash
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
php artisan test
./vendor/bin/pint --test
```
