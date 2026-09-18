# Upgrade M7-R16 to M7-R16-R1

M7-R16-R1 is a test-only compatibility revision. It adds no migration and does not change distance-fee configuration or booking behavior.

1. Apply `m7-r16-to-m7-r16-r1.patch` to an M7-R16 source tree, or replace the source with the M7-R16-R1 full package while preserving `.env` and runtime `storage`.
2. Run the focused regression test, then the complete suite:

```bash
php artisan test --filter=QuestionnaireConfigurationTest
php artisan test
```

No cache clear, worker restart, or `php artisan migrate` command is required for an installation already running M7-R16. If upgrading from M7-R15 or earlier, first follow the M7-R16 upgrade guide.
