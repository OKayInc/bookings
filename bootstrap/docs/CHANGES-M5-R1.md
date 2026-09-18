# M5 Revision 1

## Fixed

MariaDB/InnoDB requires explicit foreign-key constraint names to be unique across the database schema. M5 accidentally reused `ba_booking_fk` in both:

- `2026_08_24_000023_create_booking_attendees_table.php`
- `2026_08_24_000030_create_booking_answers_table.php`

This caused migration `000030` to fail with MariaDB errno 121.

M5-R1 renames the two `booking_answers` constraints to:

- `bans_booking_fk`
- `bans_question_fk`

The names are short enough for MariaDB's identifier limit and are unique across all project migrations.

## Regression protection

Added `MigrationForeignKeyNameTest`, which scans all migration files and fails if an explicit foreign-key constraint name is reused anywhere in the schema.

## Database recovery

If M5 `000030` already failed on an installation, the partially created `booking_answers` table must be dropped before rerunning the migration. See `RECOVER-M5-MIGRATION-000030.md`.
