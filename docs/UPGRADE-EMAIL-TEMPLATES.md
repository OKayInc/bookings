# Attendee email templates — September 18, 2026

Based on the complete appointment-to-fixed-event-dates-2026-09-17 archive,
including its latest ticketing, checkout and location edits. This release was
not compared with newer GitHub commits.

## Install

Back up the application and database, deploy the project, then run:

```sh
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

No new Composer or JavaScript dependencies are required.

## Use

Open the organization menu → Email templates (/email-templates).
Owners and administrators can select an email type, edit its subject and body,
choose plain text or HTML, preview a draft with sample values, save, or restore
the built-in default. Changes apply only to the active organization.

All seven existing attendee/customer notification types are covered:
verification, booking access, status changes (including admission decisions,
cancellation and rescheduling), reminders, proposed schedule changes, event
location disclosure, and gift-card/coupon delivery. Staff-only notifications
and backend account authentication emails are unchanged.

The required {{message}} placeholder contains the original dynamic details and
warnings. Customize the surrounding message, greeting and subject. Secure
buttons/links and attachments are always preserved. No raw location placeholder
is offered, so templates cannot disclose a private venue prematurely.

HTML uses the project's existing typography-only policy: bold, italic,
underline, colours and lists. Scripts, external images and user-authored links
are removed. Plain-text delivery is text/plain; HTML delivery includes a plain
text alternative. Placeholder values are escaped in HTML, and user-authored
Blade/PHP is never executed. Preview uses sample data; actual details depend on
the notification. Switching formats preserves the current source draft.

## Verification

JavaScript syntax and static notification coverage checks passed. Added
AttendeeEmailTemplateTest for permissions, tenant isolation, HTML sanitization,
placeholder escaping, required details, header injection, reset, text rendering,
action preservation and attachments. PHP/Composer are unavailable in the build
environment, so the Laravel tests could not be executed here. On the configured
MariaDB test environment, run:

```sh
php artisan test --filter=AttendeeEmailTemplateTest
php artisan test --filter=Booking
php artisan test --filter=M9R11
```

## R1: Blade editor rendering fix

Fixed the editor's HTTP 500 / "Unclosed '(' does not match '}'" error.
Literal placeholder defaults and placeholder labels now come from the
controller instead of appearing inside Blade echo expressions. The regression
test now also checks that literal defaults and placeholder labels appear on the
page, and that a saved custom template renders successfully.

After replacing files, run `php artisan view:clear`, then
`php artisan test --filter=AttendeeEmailTemplateTest`. No additional migration
is needed if the email-template migration has already been applied.
The Laravel tests remain unexecuted in this build environment (PHP unavailable).
