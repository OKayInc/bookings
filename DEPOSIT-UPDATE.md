# Booking deposits: staff waiver and total override

Based on the complete Appointment.to M10 project archive.

## Behavior

- New booking quotes waive the entire refundable resource deposit when a person resource is involved. Equipment-only rentals keep their configured deposits.
- An unavailable/unallocated person does not waive a deposit. Conditional person resources only qualify when their question is triggered.
- Owners/managers with scheduling permission can open a booking and use **Deposit settings → Override global deposit**. The amount replaces the whole booking deposit; zero explicitly waives it. A manual override takes precedence over the automatic waiver.
- Unchecking the option restores the original deposit snapshot, rather than recalculating using resource prices that may have changed.
- The total, subtotal, initial payment due, deposit breakdown, and booking status update together. Service charges, taxes and coupon discounts stay as originally booked.
- Changes are allowed before any payment transaction has been created. Collected deposits use the existing refund action. Closed bookings cannot be changed. Private free events cannot acquire a positive deposit.
- Existing bookings are not automatically repriced; their original financial records are retained.

## Install

Deploy the full project with your existing environment configuration, then run:

```sh
php artisan migrate --force
php artisan optimize:clear
```

## Verification

Added regression cases to `tests/Feature/M9R6ResourceDepositTest.php` for allocated/unallocated staff, conditional staff, explicit zero, a higher override, restoring the original deposit, invalid negative amounts, collected-payment protection and organization isolation.

```sh
php artisan test --filter='M9R6ResourceDepositTest|M9R7PersonDepositTest'
```

PHP/Composer are unavailable in the editing environment, so the Laravel test suite and PHP lint could not be executed here. The new browser script passed Node syntax checking. Review the regression test results on your configured MariaDB testing environment before deploying to production.
