@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="card">
<h1>{{ $organization->name }}</h1>
<p class="muted">Active organization · {{ $organization->timezone }} · {{ $organization->currency }}</p>
<div class="actions"><a class="btn" href="{{ route('organizations.index') }}">Switch organization</a><a class="btn" href="{{ route('admin.health') }}">System health</a></div>
</div>
@include('dashboard.upcoming-bookings')
<div class="grid">
<div class="card"><div class="stat">{{ $memberCount }}</div><div class="muted">Members</div></div>
<div class="card"><div class="stat">{{ $resourceCount }}</div><div class="muted">Resources</div></div>
<div class="card"><div class="stat">{{ $appointmentTypeCount }}</div><div class="muted">Appointment types</div></div>
</div>
@endsection
