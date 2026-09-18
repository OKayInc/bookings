# M7-R4-R1 changes

- Fixed MariaDB error 1553 in migration `2026_08_27_000047_create_organization_resources_table.php`.
- `calendar_connections.cc_resource_provider_uq` was also serving as the supporting index for foreign key `cc_resource_fk`; M7-R4 attempted to drop it without first adding another index beginning with `resource_id`.
- The migration now creates `cc_resource_fk_idx(resource_id)` before removing the old unique index.
- The migration is now safe to rerun after MariaDB partially committed DDL from the failed M7-R4 attempt.
- Existing `organization_resources` is detected instead of recreated.
- Resource backfill is always rerun with `insertOrIgnore`, so a partially populated pivot is repaired safely.
- No application behavior, shared-resource semantics, availability behavior, or model APIs changed.
