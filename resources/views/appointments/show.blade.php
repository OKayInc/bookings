@extends('layouts.app')
@section('title', 'Appointment · '.$appointment->appointmentType->name)
@if($showAppointmentAdvertisement)
    @push('head')
        <meta name="referrer" content="no-referrer">
        <script async crossorigin="anonymous" src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('plans.adsense.client') }}"></script>
    @endpush
@endif
@section('content')
<div class="page-heading"><h1>{{ $appointment->appointmentType->name }}</h1><p>Appointment details</p></div>
<div class="card">
    <h2 class="h4">Schedule</h2>
    <p>{{ $appointment->starts_at_utc->setTimezone($appointment->scheduling_timezone)->format('D, M j Y · g:i A') }} – {{ $appointment->ends_at_utc->setTimezone($appointment->scheduling_timezone)->format('g:i A') }} ({{ $appointment->scheduling_timezone }})</p>
    <p>{{ $appointment->bookings->count() }} active booking(s)</p>
</div>
@if($showAppointmentAdvertisement)
    @include('partials.plan-advertisement')
@endif
<div class="card">
    <h2 class="h4">Bookings</h2>
    @forelse($appointment->bookings as $booking)
        <p><a href="{{ route('bookings.show', $booking) }}">View booking {{ $loop->iteration }}</a></p>
    @empty
        <p>No active bookings.</p>
    @endforelse
</div>
@endsection
