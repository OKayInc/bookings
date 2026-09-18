# M7-R4-R3 changes

- Fixed organization switching so the selected organization is persisted on the backend user instead of relying only on session state.
- Added nullable `users.active_organization_id` as the durable active-organization preference.
- The active organization resolver now prefers a valid persisted organization, then a valid legacy/session selection, then the first active membership.
- Session `active_organization_uuid` remains synchronized for compatibility with existing routes/tests and browser state.
- Creating a new organization makes it active persistently.
- Initial registration persists the first organization as active.
- Calendar OAuth return persists the OAuth organization when the initiating user is still authenticated.
- Added regression coverage proving that a stale session after switching cannot revert the user to the first organization.
