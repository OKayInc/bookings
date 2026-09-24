@extends('layouts.app')
@section('title', 'Finish setup')
@section('content')
<div class="page-heading">
    <div>
        <h1>Let’s get {{ $organization->name }} ready for bookings</h1>
        <p class="muted">Answer a few everyday questions. Appointment.To will create a working appointment, your normal hours, and your first resource.</p>
    </div>
</div>

<form method="post" action="{{ route('onboarding.store') }}" class="form-stack guided-setup-form">
    @csrf

    <div class="section-card">
        <h2>1. What kind of business do you run?</h2>
        <div class="field">
            <label for="guided_business_type">Business type</label>
            <select id="guided_business_type" name="guided_business_type" required autofocus>
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
            <div class="muted">This only chooses sensible defaults. It does not limit what you can configure later.</div>
        </div>
    </div>

    <div class="section-card">
        <h2>2. What do customers book?</h2>
        <div class="field">
            <label for="guided_appointment_name">Appointment name</label>
            <input id="guided_appointment_name" name="guided_appointment_name" value="{{ old('guided_appointment_name') }}" required placeholder="Example: Family Photo Session">
        </div>

        <div class="row">
            <div class="field">
                <label for="guided_duration_minutes">About how long does it take?</label>
                <select id="guided_duration_minutes" name="guided_duration_minutes" required>
                    @foreach([15, 30, 45, 60, 90, 120, 180, 240] as $minutes)
                        <option value="{{ $minutes }}" @selected((int) old('guided_duration_minutes', 60) === $minutes)>
                            {{ $minutes < 60 ? $minutes.' minutes' : ($minutes % 60 === 0 ? ($minutes / 60).' hour'.($minutes > 60 ? 's' : '') : floor($minutes / 60).' hr '.($minutes % 60).' min') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="guided_location_mode">Where does it happen?</label>
                <select id="guided_location_mode" name="guided_location_mode" required>
                    <option value="in_person" @selected(old('guided_location_mode', 'in_person') === 'in_person')>In person</option>
                    <option value="online" @selected(old('guided_location_mode') === 'online')>Online</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="field">
                <label for="guided_pricing_mode">Do customers pay for it?</label>
                <select id="guided_pricing_mode" name="guided_pricing_mode" required>
                    <option value="free" @selected(old('guided_pricing_mode', 'free') === 'free')>No — it is free</option>
                    <option value="fixed" @selected(old('guided_pricing_mode') === 'fixed')>Yes — one fixed price</option>
                </select>
            </div>
            <div class="field" id="guided-fixed-price-field">
                <label for="guided_fixed_price">Price ({{ $organization->currency }})</label>
                <input id="guided_fixed_price" type="number" min="0.01" step="0.01" name="guided_fixed_price" value="{{ old('guided_fixed_price') }}" placeholder="150.00">
            </div>
        </div>

        <div class="row">
            <div class="field">
                <label for="guided_attendance_mode">Can more than one customer book the same time?</label>
                <select id="guided_attendance_mode" name="guided_attendance_mode" required>
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

    <div class="section-card">
        <h2>3. When can people normally book?</h2>
        <div class="field">
            <label>Days</label>
            <div class="guided-weekdays">
                @foreach([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'] as $value => $label)
                    <label class="guided-weekday">
                        <input type="checkbox" name="guided_weekdays[]" value="{{ $value }}" @checked(in_array($value, old('guided_weekdays', [1,2,3,4,5]), true))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="row three">
            <div class="field">
                <label for="guided_start_time">From</label>
                <input id="guided_start_time" type="time" name="guided_start_time" value="{{ old('guided_start_time', '09:00') }}" required>
            </div>
            <div class="field">
                <label for="guided_end_time">Until</label>
                <input id="guided_end_time" type="time" name="guided_end_time" value="{{ old('guided_end_time', '17:00') }}" required>
            </div>
            <div class="field">
                <label for="guided_booking_notice_hours">How much notice do you need?</label>
                <select id="guided_booking_notice_hours" name="guided_booking_notice_hours" required>
                    @foreach([0 => 'No minimum', 1 => '1 hour', 4 => '4 hours', 24 => '1 day', 48 => '2 days', 72 => '3 days', 168 => '1 week'] as $hours => $label)
                        <option value="{{ $hours }}" @selected((int) old('guided_booking_notice_hours', 24) === $hours)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="guided-setup-finish d-flex flex-column flex-sm-row gap-2 align-items-sm-center">
        <button class="btn btn-primary" type="submit">Create my first booking page</button>
        <span class="muted">You can change every setting afterward.</span>
    </div>
</form>

<form method="post" action="{{ route('onboarding.skip') }}" class="mt-4">
    @csrf
    <button class="btn btn-link px-0" type="submit">Skip guided setup and configure manually</button>
</form>

<script>
(() => {
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
        const fixed = pricing.value === 'fixed';
        priceField.hidden = !fixed;
        price.disabled = !fixed;

        const group = attendance.value === 'group';
        capacityField.hidden = !group;
        capacity.disabled = !group;
    };

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
