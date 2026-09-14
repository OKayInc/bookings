@extends('layouts.public')

@section('title', 'Terms and Conditions | '.config('app.name'))

@push('head')
    <meta name="description" content="Terms and Conditions governing access to and use of Appointment.to.">
    <meta name="robots" content="index,follow">
    <style>
        .terms-policy { max-width: 960px; }
        .terms-policy section { scroll-margin-top: 1rem; }
        .terms-policy h2 { margin-top: 2.5rem; }
        .terms-policy h3 { margin-top: 1.75rem; }
        .terms-policy li + li { margin-top: .5rem; }
        .terms-policy address { font-style: normal; }
    </style>
@endpush

@section('content')
<article class="terms-policy mx-auto">
    <header class="mb-4">
        <p class="text-uppercase small fw-semibold text-primary mb-2">Appointment.to</p>
        <h1 class="display-5 fw-bold mb-3">Terms and Conditions</h1>
        <p class="lead text-secondary mb-2">Terms governing use of the booking and calendar platform</p>
        <p class="small text-secondary mb-0">
            <strong>Effective:</strong> August 27, 2026
            <span class="mx-2" aria-hidden="true">&middot;</span>
            <strong>Last updated:</strong> September 14, 2026
        </p>
    </header>

    <div class="alert alert-primary" role="note">
        <strong>Please read these Terms carefully.</strong> By accessing or using Appointment.to, creating an account, publishing a booking page, connecting an integration, or making or managing a booking, you agree to these Terms. If you do not agree, do not use the Service.
    </div>

    <div class="alert alert-warning" role="note">
        <strong>Important risk notice:</strong> Use of the Service is voluntary. Appointment.to is a technology platform, not the provider of services offered through booking pages. You are responsible for deciding whether an appointment, activity, provider, location, instruction, or transaction is appropriate and safe for you and others.
    </div>

    <nav class="card border-0 shadow-sm my-4" aria-labelledby="terms-contents-title">
        <div class="card-body">
            <h2 class="h5 mt-0" id="terms-contents-title">Contents</h2>
            <div class="row row-cols-1 row-cols-md-2 g-2 small">
                <div class="col"><a href="#agreement">1. Agreement and scope</a></div>
                <div class="col"><a href="#definitions">2. Definitions</a></div>
                <div class="col"><a href="#eligibility">3. Eligibility and authority</a></div>
                <div class="col"><a href="#platform-role">4. Our platform role</a></div>
                <div class="col"><a href="#voluntary-use">5. Voluntary use and risk</a></div>
                <div class="col"><a href="#accounts">6. Accounts and security</a></div>
                <div class="col"><a href="#organizations">7. Organization responsibilities</a></div>
                <div class="col"><a href="#clients">8. Client responsibilities</a></div>
                <div class="col"><a href="#bookings">9. Bookings and contracts</a></div>
                <div class="col"><a href="#payments">10. Prices, payments, and refunds</a></div>
                <div class="col"><a href="#integrations">11. Calendars and integrations</a></div>
                <div class="col"><a href="#communications">12. Electronic communications</a></div>
                <div class="col"><a href="#content">13. User Content and data</a></div>
                <div class="col"><a href="#acceptable-use">14. Acceptable use</a></div>
                <div class="col"><a href="#intellectual-property">15. Intellectual property</a></div>
                <div class="col"><a href="#privacy">16. Privacy</a></div>
                <div class="col"><a href="#third-parties">17. Third-party services</a></div>
                <div class="col"><a href="#availability">18. Service availability</a></div>
                <div class="col"><a href="#disclaimers">19. Disclaimers</a></div>
                <div class="col"><a href="#release">20. Release</a></div>
                <div class="col"><a href="#liability">21. Limitation of liability</a></div>
                <div class="col"><a href="#indemnity">22. Indemnification</a></div>
                <div class="col"><a href="#termination">23. Suspension and termination</a></div>
                <div class="col"><a href="#changes">24. Changes</a></div>
                <div class="col"><a href="#law">25. Governing law and disputes</a></div>
                <div class="col"><a href="#general">26. General terms</a></div>
                <div class="col"><a href="#contact">27. Contact</a></div>
            </div>
        </div>
    </nav>

    <section id="agreement" aria-labelledby="agreement-title">
        <h2 class="h3" id="agreement-title">1. Agreement and scope</h2>
        <p>
            These Terms and Conditions ("Terms") form a binding agreement between you and OKay Inc. ("OKay Inc.," "we," "us," or "our") regarding your access to and use of Appointment.to, including its website, applications, booking pages, application programming interfaces, communications, and integrations that link to these Terms (collectively, the "Service").
        </p>
        <p>
            These Terms apply to visitors, account holders, Organizations, their personnel, Clients, attendees, and anyone else who accesses or uses the Service. Additional written terms may apply to a paid plan, integration, feature, order, or enterprise arrangement. If additional terms conflict with these Terms, the additional terms control only for their subject matter.
        </p>
    </section>

    <section id="definitions" aria-labelledby="definitions-title">
        <h2 class="h3" id="definitions-title">2. Definitions</h2>
        <ul>
            <li><strong>"Organization"</strong> means a business, professional, association, team, or other person that creates or administers booking pages or offers goods, appointments, activities, facilities, or services through the Service.</li>
            <li><strong>"Client"</strong> means a person who views a booking page or requests, purchases, attends, manages, cancels, or reschedules a booking.</li>
            <li><strong>"User"</strong> or <strong>"you"</strong> means any person or entity accessing or using the Service, including an Organization or Client.</li>
            <li><strong>"User Content"</strong> means information or material submitted, uploaded, transmitted, displayed, or made available by a User through the Service.</li>
            <li><strong>"Third-Party Service"</strong> means a product or service not controlled by OKay Inc., including calendar, identity, payment, tax, messaging, video-conferencing, mapping, hosting, and communications providers.</li>
        </ul>
    </section>

    <section id="eligibility" aria-labelledby="eligibility-title">
        <h2 class="h3" id="eligibility-title">3. Eligibility and authority</h2>
        <p>
            You must be at least 18 years old and legally capable of entering a binding agreement to create or administer an account. If you use the Service for an entity, you represent that you have authority to bind it, and "you" includes that entity.
        </p>
        <p>
            A parent, guardian, or other authorized adult may make a booking involving a minor or another person who cannot book independently. That adult is responsible for having the authority and consents required to provide information, accept applicable policies, and arrange the service.
        </p>
    </section>

    <section id="platform-role" aria-labelledby="platform-role-title">
        <h2 class="h3" id="platform-role-title">4. Our platform role</h2>
        <p>
            OKay Inc. provides Appointment.to as scheduling and related technology. Unless we expressly identify OKay Inc. as the provider of a particular offering, we do not provide, supervise, recommend, endorse, control, or guarantee any service, activity, product, venue, professional, Organization, Client, or other User appearing through the Service.
        </p>
        <p>
            Organizations are independent from OKay Inc. OKay Inc. is not their employer, partner, joint venturer, agent, insurer, licensor, credentialing body, or professional regulator. A booking can create a contract directly between an Organization and a Client; OKay Inc. is not a party to that contract.
        </p>
        <p>
            We do not verify every identity, qualification, licence, certification, insurance policy, background, facility, description, availability statement, price, health or safety practice, or legal compliance claim. Any verification or badge shown by the Service is limited to what it expressly states and is not a warranty.
        </p>
    </section>

    <section id="voluntary-use" aria-labelledby="voluntary-use-title">
        <h2 class="h3" id="voluntary-use-title">5. Voluntary Use and Assumption of Risk</h2>
        <p class="fw-semibold">
            Your use of the Service and your decision to request, offer, attend, host, perform, cancel, or rely on any booking are voluntary. Neither OKay Inc. nor anyone acting on its behalf requires you to use the Service or participate in an appointment or activity.
        </p>
        <p>
            To the maximum extent permitted by law, you knowingly accept and assume the risks arising from your choices, conduct, User Content, bookings, communications, transactions, travel, locations, equipment, instructions, interactions with other Users, and use of services arranged through the platform. These risks may include inconvenience, scheduling conflicts, data loss, financial loss, property damage, illness, personal injury, emotional distress, disability, or death.
        </p>
        <p>
            You are responsible for independently evaluating whether an Organization, Client, offering, location, instruction, activity, or transaction is lawful, suitable, accessible, and safe. Use qualified professional advice where appropriate. Appointment.to is not an emergency service and must not be used to request or coordinate emergency assistance. Contact the appropriate emergency service when immediate help is required.
        </p>
        <p>
            Nothing in these Terms excludes a duty or liability that applicable law does not allow to be excluded, including liability that cannot lawfully be waived for gross negligence, wilful misconduct, fraud, or personal injury.
        </p>
    </section>

    <section id="accounts" aria-labelledby="accounts-title">
        <h2 class="h3" id="accounts-title">6. Accounts and security</h2>
        <p>
            You must provide accurate, current information; keep it updated; protect passwords, devices, recovery methods, access tokens, and connected accounts; restrict access to authorized people; and notify us promptly of suspected compromise or unauthorized use. You are responsible for activity under your account to the extent permitted by law.
        </p>
        <p>
            You may not share credentials in a way that defeats plan limits or security controls, impersonate another person, register using misleading information, or transfer an account without our permission. We may require identity, ownership, or authority verification.
        </p>
    </section>

    <section id="organizations" aria-labelledby="organizations-title">
        <h2 class="h3" id="organizations-title">7. Organization responsibilities</h2>
        <p>Each Organization is solely responsible for its business and offerings, including:</p>
        <ul>
            <li>the accuracy, completeness, and legality of its descriptions, schedules, capacity, availability, prices, taxes, fees, refund rules, cancellation terms, contracts, questionnaires, and public content;</li>
            <li>all licences, permits, qualifications, registrations, consents, background checks, insurance, safety procedures, accessibility measures, staffing, equipment, premises, and professional obligations required for its activities;</li>
            <li>evaluating Clients and declining, stopping, or modifying an appointment where safety, legality, suitability, or professional standards require it;</li>
            <li>protecting Client information, limiting personnel access, responding to privacy requests, and providing any additional notices or consents required by law;</li>
            <li>the acts and omissions of its owners, staff, contractors, resources, invitees, and connected accounts;</li>
            <li>resolving complaints, no-shows, cancellations, refunds, chargebacks, injuries, losses, disputes, and claims relating to its offerings.</li>
        </ul>
        <p>
            An Organization must not present OKay Inc. or the Appointment.to Service as the provider, guarantor, insurer, certifier, or endorser of the Organization's services.
        </p>
    </section>

    <section id="clients" aria-labelledby="clients-title">
        <h2 class="h3" id="clients-title">8. Client responsibilities</h2>
        <p>Each Client is responsible for:</p>
        <ul>
            <li>reviewing the Organization's description, qualifications, location, policies, price, cancellation terms, privacy notice, waivers, and contracts before booking;</li>
            <li>providing accurate information and having authority to submit information about attendees or other people;</li>
            <li>following lawful safety, access, conduct, age, health, equipment, and preparation requirements disclosed by the Organization;</li>
            <li>seeking medical, legal, financial, or other professional advice before relying on an appointment where appropriate;</li>
            <li>communicating limitations or concerns directly to the Organization and leaving or stopping an activity if the Client reasonably believes it is unsafe.</li>
        </ul>
    </section>

    <section id="bookings" aria-labelledby="bookings-title">
        <h2 class="h3" id="bookings-title">9. Bookings, attendance, and Organization contracts</h2>
        <p>
            A booking request is subject to availability, Organization acceptance, payment requirements, confirmation rules, and any additional terms shown during the booking process. Automated confirmation does not guarantee that an Organization will perform an offering if it is unlawful, unsafe, unavailable, incorrectly described, or affected by circumstances beyond reasonable control.
        </p>
        <p>
            Organizations may present their own contracts, waivers, questionnaires, and policies. Those materials are provided by the Organization, not OKay Inc. OKay Inc. does not give legal advice or determine whether Organization terms are valid, sufficient, fair, or enforceable.
        </p>
        <p>
            Users are responsible for keeping their own records. Calendar events, emails, reminders, tickets, and platform status indicators are conveniences and may be delayed, filtered, duplicated, altered, or unavailable. Users must verify important details directly with the relevant Organization.
        </p>
    </section>

    <section id="payments" aria-labelledby="payments-title">
        <h2 class="h3" id="payments-title">10. Prices, payments, taxes, coupons, and refunds</h2>
        <p>
            An Organization controls the prices, deposits, retainers, taxes, fees, coupons, gift cards, cancellation charges, and refund rules for its offerings unless the Service expressly states otherwise. The Organization is responsible for required tax registration, calculation, collection, remittance, invoices, and records.
        </p>
        <p>
            Payments may be processed by a Third-Party Service under its own terms. OKay Inc. does not receive or store complete payment-card credentials when they are collected directly by that provider. Authorization, settlement, currency conversion, disputes, reserves, reversals, chargebacks, or provider outages may affect a transaction.
        </p>
        <p>
            Except where OKay Inc. is expressly identified as the seller, payment facilitator, or merchant of record, payment for an offering is made to the Organization and refund obligations belong to the Organization. Platform subscription or usage fees paid directly to OKay Inc. are governed by the applicable plan or order and are non-refundable except as stated there or required by law.
        </p>
    </section>

    <section id="integrations" aria-labelledby="integrations-title">
        <h2 class="h3" id="integrations-title">11. Calendars, OAuth, and other integrations</h2>
        <p>
            You may choose to connect Google, Microsoft, or another Third-Party Service. By doing so, you authorize OKay Inc. to access and use the permitted account and calendar information through Appointment.to to provide the requested feature, such as sign-in, calendar selection, availability checks, conflict prevention, and creation, updating, or deletion of Appointment.to-related events.
        </p>
        <p>
            You are responsible for selecting the correct account and calendars, maintaining required third-party permissions, and reviewing synchronization results. Provider delays, revoked permissions, rate limits, duplicate events, time-zone settings, recurring-event behaviour, or inaccurate source data can cause conflicts or missed updates. OKay Inc. does not guarantee that an integration will be uninterrupted or error-free.
        </p>
        <p>
            You may disconnect an integration through available settings and may also revoke access with the provider. Our handling of Google and Microsoft API data is described in the <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.
        </p>
    </section>

    <section id="communications" aria-labelledby="communications-title">
        <h2 class="h3" id="communications-title">12. Electronic communications</h2>
        <p>
            You consent to receive electronic notices and operational messages needed to administer accounts, bookings, payments, security, integrations, and these Terms. Messages may be sent to contact details supplied by you or another authorized User. Delivery is not guaranteed; you must keep contact details current and check spam, filtering, and account settings.
        </p>
        <p>
            Organizations are responsible for ensuring that their marketing or other non-operational communications comply with consent, identification, unsubscribe, and recordkeeping requirements. They may not use the Service to send unlawful or misleading messages.
        </p>
    </section>

    <section id="content" aria-labelledby="content-title">
        <h2 class="h3" id="content-title">13. User Content and data</h2>
        <p>
            You retain ownership of your User Content. You grant OKay Inc. a worldwide, non-exclusive, royalty-free licence to host, store, reproduce, process, adapt, transmit, display, and otherwise use User Content through the Service only as reasonably necessary to operate, secure, support, and improve the Service; follow your instructions; enforce these Terms; and comply with law.
        </p>
        <p>
            You represent that you have all rights, authority, notices, and consents required to submit User Content and permit its processing. Do not submit unnecessary sensitive information, passwords, government identification numbers, complete payment-card numbers, unlawful content, malware, or information you are not authorized to disclose.
        </p>
        <p>
            We may generate and use aggregated or de-identified information that cannot reasonably identify an individual or Organization to measure, secure, analyze, and improve the Service, subject to applicable law and the integration-specific restrictions in our Privacy Policy.
        </p>
    </section>

    <section id="acceptable-use" aria-labelledby="acceptable-use-title">
        <h2 class="h3" id="acceptable-use-title">14. Acceptable use</h2>
        <p>You must not use or assist others in using the Service to:</p>
        <ul>
            <li>violate law, regulation, court order, professional duty, intellectual-property right, privacy right, or contractual obligation;</li>
            <li>offer, coordinate, encourage, or conceal dangerous, abusive, exploitative, fraudulent, discriminatory, or illegal conduct;</li>
            <li>harm, threaten, harass, stalk, deceive, impersonate, defame, or invade the privacy of another person;</li>
            <li>collect information without proper authority or use booking fields to obtain unnecessary passwords, financial credentials, health information, or government identifiers;</li>
            <li>upload malicious code, interfere with security or availability, probe for vulnerabilities, bypass access controls, scrape without permission, or impose an unreasonable load;</li>
            <li>send spam, manipulate reviews or availability, make fraudulent bookings, evade fees, or abuse refunds, coupons, gift cards, payment processes, or support channels;</li>
            <li>reverse engineer or copy the Service except where applicable law expressly permits it.</li>
        </ul>
        <p>
            We may investigate suspected misuse and preserve or disclose relevant information where reasonably necessary to protect Users, enforce these Terms, or comply with law.
        </p>
    </section>

    <section id="intellectual-property" aria-labelledby="intellectual-property-title">
        <h2 class="h3" id="intellectual-property-title">15. Appointment.to intellectual property</h2>
        <p>
            The Service, including its software, design, documentation, trademarks, logos, and content supplied by Appointment.to, is owned by OKay Inc. or its licensors and protected by intellectual-property laws. Subject to these Terms, we grant you a limited, revocable, non-exclusive, non-transferable right to access and use the Service for its intended purpose.
        </p>
        <p>
            Feedback may be used without restriction or compensation, provided we do not identify you as its source without permission. No rights are granted except those expressly stated.
        </p>
    </section>

    <section id="privacy" aria-labelledby="privacy-title">
        <h2 class="h3" id="privacy-title">16. Privacy</h2>
        <p>
            Our <a href="{{ route('legal.privacy') }}">Privacy Policy</a> explains how OKay Inc. handles personal information and Google and Microsoft API data through Appointment.to. Organizations may have separate privacy notices and are responsible for information they control. Review the relevant notices before submitting personal information.
        </p>
    </section>

    <section id="third-parties" aria-labelledby="third-parties-title">
        <h2 class="h3" id="third-parties-title">17. Third-party services and links</h2>
        <p>
            The Service may link to or interoperate with Third-Party Services. OKay Inc. does not control and is not responsible for their availability, accuracy, security, content, fees, decisions, acts, omissions, or data practices. Your use of them is governed by their own terms and may require a separate account. Enabling an integration authorizes the exchange of information needed to provide it.
        </p>
    </section>

    <section id="availability" aria-labelledby="availability-title">
        <h2 class="h3" id="availability-title">18. Service availability and changes</h2>
        <p>
            We may add, change, suspend, limit, or discontinue any part of the Service; establish or change usage limits; perform maintenance; or restrict a feature by location, plan, account, or integration. We aim to provide a reliable Service but do not guarantee uninterrupted availability, preservation of all data, delivery of every message, or compatibility with every device or third-party system.
        </p>
        <p>
            You are responsible for maintaining appropriate backups, business-continuity procedures, alternative communication methods, and independent records of information needed for legal, tax, safety, professional, or operational purposes.
        </p>
    </section>

    <section id="disclaimers" aria-labelledby="disclaimers-title">
        <h2 class="h3" id="disclaimers-title">19. Disclaimers of warranties</h2>
        <p class="fw-semibold text-uppercase">
            To the maximum extent permitted by law, the Service is provided "as is" and "as available." OKay Inc. and its owners, directors, officers, employees, contractors, affiliates, licensors, and service providers (collectively, the "OKay Inc. Parties") disclaim all express, implied, statutory, and collateral warranties and conditions, including merchantability, fitness for a particular purpose, title, non-infringement, accuracy, availability, security, quiet enjoyment, and any warranty arising from course of dealing or usage of trade.
        </p>
        <p>
            The OKay Inc. Parties do not warrant that the Service, a booking, an Organization, a Client, an offering, User Content, a calendar result, a payment, or a Third-Party Service will be safe, suitable, lawful, accurate, complete, timely, available, successful, profitable, error-free, or free from harmful components. Information provided through the Service is general operational information and is not medical, legal, financial, tax, insurance, safety, or other professional advice.
        </p>
        <p>
            Some jurisdictions do not allow the exclusion of certain warranties or consumer rights. In that case, the exclusions apply only to the fullest extent permitted, and non-waivable rights remain unaffected.
        </p>
    </section>

    <section id="release" aria-labelledby="release-title">
        <h2 class="h3" id="release-title">20. Release regarding Users and third parties</h2>
        <p>
            To the maximum extent permitted by law, you release and discharge the OKay Inc. Parties from claims, demands, damages, losses, and liabilities arising from or relating to: dealings or disputes between Users; an Organization's offering, premises, personnel, equipment, policies, advice, representations, or performance; a Client's conduct, information, attendance, or non-attendance; or a Third-Party Service.
        </p>
        <p>
            This release does not apply to a claim caused directly by an OKay Inc. Party to the extent the claim cannot lawfully be released. You waive any law that would limit a general release to claims known or suspected at the time of release, but only where that waiver is lawful and effective.
        </p>
    </section>

    <section id="liability" aria-labelledby="liability-title">
        <h2 class="h3" id="liability-title">21. Limitation of Liability</h2>
        <p class="fw-semibold text-uppercase">
            To the maximum extent permitted by law, the OKay Inc. Parties will not be liable for any indirect, incidental, special, exemplary, punitive, aggravated, or consequential loss or damage; loss of profits, revenue, business, opportunity, goodwill, data, or anticipated savings; service interruption; substitute services; personal decisions; or claims by another person, whether arising in contract, tort (including negligence), statute, strict liability, equity, or otherwise, even if advised that the loss was possible.
        </p>
        <p class="fw-semibold text-uppercase">
            To the maximum extent permitted by law, the aggregate liability of all OKay Inc. Parties for all claims arising out of or relating to the Service or these Terms will not exceed the greater of: (a) CAD $100; or (b) the amount you paid directly to OKay Inc. for the Service during the 12 months immediately before the event giving rise to the first claim.
        </p>
        <p>
            These limitations allocate risk between the parties and apply to the Service even if a limited remedy fails of its essential purpose. They do not limit liability for fraud, wilful misconduct, gross negligence, death or personal injury caused by negligence, or any other liability to the extent it cannot be limited under applicable law.
        </p>
    </section>

    <section id="indemnity" aria-labelledby="indemnity-title">
        <h2 class="h3" id="indemnity-title">22. Indemnification</h2>
        <p>
            To the maximum extent permitted by law, you will defend, indemnify, and hold harmless the OKay Inc. Parties from third-party claims, proceedings, damages, judgments, losses, liabilities, costs, and reasonable legal fees arising from or relating to:
        </p>
        <ul>
            <li>your access to or use of the Service, User Content, booking pages, offerings, bookings, communications, or transactions;</li>
            <li>your breach of these Terms, additional terms, law, professional duty, or another person's rights;</li>
            <li>your Organization, personnel, premises, equipment, services, advice, safety practices, taxes, payments, refunds, cancellations, contracts, or privacy practices;</li>
            <li>injury, death, property damage, or other harm caused or alleged to have been caused by your act, omission, instruction, product, service, or conduct;</li>
            <li>a dispute between you and another User or Third-Party Service.</li>
        </ul>
        <p>
            You have no obligation to indemnify an OKay Inc. Party for a claim to the extent finally determined to have resulted from that party's own conduct for which indemnification cannot lawfully be required. We may control the defence and settlement of an indemnified claim, and you will provide reasonable cooperation. You may not settle a claim in a way that admits wrongdoing by or imposes an obligation on an OKay Inc. Party without our written consent.
        </p>
    </section>

    <section id="termination" aria-labelledby="termination-title">
        <h2 class="h3" id="termination-title">23. Suspension and termination</h2>
        <p>
            You may stop using the Service at any time. Account deletion or cancellation does not automatically cancel bookings, payment obligations, Organization contracts, chargebacks, legal holds, or records another party is entitled or required to retain.
        </p>
        <p>
            We may suspend, restrict, or terminate access, remove content, cancel affected functionality, or take protective action if we reasonably believe there is a security threat, unlawful conduct, harm to a person, breach of these Terms, non-payment, excessive risk, abuse, or a legal or provider requirement. Where reasonable, we will provide notice and an opportunity to address the issue.
        </p>
        <p>
            Provisions that by their nature should survive termination remain effective, including ownership, payment obligations, disclaimers, releases, liability limits, indemnification, dispute terms, and general provisions.
        </p>
    </section>

    <section id="changes" aria-labelledby="changes-title">
        <h2 class="h3" id="changes-title">24. Changes to these Terms</h2>
        <p>
            We may update these Terms to reflect changes in the Service, law, risk, or business practices. We will post revised Terms with a new Last updated date and provide additional notice when required. Changes apply prospectively. If a material change requires consent, we will request it. Continuing to use the Service after an effective change constitutes acceptance where permitted by law.
        </p>
    </section>

    <section id="law" aria-labelledby="law-title">
        <h2 class="h3" id="law-title">25. Governing law and disputes</h2>
        <p>
            These Terms are governed by the laws of Ontario and the federal laws of Canada applicable there, without regard to conflict-of-law rules. Subject to non-waivable consumer rights and any law requiring another forum, the courts located in Ontario have exclusive jurisdiction over disputes arising from these Terms or the Service, and you submit to their jurisdiction.
        </p>
        <p>
            Before starting formal proceedings, each party will make a reasonable good-faith effort to resolve the dispute by written notice describing the issue and requested resolution. Either party may seek urgent injunctive or protective relief where delay could cause irreparable harm.
        </p>
    </section>

    <section id="general" aria-labelledby="general-title">
        <h2 class="h3" id="general-title">26. General terms</h2>
        <ul>
            <li><strong>Entire agreement:</strong> These Terms, the Privacy Policy, and applicable additional terms are the entire agreement about the Service and replace prior discussions on that subject.</li>
            <li><strong>Severability:</strong> If a provision is unenforceable, it will be enforced to the maximum lawful extent and the remaining provisions will continue.</li>
            <li><strong>No waiver:</strong> A failure to enforce a provision is not a waiver.</li>
            <li><strong>Assignment:</strong> You may not assign these Terms without our written consent. We may assign them as part of a reorganization, financing, merger, acquisition, asset transfer, or operation of the Service, subject to law.</li>
            <li><strong>Force majeure:</strong> We are not responsible for delay or failure caused by events beyond reasonable control, including internet or utility failures, labour disputes, disasters, epidemics, war, civil disorder, government action, cyberattack, or provider failure.</li>
            <li><strong>No third-party beneficiaries:</strong> Except for the OKay Inc. Parties entitled to rely on protections stated for them, these Terms do not create rights for another person.</li>
            <li><strong>Language:</strong> The parties request that these Terms and related documents be drawn up in English. Les parties demandent que les présentes modalités et les documents qui s'y rattachent soient rédigés en anglais.</li>
        </ul>
    </section>

    <section id="contact" aria-labelledby="contact-title" class="mb-0">
        <h2 class="h3" id="contact-title">27. Contact</h2>
        <address class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="fw-semibold mb-1">OKay Inc.</p>
                <p class="mb-1">Cornwall, Ontario, Canada</p>
                <p class="mb-3"><a href="mailto:privacy@appointment.to">privacy@appointment.to</a></p>
                <p class="small text-secondary mb-0">
                    Include enough information to identify the relevant account, Organization, booking, or issue. Do not send passwords, complete payment-card numbers, or unnecessary sensitive information.
                </p>
            </div>
        </address>
    </section>
</article>
@endsection