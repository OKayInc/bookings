# Upgrade M7-R4-R1 to M7-R4-R2

This is a source-only regression fix. No migration is required.

```bash
php artisan optimize:clear
php artisan test --filter=AppointmentTypeConfigurationTest
php artisan test --filter=AvailabilityEngineTest
php artisan test --filter=SharedResourceTest
php artisan test
```
