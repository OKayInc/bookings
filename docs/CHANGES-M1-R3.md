# M1 Revision 3

## MariaDB identifier-length fix

MariaDB limits identifiers such as index names to 64 characters. Laravel's default
index naming convention can exceed that limit when table and column names are long.

This revision assigns explicit short names to the affected indexes in the M1
migrations:

- `appointment_contract_templates (appointment_type_id, active_slot)` → `act_type_active_uq`
- `appointment_contract_templates (appointment_type_id, is_active)` → `act_type_active_idx`
- `appointment_contract_templates (organization_id, created_at)` → `act_org_created_idx`
- `appointment_type_resources (appointment_type_id, resource_id)` primary command → `atr_primary`

The first two are the important MariaDB 64-character fixes. The other composite
names are explicitly shortened for consistency and future portability.

## Recovering from the failed M1-R2 migration

If migration `2026_08_21_000008_create_appointment_contract_templates_table.php`
failed with error 1059, MariaDB may have already created the table because DDL can
autocommit even though Laravel did not record the migration as completed.

If this is a fresh M1 installation and there is no data to preserve, run:

```bash
php artisan migrate:fresh --seed
```

If you want to preserve the already-created M1 tables/data, remove only the table
from the failed migration and rerun migrations:

```sql
DROP TABLE IF EXISTS appointment_contract_templates;
```

then:

```bash
php artisan migrate
```
