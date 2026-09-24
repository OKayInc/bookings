# Appointment.To

> **Flexible appointment scheduling for real businesses — from simple one-to-one bookings to events, rentals, ticketing, questionnaires, payments, resources, customer history, and automation.**

Appointment.To is a multi-tenant appointment and event scheduling platform built for organizations that need more than a basic calendar link.

It is designed to serve very different business models from the same platform: photographers, consultants, service businesses, rental companies, event organizers, venues, classes, group activities, private events, and other organizations that need configurable booking workflows without maintaining separate scheduling systems.

The platform combines a customer-friendly public booking experience with deep administrative controls for availability, resources, pricing, questionnaires, payments, policies, customer history, integrations, branding, and operational workflows.

---

## Why Appointment.To?

Most scheduling applications work well when every appointment follows the same pattern.

Real businesses are rarely that simple.

A booking may require:

- a specific employee;
- one of several interchangeable resources;
- multiple pieces of equipment;
- a room or location;
- a questionnaire;
- a deposit;
- a retainer;
- a distance surcharge;
- a short-notice fee;
- taxes;
- a coupon;
- approval before confirmation;
- a contract;
- a calendar conflict check;
- an event ticket;
- a QR code;
- a private location revealed later;
- or a completely different workflow from the next appointment type.

Appointment.To is built around that reality.

Instead of forcing every organization into a single scheduling model, it provides a configurable booking engine that can adapt to the way each business actually operates.

---

# Business Capabilities

## Multi-Organization Platform

A single user account can participate in or operate multiple organizations.

Organizations maintain their own:

- appointment types;
- team members;
- resources;
- locations;
- branding;
- policies;
- taxes;
- integrations;
- calendars;
- customers;
- questionnaires;
- pricing;
- payment configuration;
- galleries;
- webhooks;
- and operational settings.

This allows consultants, agencies, franchise operators, event companies, and multi-brand businesses to manage multiple booking environments without maintaining separate installations.

---

## Roles and Permissions

Appointment.To supports organization-level access control.

Typical roles include:

- **Owner**
- **Manager**
- **Employee**

Administrative actions are protected through Laravel authorization policies so that users only see or modify the areas appropriate to their role.

Higher-privilege users can also perform operational tasks such as reviewing customer history, managing policies, handling appointment outcomes, and responding to events that require approval.

---

# Appointment Types

Each organization can create multiple appointment types with independent booking rules.

Examples include:

- consultations;
- photography sessions;
- equipment rentals;
- service calls;
- classes;
- group appointments;
- venue bookings;
- private events;
- ticketed events;
- online meetings;
- and admission-based activities.

Each appointment type can define its own configuration without requiring a separate application.

The administrative editor uses grouped, collapsible configuration sections so advanced configuration does not overwhelm users who only need a simple setup.

---

## Guided Initial Setup

Appointment.To can provide a simplified starting point when an organization is created.

Instead of requiring a new business owner to understand every advanced scheduling option immediately, the system can use an initial set of questions to establish a sensible base configuration.

The resulting appointment type remains fully editable afterward.

This allows Appointment.To to remain powerful without forcing every user to become a scheduling-system expert.

---

# Availability and Scheduling

Appointment.To provides configurable availability rules for each appointment type.

Capabilities include:

- one-to-one appointments;
- group appointments;
- configurable appointment duration;
- fixed-duration events;
- configurable booking increments;
- minimum booking notice;
- maximum advance booking window;
- booking seasons;
- availability periods;
- resource-aware availability;
- calendar conflict detection;
- appointment buffers;
- fixed event dates;
- timezone-aware scheduling;
- organization holidays;
- resource-specific holidays;
- country-based holiday support;
- and availability previews.

The platform uses IANA timezones and is designed to support database-side timezone conversion where required.

---

## Availability Preview

Administrators can inspect a visual availability timeline before publishing or troubleshooting an appointment type.

The preview helps show how required and optional resources affect a potential booking window.

This is particularly useful when a booking depends on several simultaneous constraints.

---

# Resource Management

Resources are one of the core concepts in Appointment.To.

