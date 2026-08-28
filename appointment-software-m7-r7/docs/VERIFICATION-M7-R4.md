# M7-R4 verification

Static verification performed in the packaging environment:

- PHP syntax validation passes for application, configuration, migrations, routes, and tests.
- Existing `resources.organization_id` remains the owning organization; no destructive resource migration is performed.
- `organization_resources` is additive and backfills every existing resource to its owner organization.
- Availability remains keyed by `organization_id + scope_type + scope_id`; resource availability resolution now requires the appointment organization explicitly.
- Holds and appointments continue querying by physical `resource_id` without organization filtering, preserving cross-organization double-booking protection.
- Calendar connection uniqueness is expanded to organization + resource + provider so a shared resource can have organization-specific calendar configuration.
- New shared-resource regression tests cover sharing, independent organization availability, and cross-organization hold conflicts.

The packaging environment does not contain Composer/vendor or MariaDB/Memcached services. Run the full Laravel suite on the deployment/test host.
