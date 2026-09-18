# M5 Revision 5

Documentation/upgrade correction for the M5 phone validation dependency.

- No production PHP code changes.
- `giggsey/libphonenumber-for-php-lite` was already correctly declared in `composer.json`.
- Existing installations may have a pre-M5 `composer.lock`; the upgrade instructions now use `composer update giggsey/libphonenumber-for-php-lite --with-dependencies` rather than relying on `composer install`.