A resource can represent almost anything required to fulfill a booking.

Examples include:

- employees;
- photographers;
- technicians;
- rooms;
- vehicles;
- studios;
- equipment;
- projectors;
- tables;
- chairs;
- rental inventory;
- or other limited assets.

---

## Required and Optional Resources

Appointment types may use:

- required resources;
- optional resources;
- or combinations of both.

This lets the system determine whether a booking can proceed while still allowing optional extras when available.

---

## Replacement Resource Groups

Resources can be grouped as alternatives.

For example:

> Photographer A **OR** Photographer B

or:

> Room 1 **OR** Room 2

The booking engine can select availability based on replacement groups rather than requiring one specific resource.

This is useful for organizations where several people or assets can perform the same function.

---

## Conditional Resources

Resources can be made conditional on questionnaire answers.

For example:

> **Do you want video coverage?**

If the customer selects **Yes**, a video resource group can become required.

If the required resource is not available, the system can hide or constrain the related option according to the appointment configuration.

This allows questionnaire choices to affect operational availability instead of merely collecting information.

---

## Quantity-Managed Equipment

Equipment can be managed by quantity.

Instead of representing every identical unit as a separate resource, the system can track how many units are required and how many remain available during overlapping appointments.

This is useful for:

- rental inventory;
- chairs;
- tables;
- microphones;
- lighting equipment;
- projectors;
- cameras;
- and other interchangeable assets.

---

## Shared Resources

Resources can be shared between organizations belonging to the same owner where appropriate.

This makes it possible for multiple brands or business units to compete for the same physical inventory or personnel.

---

# Locations

Organizations can manage reusable location definitions.

Appointment types can be:

- at a physical location;
- online;
- at a customer location;
- or configured with event-specific location rules.

Online appointments can use meeting or event URLs instead of physical addresses.

Location configuration can also participate in caching for faster public booking performance.

---

# Questionnaires

Appointment.To includes a configurable questionnaire engine.

Question types can be used to collect information before the booking is finalized.

Questionnaires may support:

- required or optional questions;
- long-form text;
- help text;
- numeric answers;
- conditional questions;
- AND/OR dependency rules;
- answer validation;
- answer-based pricing;
- attendee-related comparisons;
- and resource dependencies.

Rich text can be used in appropriate questionnaire help and descriptive fields.

---

## Conditional Logic

Questions can depend on previous answers.

This allows appointment forms to remain short and relevant while still supporting complex workflows.

For example:

- ask about video only when the customer selected a package that supports video;
- ask for delivery information only when physical delivery is selected;
- ask equipment-specific questions only when that equipment is requested.

Dependencies can use **AND** and **OR** conditions.

---

## Numeric Questions

Numeric answers can be validated against configured limits and can participate in pricing or business rules.

Examples include:

- number of attendees;
- number of rental units;
- distance;
- quantities;
- package counts;
- or other measurable values.

---

# Pricing

Appointment.To supports flexible pricing models.

Depending on the appointment type, pricing can include:

- a fixed base price;
- duration-based pricing;
- attendee-based pricing;
- questionnaire surcharges;
- fixed question-based charges;
- percentage-based charges;
- resource-based pricing;
- short-notice fees;
- distance-related fees;
- taxes;
- deposits;
- retainers;
- coupons;
- gift cards;
- and administrator-defined overrides.

The customer-facing checkout can display a clear subtotal, tax calculation, and final total.

---

# Taxes

Organizations can configure multiple taxes.

Tax configuration can include whether a tax is:

- added on top of the displayed price; or
- already included in the advertised price.

Organizations can also store relevant tax identification information.

This is useful for businesses operating in jurisdictions where different taxes or tax presentation rules apply.

---

# Deposits and Retainers

Appointment.To supports deposit and retainer workflows.

Deposit rules can be defined at different levels depending on the booking configuration.

The platform supports cases where:

- a resource defines a deposit;
- a questionnaire answer changes the required deposit;
- an appointment type overrides the global deposit;
- a deposit is waived when the booking includes a person resource;
- or no deposit is required.

