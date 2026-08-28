<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm" aria-label="Backend navigation">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="{{ auth()->check() ? route('dashboard') : route('login') }}">@if($activeOrganization?->logo_url)<img src="{{ $activeOrganization->logo_url }}" alt="{{ $activeOrganization->name }} logo" style="height:32px;width:auto;max-width:120px;object-fit:contain">@endif<span>{{ config('app.name') }}</span></a>

        @auth
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#backendNavbar" aria-controls="backendNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="backendNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link @if(request()->routeIs('dashboard')) active @endif" @if(request()->routeIs('dashboard')) aria-current="page" @endif href="{{ route('dashboard') }}">Dashboard</a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle @if(request()->routeIs('appointment-types.*', 'availability.*', 'bookings.*', 'staff-confirmations.*', 'calendar-connections.*')) active @endif" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Scheduling</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('appointment-types.index') }}">Appointment Types</a></li>
                            <li><a class="dropdown-item" href="{{ route('availability.index') }}">Availability</a></li>
                            <li><a class="dropdown-item" href="{{ route('bookings.index') }}">Bookings</a></li>
                            <li><a class="dropdown-item" href="{{ route('calendar-connections.index') }}">Calendar connections</a></li>
                            <li><a class="dropdown-item" href="{{ route('staff-confirmations.mine') }}">My confirmations</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle @if(request()->routeIs('resources.*', 'organizations.*', 'organization-members.*', 'admin.*')) active @endif" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Organization</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('resources.index') }}">Resources</a></li>
                            @if($activeOrganization && auth()->user()->can('update', $activeOrganization))
                                <li><a class="dropdown-item" href="{{ route('organization-members.index') }}">Members</a></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('organizations.index') }}">Organizations</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('admin.health') }}">System health</a></li>
                        </ul>
                    </li>
                </ul>

                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 gap-lg-3 mt-3 mt-lg-0">
                    @if($activeOrganization)
                        @if($availableOrganizations->count() > 1)
                            <div class="dropdown d-grid d-lg-block organization-switcher">
                                <button class="current-organization btn btn-outline-light btn-sm dropdown-toggle text-start text-lg-center text-wrap" type="button" id="organizationSwitcher" data-bs-toggle="dropdown" aria-expanded="false" title="Switch organization">
                                    <span class="current-organization-label">Organization:</span>
                                    <strong>{{ $activeOrganization->name }}</strong>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-lg-end" aria-labelledby="organizationSwitcher">
                                    @foreach($availableOrganizations as $organizationOption)
                                        <li>
                                            @if(hash_equals((string) $organizationOption->getKey(), (string) $activeOrganization->getKey()))
                                                <span class="dropdown-item-text active" aria-current="true">✓ {{ $organizationOption->name }}</span>
                                            @else
                                                <form method="post" action="{{ route('organizations.switch', $organizationOption) }}" class="m-0">
                                                    @csrf
                                                    <button class="dropdown-item" type="submit">{{ $organizationOption->name }}</button>
                                                </form>
                                            @endif
                                        </li>
                                    @endforeach
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('organizations.index') }}">Manage organizations</a></li>
                                </ul>
                            </div>
                        @else
                            <span class="current-organization badge text-bg-secondary text-wrap" title="Current organization">
                                <span class="current-organization-label">Organization:</span>
                                <strong>{{ $activeOrganization->name }}</strong>
                            </span>
                        @endif
                    @endif
                    <form method="post" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button class="btn btn-outline-light btn-sm w-100" type="submit">Log out</button>
                    </form>
                </div>
            </div>
        @else
            <div class="d-flex gap-2">
                <a class="btn btn-outline-light btn-sm" href="{{ route('login') }}">Log in</a>
                <a class="btn btn-light btn-sm" href="{{ route('register') }}">Register</a>
            </div>
        @endauth
    </div>
</nav>

<main class="py-4 py-lg-5">
    <div class="container-xl">
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
