# M7-R13 changes

M7-R13 adds guarded, permanent organization deletion and defines safe behavior for resources shared between organizations.

## Owner-only danger zone

- Only an active organization owner is authorized to see or submit the delete action. Administrators, managers, and employees cannot delete an organization.
- The action is available in the organization editor's **Danger zone**.
- The owner must enter the organization name exactly and re-enter their current account password.
- The interface lists the destructive scope and requires a final browser confirmation.

## Deletion scope

Deletion removes the organization and all organization-owned operational data, including:

- memberships and pending member invitations;
- appointment types, reusable questions, invitations, schedules, holidays, and short-notice rules;
- contacts, holds, appointments, bookings, questionnaire answers, price lines, reminders, confirmations, and schedule-change history;
- contract templates/submissions, signed files, questionnaire uploads, organization/appointment logos;
- calendar connections, OAuth states, external-calendar mappings, and organization-owned resources.

Membership deletion does not delete the related person or backend user account. Those identities may still own or belong to another organization.

Database deletion runs in one transaction with booking history removed before rows protected by restrictive foreign keys. Stored files are collected before deletion and removed after the database commit. A storage cleanup failure is logged and does not recreate already-deleted database data.

## Shared resources

- A resource owned by another organization is detached from the organization being deleted and remains available to its actual owner.
- A resource owned by the organization being deleted is first unshared from every other organization. Its external appointment-type assignment, resource availability schedule, calendar connection, and pending OAuth state are removed before the resource is permanently deleted.
- Deleting an owned resource also removes its foreign-key-backed historical resource pivots and confirmations. The other organization's appointment and booking rows remain intact.

## Active organization recovery

After deletion, the deleting owner's active organization is resolved again. The application selects another active membership when one exists or redirects to organization creation when the user no longer belongs to any organization.

M7-R13 has no database migration, new dependency, environment variable, queue, or scheduled-command change.