Refund workflows can support full or partial refunds with a recorded reason.

---

# Coupons

Organizations can create coupons that apply:

- a fixed discount; or
- a percentage discount.

Coupons can be scoped to:

- all eligible appointment types; or
- selected appointment types.

Expiration can be optional.

Coupon usage can also be surfaced in customer history so businesses can understand how a customer booked.

---

# Gift Cards

Appointment.To supports gift-card workflows, including:

- public purchase;
- administrative creation;
- fixed-value cards;
- delivery by email;
- printable delivery;
- appointment redemption;
- expiration rules where configured;
- and administrative handling of unused cards.

Gift cards are distinct from promotional coupons and are treated as stored booking value rather than a simple discount code.

---

# Payments

Payment collection can be configured per organization.

The platform is designed to support integrations such as:

- **Stripe**
- **PayPal**

Depending on the appointment type, organizations can support:

- free bookings;
- full payment;
- deposits;
- retainers;
- refunds;
- and other configured payment rules.

Free appointment types can hide payment-related configuration that is not relevant to the customer experience.

---

# Customer History

Appointment.To can group appointment history by customer using identifying information such as email address or phone number.

Business users can review a customer’s previous activity without manually searching individual bookings.

Customer history can include:

- previous appointments;
- appointment status;
- completed appointments;
- cancellations;
- no-shows;
- booking revenue;
- coupon usage;
- and operational notes or policy status where applicable.

This gives businesses a lightweight customer relationship view directly inside the scheduling platform.

---

## Appointment Outcome Tracking

After an appointment, authorized users can record whether the appointment was successfully completed or whether the customer was a no-show.

Higher-privilege users can optionally receive a follow-up email asking them to confirm the appointment outcome.

The response is optional and does not block normal operation.

---

## Whitelist and Blacklist Policies

Appointment.To can support customer policy rules that suggest or automatically manage whitelist and blacklist status.

Where a customer is added automatically, the interface can indicate that the action resulted from a policy rather than a manual administrator decision.

This creates an auditable distinction between:

- manually managed customer status; and
- automatically applied business rules.

---

# Booking Confirmation and Approval

Appointment types can require confirmation instead of immediately finalizing a booking.

Depending on the workflow, authorized staff can:

- accept;
- decline;
- or propose an alternate time.

This is useful for appointments where availability alone is not enough to guarantee acceptance.

---

# Private Events and Approval Workflows

Free ticketed events can operate as private events.

A private-event workflow may:

1. collect attendee information;
2. temporarily hold the booking;
3. notify appropriate organization coordinators;
4. include questionnaire answers in the review process;
5. allow an authorized person to accept or decline the request;
6. withhold the final ticket or QR code until approval.

This can support invitation-like events without requiring every attendee to already have an account.

---

# Event Ticketing

Appointment.To supports admission-oriented appointment types.

Ticketing features can include:

- fixed event date;
- fixed event duration;
- group attendance;
- event capacity;
- seat or admission limits;
- door-open time;
- event start time;
- event end time;
- full-payment rules;
- QR-code admission;
- free events;
- private events;
- and approval workflows.

Ticketing-specific configuration can hide ordinary appointment settings that do not make sense for a fixed event.

---

## Hidden or Delayed Event Locations

For certain private events, the exact event location can remain hidden until a configured period before the event begins.

This is useful for:

- private parties;
- mystery events;
- invitation-only gatherings;
- and location-sensitive events.

---

# QR Codes

Ticketing workflows can use QR codes as part of attendee admission or booking verification.

For approval-based private events, the QR code can remain unavailable until the booking has been accepted.

---

# Calendar Integrations

Organizations can connect external calendars.

Supported integration targets can include:

- **Google Calendar**
- **Microsoft Outlook / Microsoft 365**

Appointment types can determine which calendars participate in availability and scheduling.

This helps prevent double booking while allowing each appointment type to use the calendars relevant to that workflow.

---

# Video Conferencing

Online appointments can support conferencing providers such as:

- Google Meet;
- Microsoft Teams;
- Zoom;
- Webex;
- Jitsi;
- or a custom meeting URL.

