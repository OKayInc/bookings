# M1 Revision 4

## Fix: Person model table mapping

Laravel/Eloquent pluralizes the model name `Person` to `people`. M1 intentionally creates the database table as `persons`, so previous revisions could fail during registration with:

```text
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'appointment.people' doesn't exist
```

Revision 4 explicitly maps the model to the existing table:

```php
protected $table = 'persons';
```

No database migration or schema change is required for this fix.

## Upgrade from R3

Replace `app/Models/Person.php` with the R4 version, or apply the supplied R3-to-R4 patch. Then clear Laravel caches:

```bash
php artisan optimize:clear
```

There is no need to run `migrate:fresh` or delete existing data.
