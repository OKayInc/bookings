# M6 Revision 1 — Bootstrap responsive UI

This revision migrates the shared backend and public layouts to Bootstrap 5.3.8 and makes the navigation and core page components responsive.

## Backend

- Replaced the crowded fixed top menu with a Bootstrap responsive navbar.
- Dashboard remains a direct item.
- Appointment Types, Availability, Bookings, and My confirmations are grouped under **Scheduling**.
- Resources, Organizations, and System health are grouped under **Organization**.
- The active organization remains visible immediately beside the Log out control.
- On smaller screens the navigation collapses behind the standard Bootstrap navbar toggler.

## Public/client UI

- Public booking, password, booking-management, and staff-confirmation pages now share the Bootstrap public layout.
- No backend Log in/Register links are added to guest pages.
- Booking cards, forms, slot grids, actions, and tables adapt to narrow screens.

## Forms and tables

Existing Blade form markup is preserved to minimize application risk. `public/css/app.css` bridges those existing fields to Bootstrap's variables, focus states, spacing, and breakpoints. Tables now use Bootstrap table classes and retain horizontal scrolling where required.

## Bootstrap delivery

Bootstrap 5.3.8 CSS and `bootstrap.bundle.min.js` are loaded from the official Bootstrap-documented jsDelivr URLs with SHA-384 Subresource Integrity hashes and `crossorigin="anonymous"`.

No database migration is required.
