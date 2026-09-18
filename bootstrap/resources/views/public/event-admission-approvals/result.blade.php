@extends('layouts.public')
@section('title', 'Admission decision saved')
@section('content')
<div class="narrow-card card">
    <h1>Decision saved</h1>
    <p>The admission request for booking <strong>{{ $approval->booking->reference }}</strong> was <strong>{{ strtolower($approval->status->label()) }}</strong>.</p>
    <p>The booking workflow has been updated. Other coordinator response links for this request are no longer active.</p>
</div>
@endsection
