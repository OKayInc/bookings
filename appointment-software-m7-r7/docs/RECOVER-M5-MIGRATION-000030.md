# Recover from failed M5 migration 000030

The original M5 migration could fail while adding the first foreign key to `booking_answers` because the constraint name `ba_booking_fk` was already used by `booking_attendees`.

MariaDB may leave the newly created `booking_answers` table behind even though Laravel did not record migration `000030` as completed.

## 1. Apply M5-R1 first

Replace the source with M5-R1 or apply the M5-to-M5-R1 patch.

## 2. Check whether the partial table exists

```sql
SHOW TABLES LIKE 'booking_answers';
```

## 3. Drop only the partial table

Migration `000030` never completed, so there should be no valid application data in this table yet.

```sql
DROP TABLE IF EXISTS booking_answers;
```

Do not drop `appointment_questions` or `question_options`; migrations `000028` and `000029` completed successfully and Laravel has already recorded them.

## 4. Clear caches and continue migrations

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
```

Laravel should rerun `000030`, then continue with `000031` through `000033`.
