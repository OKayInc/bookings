@extends('layouts.public')
@section('title', 'Selected time expired')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="card shadow-sm text-center p-4 p-lg-5">
            <div class="display-5 mb-3" aria-hidden="true">⌛</div>
            <h1>Your selected time has expired</h1>
            <p class="lead mt-3">The temporary hold for {{ $type->name }} has ended, so this booking form can no longer be submitted.</p>
            <p class="text-body-secondary">Please return to the calendar and choose an available date and time again. If you already submitted the booking, check your email for its confirmation.</p>
            <div class="mt-3">
                <a id="choose-another-time" class="btn btn-primary btn-lg" href="{{ $returnUrl }}">← Back to choose a date</a>
            </div>
        </div>
    </div>
</div>
@endsection
