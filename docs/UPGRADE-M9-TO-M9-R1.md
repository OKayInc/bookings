# Upgrade M9 to M9-R1

M9-R1 is an additive database and application upgrade. Do not run `migrate:fresh` on an existing installation.

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
./vendor/bin/pint --test
```

Migration `2026_09_02_000064_add_m9_r1_equipment_inventory.php` adds:

- `resources.inventory_quantity`;
- quantity and equipment-pricing fields on `appointment_type_resources`;
- `quantity_reserved` on `booking_hold_resources` and `appointment_resources`.

Migration `2026_09_02_000065_add_quantity_enabled_to_equipment_resources.php` adds `resources.quantity_enabled`. Equipment previously configured with stock greater than one is enabled automatically. Legacy equipment remains binary and compatible with replacement groups until quantity tracking is explicitly enabled.

Existing records are preserved with quantity `1` and free equipment pricing. After deployment, edit each interchangeable equipment resource, enable quantity tracking, enter its real stock, then edit each appointment type that uses it to set the required quantity and rental pricing.

No new Composer package, worker or scheduled task is required.
