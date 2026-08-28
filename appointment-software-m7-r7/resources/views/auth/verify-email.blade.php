@extends('layouts.app')
@section('title', 'Verify email')
@section('content')
<div class="card shadow-sm mx-auto" style="max-width: 680px;">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h3 mb-3">Verify your email address</h1>
        <p class="text-secondary">
            We sent a verification link to <strong>{{ auth()->user()->email }}</strong>.
            Verify that address before accessing organization and scheduling administration.
        </p>

        @if(session('message'))
            <div class="alert alert-success" role="alert">{{ session('message') }}</div>
        @endif

        <p class="mb-4">If the message does not arrive, you can request another verification email.</p>

        <div class="d-flex flex-column flex-sm-row gap-2">
            <form method="post" action="{{ route('verification.send') }}">
                @csrf
                <button class="btn btn-primary" type="submit">Resend verification email</button>
            </form>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-outline-secondary" type="submit">Log out</button>
            </form>
        </div>
    </div>
</div>
@endsection
