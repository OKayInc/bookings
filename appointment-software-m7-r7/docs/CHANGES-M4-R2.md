# M4 Revision 2

Fixes the invite-only public appointment regression test.

The public invite-only appointment page now labels recipient-bound links as:

`Recipient-specific invitation for <email>`

This restores the explicit wording expected by `AppointmentTypeAccessTest` and makes it clearer that the invitation is tied to the configured recipient email.

No database migrations or application configuration changes are required.
