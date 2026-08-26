# Guest clients and organization contacts

## Separation from backend users

Backend staff have authenticated Laravel `users` linked to `persons` and organization memberships. Guest clients are not backend users.

A guest client is represented by `organization_contacts` and has no password.

## Organization scope

A contact belongs to exactly one organization. The same normalized email may therefore exist in multiple organizations without sharing customer history or private data between them.

Within one organization, normalized email is unique.

## Email identity

Email is normalized by trimming whitespace and lowercasing before matching. The original trimmed email is also retained for display/use.

An appointment type controls whether that email needs verification. M4 will implement signed, expiring verification links.

## Attendees

A future booking may contain multiple attendees. Those attendees do not automatically become organization contacts and do not each need an email address. The booking's primary client/contact is the person responsible for the booking.

## Passwordless management

M4 will send signed, expiring email links so clients can manage permitted booking actions without registration, including contract upload and later cancellation/rescheduling/payment actions.
