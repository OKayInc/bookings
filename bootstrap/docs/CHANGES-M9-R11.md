# M9-R11 — private free events and mystery locations

M9-R11 adds coordinator-reviewed admission for free ticketed events and controlled disclosure of event locations.

## Private admission

- A ticketed event using free pricing can require approval by an event coordinator.
- Every active owner, administrator, and manager with an email address receives a private review link after the prospective attendee submits the form.
- The email and review page include the attendee count, contact details, schedule, and questionnaire answers. Uploaded questionnaire files remain protected by the review token.
- The booking uses the **To confirm** status while it waits. Tickets stay reserved and their QR codes are not exposed to the attendee.
- The first coordinator decision wins. Other coordinator requests are marked superseded to prevent contradictory decisions.
- Coordinators can accept or decline from their emailed review link or from the authenticated booking page. A decision note and responder are retained for the audit trail.
- Acceptance advances the normal email, contract, resource-confirmation, and payment workflow. For a fully free event, the tickets become issued immediately. Decline voids the reserved tickets and releases capacity.

## Mystery locations

Ticketed events may store a venue/location and choose one of three disclosure policies:

- show immediately;
- show after the private admission request is accepted; or
- show to accepted attendees a configured number of hours before the show starts.

Before disclosure, public pages contain only a mystery-location explanation. The exact location remains available to authorized organization staff. When a delayed threshold arrives, the scheduler emails the accepted attendee and the exact location also becomes visible on the private booking page and ticket.

Appointment and booking snapshots preserve the policy used for an existing event when the appointment type is edited later.
