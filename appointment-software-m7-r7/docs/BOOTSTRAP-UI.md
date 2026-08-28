# Bootstrap UI

Appointment Software uses Bootstrap 5.3.8 for both the authenticated backend and guest-facing UI.

## Navigation

The backend uses `navbar-expand-lg`. On desktop, the main groups are visible horizontally. Below the large breakpoint, Bootstrap Collapse exposes them through the navbar toggler.

The backend groups are intentionally compact:

- Dashboard
- Scheduling
  - Appointment Types
  - Availability
  - Bookings
  - My confirmations
- Organization
  - Resources
  - Organizations
  - System health

The active organization and Log out control remain together on the right side of the desktop navbar and inside the collapsed area on mobile.

## Public UI

Guest pages use a separate Bootstrap layout and never expose backend authentication navigation. The organization name is used as the public navbar brand when available.

## Asset delivery

Bootstrap is loaded from jsDelivr using the versioned URLs and SRI hashes published in Bootstrap's official 5.3 quick-start documentation.

The application-specific `public/css/app.css` loads after Bootstrap and contains only Appointment Software layout/component adjustments and compatibility styling for existing Blade form markup.

## Responsive behavior

- Form rows become one-column below Bootstrap's `md` breakpoint.
- Wide data tables scroll horizontally on narrow screens.
- Slot choices use a responsive CSS grid.
- Appointment hero areas stack vertically on phones.
- Page action/header groups wrap rather than overflow.
- The backend navbar collapses below Bootstrap's `lg` breakpoint.
