# M7-R5 changes

M7-R5 adds organization-member onboarding and immediate booking notifications for linked person resources.

## Organization-member invitations

- Owners and administrators can open **Organization → Members** and invite a person by email.
- Invitations may grant the existing non-owner roles: `administrator`, `manager`, or `employee`.
- The `owner` role cannot be granted through this workflow.
- Invitation tokens are random, stored only as SHA-256 hashes, bound to the recipient email, revocable, single-use, and expire after seven days by default.
- A new recipient creates a backend `user` and `person`, then receives an active `organization_membership` without creating or owning an organization.
- A recipient who already has a backend account must log in as the invited email address before accepting. Their existing person/user record is reused.
- Newly created accounts still follow M7 backend email verification before they can access verified backend routes.
- Accepted members immediately become available in the existing person-resource selector.

The new `organization_member_invitations` table preserves inviter, recipient, intended role, expiration, revocation, and acceptance audit data. Raw invitation tokens are never stored.

## Linked resource booking email

When a booking is committed, every assigned resource linked to a person with an email receives an immediate booking-assignment message. The message includes the appointment type, assigned resource name, local scheduled time, client name, booking status/reference, and an authenticated backend link.

- The backend user's current email is preferred; the person's primary email is the fallback.
- If one person is linked to multiple resources in the same booking, only one email is sent and all of that person's resource names are listed.
- If the normal staff-confirmation workflow already sent that resource a confirmation-request email during booking creation, M7-R5 does not also send the generic assignment email.
- Assignment-email delivery is best-effort after the database booking transaction; a mail transport failure does not roll back an already committed booking.

## Configuration

The invitation lifetime can be changed with:

```dotenv
ORGANIZATION_MEMBER_INVITATION_TTL_DAYS=7
```
