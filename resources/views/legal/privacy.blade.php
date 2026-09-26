@extends('layouts.public')

@section('title', 'Privacy Policy | '.config('app.name'))

@push('head')
    <meta name="description" content="Privacy Policy for Appointment.to, including Google Calendar and Microsoft Outlook integration data practices.">
    <meta name="robots" content="index,follow">
    <style>
        .privacy-policy { max-width: 960px; }
        .privacy-policy section { scroll-margin-top: 1rem; }
        .privacy-policy h2 { margin-top: 2.5rem; }
        .privacy-policy h3 { margin-top: 1.75rem; }
        .privacy-policy li + li { margin-top: .5rem; }
        .privacy-policy address { font-style: normal; }
    </style>
@endpush

@section('content')
<article class="privacy-policy mx-auto">
    <header class="mb-4">
        <p class="text-uppercase small fw-semibold text-primary mb-2">Appointment.to</p>
        <h1 class="display-5 fw-bold mb-3">Privacy Policy</h1>
        <p class="lead text-secondary mb-2">Booking and calendar platform</p>
        <p class="small text-secondary mb-0">
            <strong>Effective:</strong> August 27, 2026
            <span class="mx-2" aria-hidden="true">&middot;</span>
            <strong>Last updated:</strong> September 26, 2026
        </p>
    </header>

    <div class="alert alert-primary" role="note">
        This Privacy Policy explains how Appointment.to collects, uses, discloses, stores, and protects personal information when people visit our website, create or administer accounts, use booking pages, make or manage appointments, communicate with us, or connect third-party services such as Google Calendar and Microsoft Outlook.
    </div>

    <p class="fw-semibold">
        A publicly accessible booking page does not make the information entered into that page public. We use that information only for the purposes described in this Policy, the applicable organization's instructions, and applicable law.
    </p>

    <nav class="card border-0 shadow-sm my-4" aria-labelledby="privacy-contents-title">
        <div class="card-body">
            <h2 class="h5 mt-0" id="privacy-contents-title">Contents</h2>
            <div class="row row-cols-1 row-cols-md-2 g-2 small">
                <div class="col"><a href="#who-we-are">1. Who we are</a></div>
                <div class="col"><a href="#privacy-roles">2. Our privacy roles</a></div>
                <div class="col"><a href="#information-we-collect">3. Information we collect</a></div>
                <div class="col"><a href="#how-we-use-information">4. How we use information</a></div>
                <div class="col"><a href="#consent">5. Consent and legal grounds</a></div>
                <div class="col"><a href="#public-booking-pages">6. Public booking pages</a></div>
                <div class="col"><a href="#disclosure">7. When we disclose information</a></div>
                <div class="col"><a href="#google-data">8. Google data</a></div>
                <div class="col"><a href="#microsoft-data">9. Microsoft data</a></div>
                <div class="col"><a href="#marketing">10. Communications</a></div>
                <div class="col"><a href="#cookies">11. Cookies</a></div>
                <div class="col"><a href="#international-processing">12. International processing</a></div>
                <div class="col"><a href="#retention">13. Retention and deletion</a></div>
                <div class="col"><a href="#security">14. Security</a></div>
                <div class="col"><a href="#incidents">15. Privacy incidents</a></div>
                <div class="col"><a href="#minors">16. Children's information</a></div>
                <div class="col"><a href="#privacy-rights">17. Privacy rights</a></div>
                <div class="col"><a href="#targeted-advertising">18. Targeted advertising</a></div>
                <div class="col"><a href="#third-parties">19. Third-party services</a></div>
                <div class="col"><a href="#changes">20. Changes to this Policy</a></div>
                <div class="col"><a href="#contact">21. Contact us</a></div>
            </div>
        </div>
    </nav>

    <section id="who-we-are" aria-labelledby="who-we-are-title">
        <h2 class="h3" id="who-we-are-title">1. Who we are</h2>
        <p>
            Appointment.to is an online booking service of OKay Inc, based in Cornwall, Ontario, Canada ("Appointment.to," "OKay Inc," "we," "us," or "our"). This Policy applies to the Appointment.to website, applications, booking pages, application programming interfaces, communications, and integrations that link to it (the "Service").
        </p>
        <p>Privacy questions, requests, and complaints may be sent to our Privacy Officer at <a href="mailto:privacy@appointment.to">privacy@appointment.to</a>.</p>
    </section>

    <section id="privacy-roles" aria-labelledby="privacy-roles-title">
        <h2 class="h3" id="privacy-roles-title">2. Our privacy roles</h2>
        <p>
            Appointment.to provides the Service to organizations and professionals ("Organizations") that configure booking pages and decide what information to request. For information an Organization collects through its booking page, the Organization generally acts as the controller or organization responsible for that information, and Appointment.to generally acts as its processor or service provider.
        </p>
        <p>
            Appointment.to acts on its own behalf for account administration, billing, security, fraud prevention, Service analytics and improvement, support, legal compliance, and our direct business communications. These roles may vary under applicable law.
        </p>
    </section>

    <section id="information-we-collect" aria-labelledby="information-we-collect-title">
        <h2 class="h3" id="information-we-collect-title">3. Information we collect</h2>

        <h3 class="h5">3.1 Information provided directly</h3>
        <ul>
            <li><strong>Account information:</strong> name, email address, telephone number, password hash, preferences, and authentication details.</li>
            <li><strong>Organization information:</strong> business name, branding, address, staff and resource details, services, schedules, policies, tax information, social links, and payment settings.</li>
            <li><strong>Booking information:</strong> contact details, appointment selections, dates and times, attendee details, questionnaire answers, notes, uploaded files, accessibility requests, contracts, confirmations, cancellations, and rescheduling information.</li>
            <li><strong>Transaction information:</strong> prices, taxes, deposits, retainers, coupon or gift-card information, payment status, and limited transaction identifiers. Payment-card credentials may be collected directly by a payment provider rather than Appointment.to.</li>
            <li><strong>Communications:</strong> support requests, feedback, complaints, and other messages.</li>
            <li><strong>Optional content:</strong> any other information a person chooses to enter. Do not submit unnecessary sensitive information, passwords, government identification numbers, or complete payment-card numbers in free-text fields.</li>
        </ul>

        <h3 class="h5">3.2 Calendar and identity integrations</h3>
        <p>
            If a user connects Google or Microsoft, we may receive account identifiers, email address and profile information, selected calendar identifiers, availability or free/busy information, event metadata needed to prevent conflicts or create and update appointments, and OAuth access or refresh tokens. The exact information depends on the permissions shown during authorization and the features enabled.
        </p>

        <h3 class="h5">3.3 Information collected automatically</h3>
        <p>
            We may collect IP address, browser and device type, operating system, referring pages, requested URLs, timestamps, session and cookie identifiers, approximate location derived from IP address, diagnostic information, security events, and interactions with Service emails.
        </p>

        <h3 class="h5">3.4 Information from other sources</h3>
        <p>
            We may receive information from Organizations, their staff, booking participants, connected services, payment providers, communications providers, fraud-prevention services, and publicly available business sources.
        </p>
    </section>

    <section id="how-we-use-information" aria-labelledby="how-we-use-information-title">
        <h2 class="h3" id="how-we-use-information-title">4. How we use information</h2>
        <p>Subject to our agreements, platform rules, consent requirements, and applicable law, we may use information to:</p>
        <ul>
            <li>provide, personalize, maintain, and troubleshoot the Service;</li>
            <li>create accounts, booking pages, appointments, tickets, calendar events, reminders, confirmations, contracts, and payment records;</li>
            <li>check availability and prevent scheduling conflicts;</li>
            <li>process transactions and administer deposits, refunds, coupons, gift cards, taxes, and billing;</li>
            <li>communicate with users, Organizations, staff, attendees, and support contacts about the Service or a booking;</li>
            <li>provide support, respond to requests, and enforce terms and Organization instructions;</li>
            <li>analyze, test, develop, and improve features, reliability, accessibility, and user experience;</li>
            <li>operate our business, keep records, forecast demand, measure performance, and create aggregated or de-identified insights;</li>
            <li>protect accounts, investigate misuse, prevent fraud, maintain security, and comply with law;</li>
            <li>send lawful product news, offers, and marketing based on information provided directly to Appointment.to, where consent or another valid legal basis exists.</li>
        </ul>
        <p>We do not use information obtained from Google Workspace APIs or Microsoft APIs for advertising or marketing. Sections 8 and 9 control if they are stricter than this section.</p>
    </section>

    <section id="consent" aria-labelledby="consent-title">
        <h2 class="h3" id="consent-title">5. Consent and legal grounds</h2>
        <p>
            Depending on the context and applicable law, we rely on consent, performance of a contract, steps requested before entering a contract, compliance with legal obligations, protection of vital interests, and legitimate interests such as providing and securing the Service, preventing fraud, improving operations, and communicating with business users. Where consent is required, it may be withdrawn for future processing, subject to legal or contractual limits.
        </p>
    </section>

    <section id="public-booking-pages" aria-labelledby="public-booking-pages-title">
        <h2 class="h3" id="public-booking-pages-title">6. Public booking pages and Organization responsibilities</h2>
        <p>
            Anyone with a public booking-page link may be able to view the page's public business content, but answers, contact information, uploaded files, payment details, and booking records are not made public merely because the page is public.
        </p>
        <p>
            Organizations determine which questions and services appear and must have authority to collect and use the requested information, provide additional notices or consents required for their activities, configure reasonable retention, protect account access, and respond to privacy requests for information they control.
        </p>
    </section>

    <section id="disclosure" aria-labelledby="disclosure-title">
        <h2 class="h3" id="disclosure-title">7. When we disclose information</h2>
        <p>We may disclose information only as reasonably necessary:</p>
        <ul>
            <li>to the relevant Organization, authorized staff and resources, and booking participants;</li>
            <li>to service providers supporting hosting, storage, email, messaging, analytics, security, support, payments, tax calculation, and other operations, under appropriate restrictions;</li>
            <li>to Google, Microsoft, video-conferencing, payment, and other services a user or Organization chooses to connect;</li>
            <li>to comply with law or valid government requests, protect rights and safety, or investigate fraud and misuse;</li>
            <li>in a financing, reorganization, merger, acquisition, or sale of assets, subject to applicable law and the integration-specific restrictions below;</li>
            <li>with the person's direction or consent.</li>
        </ul>
        <p>
            Appointment.to does not sell personal information. Except for the public-page advertising technology disclosed in sections 11 and 18, subject to applicable choices and consent, we do not share personal information for cross-context behavioural advertising. We never provide Google or Microsoft API data to advertising platforms or data brokers.
        </p>
    </section>

    <section id="google-data" aria-labelledby="google-data-title">
        <h2 class="h3" id="google-data-title">8. Google API and Google Workspace data</h2>

        <h3 class="h5">8.1 Access and use</h3>
        <p>
            When a user connects Google, Appointment.to accesses only the Google account and calendar information authorized by that user and needed to provide visible features such as sign-in, calendar selection, availability checks, conflict prevention, and creating, updating, or deleting Appointment.to-related calendar events.
        </p>
        <p class="fw-semibold">
            The use of information received from Google Workspace scopes will adhere to the <a href="https://developers.google.com/workspace/workspace-api-user-data-developer-policy" rel="external">Google User Data Policy</a>, including the Limited Use requirements.
        </p>

        <h3 class="h5">8.2 Prohibited uses</h3>
        <p>
            Appointment.to does not use Google user data for advertising, personalized advertising, marketing, creditworthiness or lending decisions, surveillance, unrelated profiling, training general-purpose artificial-intelligence or machine-learning models, or building an unrelated user database. We do not sell Google user data.
        </p>

        <h3 class="h5">8.3 Human access and transfers</h3>
        <p>
            Humans do not read Google user data unless the user gives affirmative consent for a specific support or feature purpose; access is necessary for security or abuse investigation; access is required by law; or the data has been aggregated and de-identified for permitted internal operations. We transfer Google user data only as necessary to provide or improve the user-facing feature, for security, to comply with law, or as part of a transaction for which we obtain any explicit prior consent required by Google policy.
        </p>

        <h3 class="h5">8.4 Disconnecting Google</h3>
        <p>
            Users may disconnect Google in Appointment.to's calendar-connection settings and may revoke access through <a href="https://myaccount.google.com/connections" rel="external">Google Account third-party connections</a>. Revocation stops new access. Section 13 explains deletion.
        </p>
    </section>

    <section id="microsoft-data" aria-labelledby="microsoft-data-title">
        <h2 class="h3" id="microsoft-data-title">9. Microsoft API and Outlook data</h2>

        <h3 class="h5">9.1 Access and use</h3>
        <p>
            When a user connects Microsoft, Appointment.to uses Microsoft identity services and Microsoft Graph, Outlook, or Exchange data only within the permissions authorized by the user and only for requested features such as sign-in, calendar selection, availability checks, conflict prevention, and creating, updating, or deleting Appointment.to-related events.
        </p>

        <h3 class="h5">9.2 Restrictions</h3>
        <p>
            We do not sell Microsoft API Data or use it for advertising, marketing, unrelated profiling, scraping, surveillance, credit decisions, or training general-purpose artificial-intelligence models. We do not access Microsoft mail, files, contacts, or other resources unless a separate feature clearly requires that access and the user expressly authorizes the corresponding permission.
        </p>

        <h3 class="h5">9.3 Disconnecting Microsoft</h3>
        <p>
            Users may disconnect Microsoft in Appointment.to's calendar-connection settings. Personal Microsoft accounts can manage consent through <a href="https://account.live.com/consent/Manage" rel="external">Microsoft account permissions</a>; work or school accounts may use <a href="https://myapps.microsoft.com" rel="external">My Apps</a> or ask their Microsoft 365 administrator. Revocation stops new access. Section 13 explains deletion.
        </p>
    </section>

    <section id="marketing" aria-labelledby="marketing-title">
        <h2 class="h3" id="marketing-title">10. Service and marketing communications</h2>
        <p>
            We may send operational communications such as verification messages, booking notices, reminders, receipts, security alerts, policy updates, and support replies. These are part of providing the Service and may continue while an account or booking remains active.
        </p>
        <p>
            Where permitted, we may send OKay Inc's product news or offers using contact information supplied directly to us. Recipients may unsubscribe using the message link or contact us. We do not use Google or Microsoft API data to determine or deliver marketing.
        </p>
    </section>

    <section id="cookies" aria-labelledby="cookies-title">
        <h2 class="h3" id="cookies-title">11. Cookies and similar technologies</h2>
        <p>When configured, Google Analytics 4 measures visits to public marketing, organization, appointment and gift-card listing pages. Appointment.to may use a platform Analytics property, and an Organization may configure its own property for its public pages. This integration omits query strings and fragments from page locations and limits referrers to their origin. It does not load on account, administration, private appointment, booking-management, questionnaire, confirmation or payment pages.</p>
        <p>
            The Service may use essential cookies and local storage for sessions, authentication, security, preferences, booking flows, and load balancing. We may use limited analytics technologies to understand performance and improve the Service. Eligible public directory and public appointment pages belonging to Free-plan Organizations may also load Google advertising technology, which may process IP address, device/browser information, page URL, ad interactions, and cookies or similar identifiers. Advertising is not loaded in the backend, password-protected, unlisted, invitation-only, questionnaire, contract, payment, upload, booking-management, or confirmation flows. Browser controls can block cookies, but essential features may then fail. Where law requires it, non-essential technologies are used only after the applicable choice or consent.
        </p>
    </section>

    <section id="international-processing" aria-labelledby="international-processing-title">
        <h2 class="h3" id="international-processing-title">12. International processing</h2>
        <p>
            Appointment.to, a service of OKay Inc, is based in Canada. We and our service providers may process information in Canada, the United States, and other countries where privacy laws may differ. We use contractual, organizational, and technical safeguards required for applicable transfers, but information may be accessible to courts, law enforcement, or national-security authorities under local law.
        </p>
    </section>

    <section id="retention" aria-labelledby="retention-title">
        <h2 class="h3" id="retention-title">13. Retention and deletion</h2>
        <p>We retain personal information only as long as reasonably necessary for the purposes described in this Policy, Organization instructions, legal obligations, dispute resolution, security, fraud prevention, and enforcement. Retention varies by record:</p>
        <ul>
            <li>Account and Organization records are normally kept while the account is active and for a limited period afterward for recovery, legal, accounting, and security needs.</li>
            <li>Booking records, questionnaires, contracts, and uploads are kept according to Organization settings and instructions, subject to legal and operational requirements.</li>
            <li>OAuth tokens are kept only while the integration is connected and needed to provide the requested feature.</li>
            <li>When a Google or Microsoft connection is revoked, an account is closed, or deletion is validly requested, associated integration data is deleted or de-identified from active systems ordinarily within 30 days, unless a shorter period is required or retention is legally necessary. Residual encrypted backup copies ordinarily expire within 90 days and are not restored for ordinary business use.</li>
            <li>Security logs may be kept longer where reasonably necessary. Aggregated or de-identified information may be retained when it can no longer reasonably identify a person.</li>
        </ul>
        <p>
            We may delay or limit deletion to preserve transaction records, comply with law, investigate abuse, resolve disputes, protect users, or follow an Organization's lawful instructions. When we act as a processor, the Organization may control the applicable retention period.
        </p>
    </section>

    <section id="security" aria-labelledby="security-title">
        <h2 class="h3" id="security-title">14. Security</h2>
        <p>
            We use reasonable administrative, technical, and physical safeguards designed to protect information, including access controls, encryption in transit, protected credential storage, logging, backups, vendor review, and least-privilege practices. No system is completely secure. Users must use strong unique passwords, protect devices and recovery channels, limit staff access, and promptly report suspected misuse.
        </p>
    </section>

    <section id="incidents" aria-labelledby="incidents-title">
        <h2 class="h3" id="incidents-title">15. Privacy and security incidents</h2>
        <p>
            We investigate suspected incidents and notify affected Organizations, individuals, regulators, or platform providers when required by law or contract. Notices may be delivered through account contact information or the Service.
        </p>
    </section>

    <section id="minors" aria-labelledby="minors-title">
        <h2 class="h3" id="minors-title">16. Children's information</h2>
        <p>
            The Service is not directed to children under 13, and they may not independently create an administrative account. An Organization may use the Service to arrange services involving a minor when a parent, guardian, school, club, healthcare provider, or other authorized person provides the information and any required consent. Organizations are responsible for complying with laws that apply to their collection of children's information.
        </p>
    </section>

    <section id="privacy-rights" aria-labelledby="privacy-rights-title">
        <h2 class="h3" id="privacy-rights-title">17. Privacy choices and rights</h2>
        <p>
            Depending on location and our role, rights may include access, correction, deletion, restriction, portability, withdrawal of consent, objection to certain processing, opting out of marketing, and challenging our compliance.
        </p>
        <ol>
            <li>Use available Appointment.to account, Organization, booking, or integration controls to review, correct, export, disconnect, or delete information.</li>
            <li>For information submitted to a booking Organization, contact that Organization first when it controls the information. We will assist as required by law and our agreement.</li>
            <li>For Appointment.to account information or an unresolved request, contact <a href="mailto:privacy@appointment.to">privacy@appointment.to</a> and identify the relevant account, Organization, booking, or email address.</li>
            <li>Revoke Google or Microsoft access using the links in Sections 8.4 and 9.3. Revocation stops new access but does not automatically delete records that lawfully must be retained.</li>
        </ol>
        <p>
            We may verify identity and authority before completing a request. Legal exceptions may apply, including for security, fraud prevention, accounting, disputes, and the rights of others. We do not discriminate against a person for exercising a privacy right.
        </p>
        <p>
            In Canada, a person may contact the <a href="https://www.priv.gc.ca/en/report-a-concern/" rel="external">Office of the Privacy Commissioner of Canada</a> or an applicable provincial privacy regulator after first giving us an opportunity to address the concern.
        </p>
    </section>

    <section id="targeted-advertising" aria-labelledby="targeted-advertising-title">
        <h2 class="h3" id="targeted-advertising-title">18. Do Not Track and targeted advertising</h2>
        <p>
            Because there is no universally accepted browser Do Not Track standard, the Service may not respond to every such signal. Where legally required and technically supported, we honour recognized opt-out preference signals. Appointment.to does not sell personal information. Google may serve contextual or, where permitted and consented to, personalized advertising on the eligible Free-plan public pages described in section 11. We do not send questionnaire answers, booking contact information, payment details, uploaded files, or Google/Microsoft integration data to Google Ads for ad selection. Google's handling of advertising data is also governed by its own privacy notices and controls.
        </p>
    </section>

    <section id="third-parties" aria-labelledby="third-parties-title">
        <h2 class="h3" id="third-parties-title">19. Third-party services and links</h2>
        <p>
            The Service may link to or interoperate with websites and services we do not control. Their privacy practices are governed by their own notices. Organizations may also place their own links, documents, questions, payment accounts, plugins, and integrations on booking pages. Users should review applicable third-party and Organization terms before submitting information.
        </p>
    </section>

    <section id="changes" aria-labelledby="changes-title">
        <h2 class="h3" id="changes-title">20. Changes to this Policy</h2>
        <p>
            We may update this Policy to reflect changes in the Service, law, vendors, or data practices. We will post the revised Policy with a new Last updated date and provide additional notice or seek renewed consent when required. If we change how we use Google Data or other information in a materially different way, we will notify affected users and obtain consent before the new use when required by platform policy or law.
        </p>
    </section>

    <section id="contact" aria-labelledby="contact-title" class="mb-0">
        <h2 class="h3" id="contact-title">21. Contact us</h2>
        <address class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="fw-semibold mb-1">Privacy Officer of OKay Inc</p>
                <p class="mb-1">Appointment.to</p>
                <p class="mb-1">Cornwall, Ontario, Canada</p>
                <p class="mb-3"><a href="mailto:privacy@appointment.to">privacy@appointment.to</a></p>
                <p class="small text-secondary mb-0">
                    Include enough information to identify the relevant account, Organization, booking, or integration without sending passwords, complete payment-card numbers, or unnecessary sensitive information.
                </p>
            </div>
        </address>
    </section>
</article>
@endsection
