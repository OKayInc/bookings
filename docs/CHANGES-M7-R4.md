# M7-R4 changes

- Resources can be shared with other organizations owned by the same backend user.
- `resources.organization_id` remains the owning organization for lifecycle/administration.
- Added `organization_resources` pivot and backfilled every existing resource into its owner organization.
- Organization resource listings, appointment-type resource selection, availability management, and calendar connections include shared resources.
- Resource availability is resolved explicitly by `(organization, resource)`; a shared resource may have different hours/exceptions in each organization.
- Existing bookings and holds still block the physical shared resource globally across organizations, preventing double booking.
- External calendar connections remain organization-scoped.
- Sharing is managed from the owning organization's Resource edit form and is limited to organizations where the current user is an active Owner.
- Default required/optional behavior is stored per organization-resource link and can be changed from the shared organization without changing the resource globally.
