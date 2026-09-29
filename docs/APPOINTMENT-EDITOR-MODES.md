# Appointment editor Simple and Advanced views

Appointment type configuration has one underlying data model. **Simple** and **Advanced** are presentation modes only.

## Behaviour

- Simple view shows the settings most businesses need for a normal appointment.
- Advanced view shows every appointment-type section.
- Switching views never resets, disables, clears, or changes a saved appointment setting.
- Controls in hidden advanced sections remain part of the same form and keep their existing values.
- If validation fails in an advanced section, the editor automatically opens in Advanced view so the error is not hidden.
- The user's explicit mode choice is remembered in browser local storage.
- Existing appointment types default to Advanced when there is no saved browser preference.
- The new appointment form defaults to Simple when there is no saved browser preference.
- Guided setup defaults to Simple for ordinary business types. events and rental start in Advanced because those answers commonly need specialized controls.
- Expand/collapse affects only sections visible in the current mode.

## Simple sections

Simple view includes:

- Basics
- Access
- Attendance
- Location
- Duration
- Booking notice
- Pricing
- Payment collection and refunds
- Resources and confirmation
- Cancellation policy
- Rescheduling policy
- Status

All other appointment-type sections remain available in Advanced view.

## Responsive layout

On desktop the mode selector and expand/collapse actions share the editor toolbar. On smaller screens the controls become full-width rows, with two equal-width mode buttons and two equal-width section buttons.
