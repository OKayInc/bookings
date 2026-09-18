# M7-R4-R2 changes

- Fixed the `Resource` created hook so database defaults are reloaded before the owning organization-resource pivot is created.
- Resources created without explicitly providing `is_required_by_default` now correctly inherit MariaDB's default `true` value instead of being mirrored to `organization_resources` as optional.
- This fixes the appointment-type confirmation regression and the resource-availability intersection regression introduced by M7-R4.
- Added a regression test covering direct `Resource::create(...)` calls that omit `is_required_by_default`.
- No migration, schema, Composer, route, or configuration changes are required.
