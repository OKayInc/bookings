# External resource sharing

Owners can open Resources → External resource sharing, generate a seven-day, one-time invitation code for another owner's account email, and send it privately. The recipient signs in with that verified email, switches to the receiving organization, and accepts the code. No organization membership is granted.

The recipient configures resource availability and appointment types normally. Both organizations reference the same physical resource, with existing global allocation conflict checks and organization-specific schedules. Active resources count against the receiving organization's existing resource limits. Both organizations must use the same currency because existing resource deposits are stored in minor units.

Either owner may cancel/end their share; accepted shares are protected from removal by the existing same-owner checkboxes. Before ending an accepted share, the receiving organization must remove its appointment and conditional questionnaire assignments, disconnect resource calendars, and resolve active holds and future scheduled appointments. Historical appointments remain intact.

Deployment: run php artisan migrate, php artisan optimize:clear, and php artisan test --filter=SharedResourceTest followed by the external sharing tests and the full suite. Codes are manually delivered; no email delivery service is required.
