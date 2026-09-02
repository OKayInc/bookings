# M9-R1 verification

## Automated coverage added

- overlapping appointments consume only their equipment piece counts;
- the next slot reports 19 of 20 pieces after one is held;
- a hold that would exceed remaining stock is rejected;
- hold and appointment pivots preserve reserved quantities;
- per-piece, exact-bundle, fixed and free pricing are combined correctly;
- equipment charges are persisted as booking price-line snapshots;
- joining a group session does not double-count its equipment;
- legacy equipment with quantity tracking disabled remains compatible with replacement-resource groups;
- explicit quantity tracking drives stock availability independently of the resource type;
- replacement payloads bypass piece-quantity validation and persist quantity one even if tracking is enabled;
- unused resources can be deleted while booking/appointment history blocks deletion;
- numeric answer rates persist and calculate decimal answer × rate charges;
- the modified resource, appointment-type, checkout and booking views are included in Blade compilation coverage.

## Workspace verification

- JavaScript tests: 57 passed, 0 failed, including the explicit quantity toggle, numeric rate editor, and all existing booking/payment editors.
- The four modified inline JavaScript surfaces passed a Node syntax parse after substituting server-rendered URL/value expressions.
- `git diff --check`: clean.

The packaging workspace does not provide PHP, Composer, MariaDB or Memcached. Run the host verification below before deployment.

## Required host verification

```bash
php artisan optimize:clear
php artisan migrate --pretend
php artisan test
./vendor/bin/pint --test
```

On a staging organization, configure 20 chairs and two overlapping appointment types requiring 1 and 5. Hold the first slot, confirm that the second reports 19 of 20 available, then reserve the second and confirm both appointment allocations and price lines.
