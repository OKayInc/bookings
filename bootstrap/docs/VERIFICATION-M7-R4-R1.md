# M7-R4-R1 verification

- The repaired migration passes PHP syntax validation.
- A dedicated `cc_resource_fk_idx(resource_id)` is created before `cc_resource_provider_uq` is dropped.
- `organization_resources` creation is guarded with `Schema::hasTable()` for MariaDB partial-DDL recovery.
- Backfill remains idempotent through `insertOrIgnore`.
- No source files outside the repaired migration and R4-R1 documentation changed from M7-R4.
- The release patch is generated from M7-R4 and verified to reproduce the M7-R4-R1 tree.
