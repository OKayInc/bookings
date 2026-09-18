# M7-R8 changes

M7-R8 completes the organization-member-to-person-resource workflow for users who participate in more than one organization.

## Cross-organization membership

- A person may remain an owner of one organization while accepting an `employee`, `manager`, or `administrator` invitation to another organization.
- The invitation must be sent by the organization where the new membership and person resource will belong.
- The existing backend `user` and `person` records are reused. No duplicate person or user is created.
- The original owner membership remains unchanged when the second membership is accepted.
- A person who owns another organization is not exposed in the current organization's resource picker until they accept a membership invitation to the current organization.

## Person-resource workflow

- The person-resource selector now displays the backend account email first, with the person's primary email as a fallback. This matches the email used for invitation acceptance and booking notifications.
- Only active members of the current organization are selectable and accepted by the resource create/update endpoints.
- Pending invitation emails are shown below the selector with a clear message that acceptance is required before linking.
- Each active member row has a **Create person resource** action that opens the resource form with that member safely preselected.
- A forged or stale preselection for a person who is not an active member of the current organization returns `404` and cannot cross tenant boundaries.

## Schema and deployment

M7-R8 has no database migration, Composer dependency, environment variable, queue change, or scheduled command. Existing memberships, invitations, resources, bookings, and organization roles are preserved.