Jitsi can be used as a convenient default where a separately provisioned meeting provider is not required.

---

# Contracts and Uploaded Documents

Appointment workflows can include contracts or signed document uploads.

The platform can accept a signed document or image for manual review.

This is intentionally different from a full electronic-signature platform: the system stores and manages the uploaded agreement while the business retains control over verification.

---

# Galleries and Media

Organizations and appointments can have associated galleries.

Gallery functionality can support:

- organization-level images;
- appointment-specific galleries;
- image uploads;
- image ordering;
- moving images toward the top or bottom;
- WEBP-oriented media handling;
- and CDN-compatible URLs.

This is particularly useful for photography, events, venues, and businesses where visual content is part of the customer experience.

---

# Branding

Organizations can configure branding used throughout their public booking presence.

Branding can include elements such as:

- organization name;
- logo;
- descriptive content;
- colors or visual identity settings;
- social links;
- public URLs;
- and organization-specific presentation.

Supported social/profile fields can include common platforms such as YouTube in addition to other organization links.

Branding data can be cached for fast public rendering.

---

# Public Organization Pages

Each organization can expose public booking pages using clean organization and appointment-type URLs.

For organizations with many appointment types, the public interface can use a responsive selector rather than forcing every appointment type into narrow side-by-side columns.

The result is usable on both desktop and mobile devices.

---

# Email

Appointment.To sends transactional email for booking and account workflows.

Email capabilities can include:

- registration verification;
- attendee notifications;
- confirmation messages;
- approval requests;
- appointment outcome follow-ups;
- gift-card delivery;
- and other booking-related notifications.

Mail delivery can be configured through Laravel-supported providers such as Mailgun.

---

## Email Template Editing

Organizations can customize attendee-facing email templates.

Templates may be:

- plain text; or
- HTML.

This lets businesses keep transactional communication consistent with their own tone and branding.

---

# Policies

Organizations can maintain booking-related policies, including content such as:

- cancellation policies;
- rescheduling policies;
- attendance rules;
- booking conditions;
- and other customer-facing terms.

Rich text can be used where appropriate.

Policy definitions can be cached and invalidated automatically when changed.

---

# Outgoing Webhooks

Paid organizations can integrate Appointment.To with external systems through outgoing webhooks.

Webhooks allow external applications to react when relevant events occur inside Appointment.To.

Potential consumers include:

- CRM systems;
- accounting workflows;
- internal automation tools;
- marketing platforms;
- fulfillment systems;
- custom business applications;
- and data warehouses.

Webhook endpoints can be reviewed, enabled, disabled, and deleted.

A webhook signing secret allows the receiving application to verify that a webhook request genuinely originated from Appointment.To and that the request body was not modified in transit.

---

# API Access

Appointment.To includes an API architecture intended for paid organizations.

Authentication uses separate credentials for the user and organization context.

API keys are stored securely using hashed representations and can be regenerated when needed.

This lets external systems work with a specific organization without treating a user's account as a single global tenant.

---

# Caching and Performance

Appointment.To is designed to cache frequently read configuration in Redis.

Cacheable configuration can include:

- organization configuration;
- appointment type configuration;
- questions;
- resource definitions;
- tax definitions;
- location definitions;
- policies;
- and branding.

Model observers can invalidate affected cache entries when configuration changes.

This reduces repetitive database work on public booking pages while keeping cached information consistent with administrative changes.

---

# SEO and Public Discoverability

Public-facing pages can expose search-engine-friendly metadata and page headers so organizations can be indexed more effectively.

The system is designed to separate public booking content from private administrative areas while still providing meaningful public page titles, descriptions, and related metadata.

---

# Friendly Error Handling

Customer-facing booking flows should fail gracefully.

Appointment.To includes or is designed around friendly handling for conditions such as:

- an expired booking hold;
- a slot becoming unavailable before checkout finishes;
- authorization failure;
- invalid booking state;
- or other expected concurrency conditions.

Customers should receive an understandable explanation rather than a raw framework exception.

