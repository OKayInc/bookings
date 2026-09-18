# M6-R1 Verification

Static verification performed during packaging:

- All 277 PHP files pass `php -l`.
- Both shared Blade layouts reference Bootstrap 5.3.8 CSS and bundled JavaScript with SRI hashes.
- Backend layout uses a `navbar-expand-lg` responsive navbar and Bootstrap Collapse toggler.
- Public layout remains separate from backend authentication navigation.
- Every rendered data table in `resources/views` has Bootstrap table classes.
- Database migrations are byte-for-byte unchanged from M6.
- `app/`, `routes/`, `config/`, and `composer.json` are unchanged from M6; this is a presentation-layer revision.
- A new `BootstrapResponsiveUiTest` covers Bootstrap inclusion, responsive backend navigation, current-organization visibility, and the guest/public Bootstrap layout.

The packaging environment cannot run the project's MariaDB-backed Laravel feature suite. Run `php artisan test` in the configured `*_test*` MariaDB environment after applying the revision.
