<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/page-loader.js') }}?v=m9-r6" defer></script>
    @stack('head')
</head>
<body class="bg-body-tertiary">
@include('layouts.partials.page-loader')
<nav class="navbar navbar-dark bg-dark shadow-sm" aria-label="Public navigation">
    <div class="container-xl">
        @isset($organization)
            <a class="navbar-brand fw-semibold text-truncate d-flex align-items-center gap-2" href="{{ route('public.appointment-types.index', $organization->slug) }}">@if($organization->logo_url)<img src="{{ $organization->logo_url }}" alt="{{ $organization->name }} logo" style="height:32px;width:auto;max-width:120px;object-fit:contain">@endif<span>{{ $organization->name }}</span></a>
        @else
            <span class="navbar-brand fw-semibold mb-0">{{ config('app.name') }}</span>
        @endisset
    </div>
</nav>

<main class="py-4 py-lg-5">
    <div class="container-xl public-container">
        @if(session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>Please correct the following:</strong>
                <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
@stack('scripts')
</body>
</html>
