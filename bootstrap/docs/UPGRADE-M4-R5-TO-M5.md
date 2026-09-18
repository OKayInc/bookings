# Upgrade M4-R5 → M5

M5 is an additive upgrade. Existing organizations, appointment types, schedules, bookings, contacts, invitations and contracts are preserved.

## 1. Back up

Back up the application directory and MariaDB database before upgrading.

## 2. Apply source

Apply `m4-r5-to-m5.patch` from the application root or replace the source with the M5 package while preserving `.env` and runtime storage.

## 3. Install the new Composer dependency

M5 adds `giggsey/libphonenumber-for-php-lite` for phone-number parsing and validation:

```bash
composer update giggsey/libphonenumber-for-php-lite --with-dependencies --no-interaction
```

## 4. Configure questionnaire validation

Add as needed to `.env`:

```dotenv
GOOGLE_MAPS_API_KEY=
QUESTIONNAIRE_DEFAULT_PHONE_REGION=CA
QUESTIONNAIRE_EMAIL_DNS_VALIDATION=true
QUESTIONNAIRE_FILE_DISK=local
```

Address questions require a Google Maps Platform server API key with Address Validation API enabled.

## 5. Clear caches and migrate

```bash
php artisan optimize:clear
php artisan migrate
```

M5 adds migrations `000028` through `000033` only.

## 6. Run tests

```bash
php artisan test
```

Continue using the dedicated MariaDB test database configured for this project.

## 7. Configure a questionnaire

Backend → Appointment Types → Questionnaire.

No questionnaire is created automatically for existing appointment types, so existing guest booking behavior remains unchanged until questions are added.
