@extends('layouts.public')

@php
    $seo = app(\App\Support\Seo\PublicSeo::class)->home();
@endphp

@push('head')
    <style>
        .sales-hero { background: radial-gradient(circle at 85% 15%, rgba(255,255,255,.13), transparent 28%), linear-gradient(135deg,#111827 0%,#1f2937 58%,#7f1d1d 100%); }
        .sales-eyebrow { letter-spacing:.09em; }
        .sales-icon { display:inline-grid;width:2.8rem;height:2.8rem;place-items:center;border-radius:.8rem;background:var(--bs-primary-bg-subtle);color:var(--bs-primary-text-emphasis);font-weight:800; }
        .sales-step { width:2.4rem;height:2.4rem;display:grid;place-items:center;border-radius:50%;background:var(--bs-dark);color:#fff;font-weight:700;flex:0 0 auto; }
        .sales-check { color:var(--bs-success);font-weight:800; }
        .sales-price-card { border:2px solid transparent; }
        .sales-price-card.featured { border-color:var(--bs-primary); }
        .sales-mini-ui { background:#fff;color:#212529;border-radius:1rem;padding:1.25rem;box-shadow:0 1rem 2.5rem rgba(0,0,0,.2); }
        .sales-mini-slot { border:1px solid var(--bs-border-color);border-radius:.65rem;padding:.65rem .8rem;margin-top:.55rem; }
    </style>
@endpush

@section('content')
<article>
    <section class="sales-hero rounded-4 p-4 p-md-5 mb-5 text-white shadow-sm overflow-hidden" aria-labelledby="home-title">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <p class="sales-eyebrow text-uppercase small fw-semibold text-white-50 mb-2">Online scheduling built around your business</p>
                <h1 class="display-4 fw-bold mb-3" id="home-title">Let clients book you without the back-and-forth.</h1>
                <p class="lead mb-4">Share one booking page, show only times that really work, coordinate staff and equipment, collect the information you need, and let Appointment.to handle the scheduling details.</p>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a class="btn btn-light btn-lg px-4" href="{{ route('register') }}">Start free</a>
                    <a class="btn btn-outline-light btn-lg px-4" href="#how-it-works">See how it works</a>
                </div>
                <p class="small text-white-50 mb-0">Free plan available. No paid subscription required to get started.</p>
            </div>
            <div class="col-lg-5">
                <div class="sales-mini-ui" aria-label="Example booking page preview">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div><div class="fw-bold">Choose a time</div><small class="text-secondary">Your availability, simplified.</small></div>
                        <span class="badge text-bg-success">Available</span>
                    </div>
                    <div class="sales-mini-slot"><strong>10:00 AM</strong><span class="float-end text-secondary">Book</span></div>
                    <div class="sales-mini-slot"><strong>1:30 PM</strong><span class="float-end text-secondary">Book</span></div>
                    <div class="sales-mini-slot"><strong>3:00 PM</strong><span class="float-end text-secondary">Book</span></div>
                    <hr>
                    <div class="small text-secondary">Calendars + staff + rooms + equipment checked before a slot is offered.</div>
                </div>
            </div>
        </div>
    </section>

    <section class="text-center mx-auto mb-5" style="max-width:820px">
        <p class="text-uppercase small fw-semibold text-primary mb-2">More than a calendar link</p>
        <h2 class="display-6 fw-bold">Scheduling that understands what an appointment actually needs</h2>
        <p class="lead text-secondary">Appointment.to can account for people, places, equipment, capacity, policies, payments and custom questions before a client ever clicks Confirm.</p>
    </section>

    <section class="row g-4 mb-5" aria-label="Core benefits">
        @foreach([
            ['1','Avoid double booking','Connect Google Calendar or Microsoft Outlook and combine calendar conflicts with your own availability rules.'],
            ['2','Coordinate resources','Require staff, rooms or equipment, support replacement groups, capacity and quantity-managed equipment.'],
            ['3','Automate the client journey','Use questionnaires, reminders, confirmations, contracts, rescheduling rules and passwordless booking management.'],
            ['4','Get paid your way','Support free or paid bookings, retainers, deposits, taxes, coupons, gift cards and organization-owned payment providers.'],
        ] as [$number,$title,$copy])
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 border-0 shadow-sm mb-0">
                    <div class="card-body p-4">
                        <span class="sales-icon mb-3">{{ $number }}</span>
                        <h3 class="h5">{{ $title }}</h3>
                        <p class="text-secondary mb-0">{{ $copy }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </section>

    <section id="how-it-works" class="card border-0 shadow-sm p-4 p-md-5 mb-5" aria-labelledby="how-title">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5">
                <p class="text-uppercase small fw-semibold text-primary mb-2">How it works</p>
                <h2 class="display-6 fw-bold" id="how-title">From setup to booked in three steps</h2>
                <p class="text-secondary mb-0">Start simple. Appointment.to can grow into more advanced workflows when your business needs them.</p>
            </div>
            <div class="col-lg-7">
                @foreach([
                    ['Create your organization','Answer a few setup questions and begin with a practical starting configuration.'],
                    ['Define what can be booked','Set services or events, availability, resources, questions, pricing and policies.'],
                    ['Share your booking page','Clients choose a valid time, complete your form and receive the booking flow you configured.'],
                ] as $i => [$title,$copy])
                    <div class="d-flex gap-3 {{ $i < 2 ? 'mb-4' : '' }}">
                        <span class="sales-step">{{ $i + 1 }}</span>
                        <div><h3 class="h5 mb-1">{{ $title }}</h3><p class="text-secondary mb-0">{{ $copy }}</p></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mb-5" aria-labelledby="use-cases-title">
        <div class="text-center mx-auto mb-4" style="max-width:760px">
            <p class="text-uppercase small fw-semibold text-primary mb-2">Flexible by design</p>
            <h2 class="display-6 fw-bold" id="use-cases-title">One platform, very different booking workflows</h2>
        </div>
        <div class="row g-3">
            @foreach([
                ['One-to-one appointments','Consultations, services, interviews and private sessions.'],
                ['Group bookings','Classes, workshops and appointments with attendee capacity.'],
                ['Ticketed events','Fixed-date events, seating, printable tickets and check-in.'],
                ['Online meetings','Jitsi plus configured Google Meet, Teams, Zoom, Webex or custom links.'],
                ['Resource-heavy services','Coordinate people, rooms, vehicles or equipment before showing availability.'],
                ['Custom intake','Conditional questionnaires, files, numeric rules and answer-driven pricing or resources.'],
            ] as [$title,$copy])
                <div class="col-md-6 col-lg-4"><div class="card h-100 mb-0"><div class="card-body"><h3 class="h5">{{ $title }}</h3><p class="text-secondary mb-0">{{ $copy }}</p></div></div></div>
            @endforeach
        </div>
    </section>

    <section id="pricing" class="mb-5" aria-labelledby="pricing-title">
        @php
            $currency = strtoupper((string) config('plans.currency','usd'));
            $prefix = $currency === 'USD' ? 'US$' : $currency.' ';
            $monthly = rtrim(rtrim(number_format(((int) config('plans.business_monthly_price_minor')) / 100, 2), '0'), '.');
            $annual = rtrim(rtrim(number_format(((int) config('plans.business_annual_price_minor')) / 100, 2), '0'), '.');
        @endphp
        <div class="text-center mx-auto mb-4" style="max-width:760px">
            <p class="text-uppercase small fw-semibold text-primary mb-2">Simple pricing</p>
            <h2 class="display-6 fw-bold" id="pricing-title">Start free. Upgrade when the business needs more.</h2>
            <p class="lead text-secondary">The homepage keeps pricing easy to scan; the full comparison lives on the pricing page.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-5">
                <div class="card sales-price-card h-100 mb-0 shadow-sm"><div class="card-body p-4 p-md-5">
                    <h3>Free</h3><p class="display-5 fw-bold">$0</p><p class="text-secondary">A real working plan for small scheduling needs.</p>
                    <ul class="list-unstyled">
                        <li class="mb-2"><span class="sales-check">✓</span> Up to {{ config('plans.limits.free.active_appointment_types') }} active appointment types</li>
                        <li class="mb-2"><span class="sales-check">✓</span> Up to {{ config('plans.limits.free.monthly_bookings') }} bookings/month</li>
                        <li class="mb-2"><span class="sales-check">✓</span> {{ config('plans.limits.free.calendar_connections') }} calendar connections</li>
                        <li class="mb-2"><span class="sales-check">✓</span> Up to {{ config('plans.limits.free.questions') }} questionnaire questions</li>
                    </ul>
                    <a class="btn btn-outline-primary w-100" href="{{ route('register') }}">Start free</a>
                </div></div>
            </div>
            <div class="col-lg-5">
                <div class="card sales-price-card featured h-100 mb-0 shadow"><div class="card-body p-4 p-md-5">
                    <span class="badge text-bg-primary mb-2">For growing organizations</span>
                    <h3>Business</h3><p class="display-5 fw-bold">{{ $prefix }}{{ $monthly }} <span class="fs-6 fw-normal text-secondary">/month</span></p>
                    <p class="text-secondary">or {{ $prefix }}{{ $annual }}/year · {{ config('plans.trial_days') }}-day eligible trial</p>
                    <ul class="list-unstyled">
                        <li class="mb-2"><span class="sales-check">✓</span> Up to {{ config('plans.limits.business.active_appointment_types') }} active appointment types included</li>
                        <li class="mb-2"><span class="sales-check">✓</span> {{ config('plans.limits.business.monthly_bookings') }} bookings/month included</li>
                        <li class="mb-2"><span class="sales-check">✓</span> API and outgoing webhooks</li>
                        <li class="mb-2"><span class="sales-check">✓</span> No advertising + optional platform branding removal</li>
                    </ul>
                    <a class="btn btn-primary w-100 mb-2" href="{{ route('register') }}">Start free, upgrade later</a>
                    <a class="btn btn-link w-100" href="{{ route('pricing') }}">Compare plans and add-ons</a>
                </div></div>
            </div>
        </div>
    </section>

    <section class="rounded-4 bg-dark text-white p-4 p-md-5 mb-5 text-center" aria-labelledby="cta-title">
        <h2 class="display-6 fw-bold" id="cta-title">Spend less time arranging appointments.</h2>
        <p class="lead text-white-50 mx-auto" style="max-width:720px">Give clients a clear way to book while Appointment.to handles the rules behind the scenes.</p>
        <a class="btn btn-light btn-lg px-4" href="{{ route('register') }}">Create your free account</a>
    </section>

    <section class="accordion mb-5" id="home-faq" aria-labelledby="faq-title">
        <div class="text-center mb-4"><h2 class="h1" id="faq-title">Frequently asked questions</h2></div>
        @foreach([
            ['Can I use Appointment.to for free?','Yes. The Free plan is intended to be usable on its own, with lower capacity and advertising on eligible public pages.'],
            ['Can I connect my existing calendar?','Yes. Appointment.to supports Google Calendar and Microsoft Outlook connections for availability and synchronization.'],
            ['Can appointments require more than one person or resource?','Yes. Appointment.to can coordinate required and optional resources, replacement groups, equipment quantities and group capacity.'],
            ['Can I charge clients when they book?','Yes. Organizations can configure supported payment providers and use pricing, taxes, retainers, deposits, coupons and gift cards.'],
        ] as $i => [$q,$a])
            <div class="accordion-item">
                <h3 class="accordion-header"><button class="accordion-button {{ $i ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq-{{ $i }}" aria-expanded="{{ $i ? 'false' : 'true' }}">{{ $q }}</button></h3>
                <div id="faq-{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" data-bs-parent="#home-faq"><div class="accordion-body text-secondary">{{ $a }}</div></div>
            </div>
        @endforeach
    </section>

    <section class="card border-0 shadow-sm p-0 mb-5 overflow-hidden" aria-labelledby="google-data-title">
        <div class="row g-0">
            <div class="col-lg-4 bg-primary-subtle p-4 p-md-5">
                <p class="text-uppercase small fw-semibold text-primary mb-2">Google Calendar integration</p>
                <h2 class="h2 mb-0" id="google-data-title">Why Appointment.to requests Google user data</h2>
            </div>
            <div class="col-lg-8 p-4 p-md-5">
                <p>Connecting Google is optional. When a user chooses to connect a Google account, Appointment.to requests only the account and calendar permissions needed to provide the calendar features that user enables.</p>
                <ul>
                    <li><strong>Calendar selection:</strong> display the user's calendars so the user can choose which ones Appointment.to should use.</li>
                    <li><strong>Availability:</strong> read relevant calendar or free/busy information to prevent double bookings.</li>
                    <li><strong>Synchronization:</strong> create, update, or delete calendar events associated with Appointment.to bookings.</li>
                    <li><strong>Connection continuity:</strong> securely store OAuth tokens while the integration remains connected so authorized synchronization can continue.</li>
                </ul>
                <p>Appointment.to does not sell Google user data or use it for advertising or marketing. Users can disconnect Google in their Appointment.to calendar settings or revoke access from their Google Account.</p>
                <p class="mb-0">Read the <a class="fw-semibold" href="{{ route('legal.privacy') }}#google-data">Google data section of our Privacy Policy</a> for details.</p>
            </div>
        </div>
    </section>
</article>
@endsection
