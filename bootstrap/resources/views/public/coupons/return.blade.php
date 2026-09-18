@extends('layouts.public')
@section('title', $successful ? 'Purchase complete' : 'Purchase not completed')
@section('content')<div class="card text-center"><h1>{{ $successful ? 'Purchase complete' : 'Purchase not completed' }}</h1><p>{{ $message }}</p><a class="btn" href="{{ route('public.appointment-types.index', $organization->slug) }}">Return to {{ $organization->name }}</a></div>@endsection
