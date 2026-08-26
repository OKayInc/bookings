# M2 Revision 5

## Guest/client identity separation

M2-R5 separates backend authentication from public client identity.

- Backend staff continue to use `users -> persons -> organization_memberships` and Laravel authentication.
- Public clients do **not** register and do **not** receive a `users` row.
- New `organization_contacts` records hold organization-scoped customer/contact identity.
- Email is the primary client identifier and is normalized for matching.
- The same email may exist independently at different organizations.
- Public appointment pages use a dedicated guest layout with no **Log in** or **Register** prompts.

Attendees remain a separate booking concept for M4. A group/class booking will not require every attendee to have an email or a contact record.

## Email verification

Appointment types now have `email_verification_mode`:

- `none`
- `before_confirmation` (default)
- `before_payment`

M4 will implement the actual email-verification token workflow. M2-R5 stores the policy now so booking behavior can depend on it later. For a free appointment configured as `before_payment`, M4 will treat verification as required before confirmation because no payment step exists.

## Passwordless client management

No customer password/account system is added. M4 will use signed, expiring email links for actions such as viewing a booking, contract upload, cancellation/rescheduling, and later outstanding payments.

## Database

Two additive schema changes are included:

1. `organization_contacts`
2. `appointment_types.email_verification_mode`

`organization_contacts` uses UUIDv7/BINARY(16) identifiers and an organization-scoped unique normalized-email index named `oc_org_email_uq`.

## Upgrade

Run:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
```

Do not run `migrate:fresh` on an existing installation.