---

# Subscription Plans and Usage Limits

Appointment.To can distinguish between free and paid organizations.

Plan rules can limit or enable capabilities such as:

- number of resources;
- number of person resources;
- number of questionnaire questions;
- API access;
- active appointment types;
- team members;
- calendar connections;
- storage;
- booking volume;
- and distance lookups.

Business plans can support usage-based add-ons for organizations that need capacity beyond their included allowance.

This lets small businesses begin with a simpler plan while larger organizations can expand without migrating to a different product.

---

# Operational Dashboard

Business users can view upcoming activity from the dashboard.

Filters can include practical ranges such as:

- today;
- tomorrow;
- this week;
- this month;
- or all upcoming activity.

Visual status indicators help distinguish different appointment states quickly.

---

# Timezones and International Use

Appointment.To is designed around IANA timezone identifiers.

That makes it possible to represent the business timezone and customer timezone correctly rather than relying on fixed UTC offsets.

The application may use MariaDB/MySQL `CONVERT_TZ()` functionality, so production database servers should have timezone tables loaded.

---

# Architecture

Appointment.To is a Laravel application using server-rendered Blade views.

The current platform architecture is centered around technologies such as:

- **PHP 8.4+**
- **Laravel 13**
- **Blade**
- **MariaDB 10.11+**
- **Redis**
- **Composer**
- **Node.js / npm**
- **Vite**
- a standard Linux web stack such as Apache or Nginx
- Laravel queues and scheduled tasks where required

The application is intentionally compatible with conventional Linux hosting and does not require Livewire for its primary UI.

---

# Multi-Node Deployment

Appointment.To can be deployed behind a load balancer.

A production cluster may use:

- multiple HTTP application servers;
- a shared or replicated database cluster;
- Redis shared between application nodes;
- shared or distributed application storage;
- source-IP or other load-balancing strategies;
- CDN-backed public media;
- and background queue workers.

When deploying multiple HTTP nodes, all nodes should share compatible:

- application code;
- environment configuration;
- `APP_KEY`;
- session storage;
- cache storage;
- queue backend;
- user-upload storage;
- and database access.

Redis is strongly recommended for shared cache/session/queue workloads in a multi-node environment.

---

# Installation

The following is intentionally a **basic production-oriented installation outline**, not a copy-and-paste server-hardening guide.

It assumes the installer is comfortable administering Linux, PHP-FPM or Apache PHP, MariaDB, Redis, Composer, Node.js, cron/systemd, TLS, and filesystem permissions.

---

## 1. Server Requirements

A typical installation should provide:

- Linux server
- PHP 8.4 or later
- Composer 2
- MariaDB 10.11 or compatible MySQL/MariaDB version
- Redis
- Node.js and npm
- Git
- Apache or Nginx
- PHP CLI
- cron
- a process supervisor or systemd for queue workers

Common PHP extensions include:

```text
bcmath
ctype
curl
dom
fileinfo
filter
intl
json
mbstring
openssl
pdo
pdo_mysql
session
tokenizer
xml
zip
```

Additional extensions may be required by specific Composer dependencies or deployment choices.

---

## 2. Clone the Application

```bash
cd /var/www
git clone https://github.com/OKayInc/bookings.git appointment
cd appointment
```

For a production deployment, check out the desired release, tag, or deployment branch rather than blindly tracking an active development branch.

---

## 3. Install PHP Dependencies

```bash
composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction
```

For a development installation:

```bash
composer install
```

---

## 4. Create the Environment File

```bash
cp .env.example .env
```

Review the complete `.env.example` and merge any new variables into an existing production `.env` when upgrading.

Do **not** overwrite an established production `.env` without reviewing the differences.

Generate the Laravel application key for a new installation:

```bash
php artisan key:generate
```

All nodes in the same application cluster must use the same `APP_KEY`.

---

## 5. Configure the Application URL

Example:

```dotenv
APP_NAME="Appointment.To"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://appointment.example.com
```

Never run a public production installation with:

```dotenv
APP_DEBUG=true
```

---

## 6. Configure MariaDB