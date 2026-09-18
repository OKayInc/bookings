# M7-R4-R3 verification

- Added `users.active_organization_id` as `BINARY(16)` nullable FK to `organizations.id`.
- Added short explicit MariaDB identifiers `users_active_org_idx` and `users_active_org_fk`.
- Organization switch writes both the database preference and session UUID.
- Middleware treats the persisted preference as authoritative when it still corresponds to an active membership.
- Legacy/session selection remains a fallback for users without a persisted preference.
- Regression test simulates stale session state after the switch POST and requires the dashboard/session to resolve the persisted organization.
