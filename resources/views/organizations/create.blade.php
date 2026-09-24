@extends('layouts.app')
@section('title', 'Create organization')
@section('content')
<div class="page-heading">
    <div>
        <h1>Set up your organization</h1>
        <p class="muted">Answer a few everyday questions. Appointment.To will create a simple starting point that you can change anytime.</p>
    </div>
</div>

<form method="post" enctype="multipart/form-data" action="{{ route('organizations.store') }}" class="form-stack guided-setup-form">
    @csrf

    <div class="section-card">
        <h2>1. Tell us about your business</h2>
        <div class="field">
            <label for="name">Organization name</label>
            <input id="name" name="name" value="{{ old('name') }}" required autofocus placeholder="Your business or organization name">
        </div>

        <div class="row">
            <div class="field">
                <label for="timezone">Timezone</label>
                <select id="timezone" name="timezone" required>
                    @foreach($timezones as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone', 'America/Toronto') === $timezone)>{{ $timezone }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="currency">Currency</label>
                <select id="currency" name="currency" required>
                    @foreach($currencies as $code => $name)
                        <option value="{{ $code }}" @selected(old('currency', 'CAD') === $code)>{{ $code }} — {{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label for="guided_business_type">What kind of business do you run?</label>
            <select id="guided_business_type" name="guided_business_type">
                @foreach([
                    'photography' => 'Photography',
                    'beauty' => 'Hair / beauty',
                    'consulting' => 'Consulting / professional services',
                    'wellness' => 'Medical / wellness',
                    'home_services' => 'Home services',
                    'fitness' => 'Fitness / classes',
                    'rental' => 'Equipment / room rental',
                    'events' => 'Events',
                    'other' => 'Other',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('guided_business_type', 'other') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="muted">This only helps us choose sensible wording and defaults. It does not limit what Appointment.To can do.</div>
        </div>
    </div>

    <div class="section-card">
        <div class="guided-setup-heading">
            <div>
                <h2>2. Create your first appointment</h2>
                <p class="muted">Recommended. We will build a normal appointment type from these answers.</p>
            </div>
            <label class="inline-check">
                <input type="hidden" name="guided_setup" value="0">
                <input id="guided_setup" type="checkbox" name="guided_setup" value="1" @checked((bool) old('guided_setup', true))>
                Create a starter appointment
            </label>
        </div>

        <div id="guided-setup-fields">
            <div class="field">
                <label for="guided_appointment_name">What do customers usually book with you?</label>
                <input id="guided_appointment_name" name="guided_appointment_name" value="{{ old('guided_appointment_name') }}" placeholder="Example: Family Photo Session">
            </div>

            <div class="row">
                <div class="field">
                    <label for="guided_duration_minutes">About how long does it take?</label>
                    <select id="guided_duration_minutes" name="guided_duration_minutes">
                        @foreach([15, 30, 45, 60, 90, 120, 180, 240] as $minutes)
                            <option value="{{ $minutes }}" @selected((int) old('guided_duration_minutes', 60) === $minutes)>
                                {{ $minutes < 60 ? $minutes.' minutes' : ($minutes % 60 === 0 ? ($minutes / 60).' hour'.($minutes > 60 ? 's' : '') : floor($minutes / 60).' hr '.($minutes % 60).' min') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="guided_location_mode">Where does it happen?</label>
                    <select id="guided_location_mode" name="guided_location_mode">
                        <option value="in_person" @selected(old('guided_location_mode', 'in_person') === 'in_person')>In person</option>
                        <option value="online" @selected(old('guided_location_mode') === 'online')>Online</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="guided_pricing_mode">Do customers pay for it?</label>
                    <select id="guided_pricing_mode" name="guided_pricing_mode">
                        <option value="free" @selected(old('guided_pricing_mode', 'free') === 'free')>No — it is free</option>
                        <option value="fixed" @selected(old('guided_pricing_mode') === 'fixed')>Yes — one fixed price</option>
                    </select>
                </div>
                <div class="field" id="guided-fixed-price-field">
                    <label for="guided_fixed_price">Price</label>
                    <input id="guided_fixed_price" type="number" min="0.01" step="0.01" name="guided_fixed_price" value="{{ old('guided_fixed_price') }}" placeholder="150.00">
                    <div class="muted">In your organization currency.</div>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="guided_attendance_mode">Can more than one customer book the same time?</label>
                    <select id="guided_attendance_mode" name="guided_attendance_mode">
                        <option value="single" @selected(old('guided_attendance_mode', 'single') === 'single')>No — one booking at a time</option>
                        <option value="group" @selected(old('guided_attendance_mode') === 'group')>Yes — this is a group/class</option>
                    </select>
                </div>
                <div class="field" id="guided-capacity-field">
                    <label for="guided_capacity">Maximum people</label>
                    <input id="guided_capacity" type="number" min="2" max="100000" name="guided_capacity" value="{{ old('guided_capacity', 10) }}">
                </div>
            </div>

            <input type="hidden" name="guided_use_owner_resource" value="0">
            <label class="inline-check">
                <input id="guided_use_owner_resource" type="checkbox" name="guided_use_owner_resource" value="1" @checked((bool) old('guided_use_owner_resource', true))>
                This appointment requires me personally to be available
            </label>
            <div class="muted">We will create you as the first person resource. You can add staff, rooms, or equipment later.</div>
        </div>
    </div>

    <div class="section-card" id="guided-availability-section">
        <h2>3. When can people normally book?</h2>
        <div class="field">
            <label>Days</label>
            <div class="guided-weekdays">
                @foreach([
                    1 => 'Mon',
                    2 => 'Tue',
                    3 => 'Wed',
                    4 => 'Thu',
                    5 => 'Fri',
                    6 => 'Sat',
                    0 => 'Sun',
                ] as $value => $label)
                    <label class="guided-weekday">
                        <input type="checkbox" name="guided_weekdays[]" value="{{ $value }}"
                            @checked(in_array($value, old('guided_weekdays', [1, 2, 3, 4, 5]), true))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="row three">
            <div class="field">
                <label for="guided_start_time">From</label>
                <input id="guided_start_time" type="time" name="guided_start_time" value="{{ old('guided_start_time', '09:00') }}">
            </div>
            <div class="field">
                <label for="guided_end_time">Until</label>
                <input id="guided_end_time" type="time" name="guided_end_time" value="{{ old('guided_end_time', '17:00') }}">
            </div>
            <div class="field">
                <label for="guided_booking_notice_hours">How much notice do you need?</label>
                <select id="guided_booking_notice_hours" name="guided_booking_notice_hours">
                    @foreach([
                        0 => 'No minimum',
                        1 => '1 hour',
                        4 => '4 hours',
                        24 => '1 day',
                        48 => '2 days',
                        72 => '3 days',
                        168 => '1 week',
                    ] as $hours => $label)
                        <option value="{{ $hours }}" @selected((int) old('guided_booking_notice_hours', 24) === $hours)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <p class="muted">These become your organization’s default hours. Staff and appointment types can have different schedules later.</p>
    </div>

    <div class="guided-setup-finish">
        <button class="btn btn-primary" type="submit">Create my organization</button>
        <p class="muted mb-0">After creation, we will open the starter appointment so you can review or change anything.</p>
    </div>
</form>

<script>
(() => {
    const enabled = document.getElementById('guided_setup');
    const fields = document.getElementById('guided-setup-fields');
    const availability = document.getElementById('guided-availability-section');
    const pricing = document.getElementById('guided_pricing_mode');
    const priceField = document.getElementById('guided-fixed-price-field');
    const price = document.getElementById('guided_fixed_price');
    const attendance = document.getElementById('guided_attendance_mode');
    const capacityField = document.getElementById('guided-capacity-field');
    const capacity = document.getElementById('guided_capacity');
    const business = document.getElementById('guided_business_type');
    const appointmentName = document.getElementById('guided_appointment_name');

    const suggestions = {
        photography: 'Photo Session',
        beauty: 'Appointment',
        consulting: 'Consultation',
        wellness: 'Wellness Appointment',
        home_services: 'Service Visit',
        fitness: 'Training Session',
        rental: 'Rental',
        events: 'Event',
        other: 'Appointment',
    };

    let nameWasEdited = appointmentName?.value?.trim() !== '';

    const refresh = () => {
        const active = enabled?.checked ?? false;
        fields.hidden = !active;
        availability.hidden = !active;

        fields.querySelectorAll('input, select, textarea').forEach((control) => {
            if (control.type !== 'hidden') control.disabled = !active;
        });
        availability.querySelectorAll('input, select, textarea').forEach((control) => {
            control.disabled = !active;
        });

        if (!active) return;

        const fixed = pricing.value === 'fixed';
        priceField.hidden = !fixed;
        price.disabled = !fixed;

        const group = attendance.value === 'group';
        capacityField.hidden = !group;
        capacity.disabled = !group;
    };

    enabled?.addEventListener('change', refresh);
    pricing?.addEventListener('change', refresh);
    attendance?.addEventListener('change', refresh);
    appointmentName?.addEventListener('input', () => { nameWasEdited = true; });
    business?.addEventListener('change', () => {
        if (!nameWasEdited && appointmentName) {
            appointmentName.value = suggestions[business.value] || 'Appointment';
        }
    });

    if (!nameWasEdited && appointmentName && business) {
        appointmentName.value = suggestions[business.value] || 'Appointment';
    }

    refresh();
})();
</script>
@endsection
