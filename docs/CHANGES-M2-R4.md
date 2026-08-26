# M2 Revision 4

Fixes browser-side form submission for conditional appointment-type fields.

## Root cause

M2 hid conditional fields with CSS (`display: none`) but left their form controls enabled. HTML5 constraint validation still validates enabled controls inside CSS-hidden sections. A legacy M1 appointment has `attendance_mode=single` and `capacity=1`; the M2 capacity input has `min=2`, so the browser rejected the form before sending a request to Laravel.

## Fix

The appointment-type form now:

- disables every input/select/textarea inside an inactive conditional section;
- re-enables controls when their section becomes active;
- dynamically sets `required` only for fields relevant to the current mode;
- applies the same behavior to capacity, fixed/variable duration fields, fixed/rate pricing fields, and password access fields.

This also prevents stale values from inactive configuration modes being submitted.

No migration or database change is required.
