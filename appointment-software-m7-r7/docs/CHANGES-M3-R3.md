# M3 Revision 3

## Current organization indicator

The authenticated backend navigation now shows the active organization name immediately beside the **Log out** button.

The indicator resolves the active organization from the existing `OrganizationContext` on organization-scoped backend routes. On organization-management routes that are intentionally outside the organization middleware, it safely resolves the organization from the authenticated user's active memberships and the `active_organization_uuid` session value.

This revision adds no database migrations.
