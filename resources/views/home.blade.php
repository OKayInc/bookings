@extends('layouts.public')

@section('title', 'Appointment.to | Online booking and calendar scheduling')

@push('head')
    <meta name="description" content="Appointment.to is an online booking and calendar scheduling service from OKay Inc. Create booking pages, manage availability, accept appointments, and connect Google Calendar or Microsoft Outlook.">
    <meta name="robots" content="index,follow">
    <style>
        .home-hero {
            background: linear-gradient(135deg, #111827 0%, #1f2937 60%, #7f1d1d 100%);
        }
        .home-eyebrow { letter-spacing: .08em; }
        .home-feature-icon {
            display: inline-grid;
            width: 2.75rem;
            height: 2.75rem;
            place-items: center;
            border-radius: .75rem;
            background: var(--bs-primary-bg-subtle);
            color: var(--bs-primary-text-emphasis);
            font-weight: 700;
        }
    </style>
@endpush

@section('content')
<article>
    <section class="home-hero rounded-4 p-4 p-md-5 mb-5 text-white shadow-sm" aria-labelledby="home-title">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <p class="home-eyebrow text-uppercase small fw-semibold text-white-50 mb-2">Online booking and calendar scheduling</p>
                <h1 class="display-4 fw-bold mb-3" id="home-title">Make scheduling easier with Appointment.to</h1>
                <p class="lead mb-4">
                    Appointment.to helps organizations publish booking pages, manage availability and resources, accept appointments and event registrations, collect payments, and keep calendars synchronized.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-light btn-lg" href="{{ route('register') }}">Create an account</a>
                    <a class="btn btn-outline-light btn-lg" href="{{ route('login') }}">Log in</a>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 bg-white text-dark mb-0 shadow">
                    <div class="card-body p-4">
                        <h2 class="h5">Operated by OKay Inc.</h2>
                        <p class="mb-0 text-secondary">
                            Appointment.to is a booking and scheduling service of OKay Inc., based in Cornwall, Ontario, Canada.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5" aria-labelledby="features-title">
        <div class="text-center mx-auto mb-4" style="max-width:720px">
            <h2 class="h1" id="features-title">What Appointment.to does</h2>
            <p class="lead text-secondary mb-0">One place to configure services, coordinate people and equipment, and guide clients from availability to confirmation.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 mb-0 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="home-feature-icon mb-3" aria-hidden="true">1</span>
                        <h3 class="h5">Public booking pages</h3>
                        <p class="text-secondary mb-0">Organizations can publish services, available times, questionnaires, policies, prices, and event tickets for clients and attendees.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 mb-0 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="home-feature-icon mb-3" aria-hidden="true">2</span>
                        <h3 class="h5">Availability and resources</h3>
                        <p class="text-secondary mb-0">Scheduling rules coordinate staff, rooms, equipment, capacity, holidays, buffers, and existing commitments to avoid conflicts.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 mb-0 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="home-feature-icon mb-3" aria-hidden="true">3</span>
                        <h3 class="h5">Booking management</h3>
                        <p class="text-secondary mb-0">Organizations can confirm, reschedule, cancel, and track bookings while sending relevant notices and reminders.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 mb-0 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="home-feature-icon mb-3" aria-hidden="true">4</span>
                        <h3 class="h5">Calendar connections</h3>
                        <p class="text-secondary mb-0">Users may connect Google Calendar or Microsoft Outlook to check availability and synchronize Appointment.to bookings.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 mb-0 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="home-feature-icon mb-3" aria-hidden="true">5</span>
                        <h3 class="h5">Payments and pricing</h3>
                        <p class="text-secondary mb-0">Organizations can configure pricing, taxes, retainers, deposits, coupons, gift cards, and supported payment providers.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 mb-0 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="home-feature-icon mb-3" aria-hidden="true">6</span>
                        <h3 class="h5">Flexible appointment types</h3>
                        <p class="text-secondary mb-0">The service supports individual and group appointments, free and paid events, questionnaires, contracts, ticketing, and video meetings.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm p-0 mb-5 overflow-hidden" aria-labelledby="google-data-title">
        <div class="row g-0">
            <div class="col-lg-4 bg-primary-subtle p-4 p-md-5">
                <p class="text-uppercase small fw-semibold text-primary mb-2">Google Calendar integration</p>
                <h2 class="h1 mb-0" id="google-data-title">Why Appointment.to requests Google user data</h2>
            </div>
            <div class="col-lg-8 p-4 p-md-5">
                <p>
                    Connecting Google is optional. When a user chooses to connect a Google account, Appointment.to requests only the account and calendar permissions needed to provide the calendar features that user enables.
                </p>
                <ul>
                    <li><strong>Calendar selection:</strong> display the user's calendars so the user can choose which ones Appointment.to should use.</li>
                    <li><strong>Availability:</strong> read relevant calendar or free/busy information to prevent double bookings.</li>
                    <li><strong>Synchronization:</strong> create, update, or delete calendar events associated with Appointment.to bookings.</li>
                    <li><strong>Connection continuity:</strong> securely store OAuth tokens while the integration remains connected so authorized synchronization can continue.</li>
                </ul>
                <p>
                    Appointment.to does not sell Google user data or use it for advertising or marketing. Users can disconnect Google in their Appointment.to calendar settings or revoke access from their Google Account.
                </p>
                <p class="mb-0">
                    Read the <a class="fw-semibold" href="{{ route('legal.privacy') }}#google-data">Google data section of our Privacy Policy</a> for details about access, use, storage, disclosure, retention, deletion, and user controls.
                </p>
            </div>
        </div>
    </section>

    <section class="text-center mx-auto mb-4" style="max-width:760px" aria-labelledby="legal-title">
        <h2 class="h3" id="legal-title">Privacy and service information</h2>
        <p class="text-secondary">
            Review how OKay Inc. operates Appointment.to, protects personal information, handles connected-service data, and defines use of the service.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a class="btn btn-primary" href="{{ route('legal.privacy') }}">Privacy Policy</a>
            <a class="btn btn-outline-primary" href="{{ route('legal.terms') }}">Terms &amp; Conditions</a>
        </div>
    </section>
</article>
@endsection
