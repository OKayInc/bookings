@extends('layouts.app')
@section('content')
<h1>API keys</h1>
<p>Use both <code>X-CLIENT-API-KEY</code> and <code>X-ORGANIZATION-API-KEY</code> with requests to <code>/api/v1</code>. API access requires a paid organization and your current organization role applies.</p>
@if($newKey)
<div class="alert alert-warning"><strong>Copy your new {{ $keyKind }} key now. It is shown only in this response.</strong><p class="text-break mb-0"><code>{{ $newKey }}</code></p></div>
@endif
@foreach(['client', 'organization'] as $kind)
@if($kind === 'client' || auth()->user()->can('update', $organization))
<div class="card mb-3"><div class="card-body">
<h2 class="h5">{{ ucfirst($kind) }} key</h2>
<p>{{ $kind === 'client' ? 'Your personal key works with organizations where you have active membership. Regenerating it invalidates your previous key everywhere.' : 'Regenerating this key invalidates all integrations using this organization’s previous key.' }}</p>
<p>Status: {{ ($kind === 'client' ? auth()->user()->api_key_hash : $organization->api_key_hash) ? 'Configured' : 'Not configured' }}</p>
<form method="post" action="{{ route('api-keys.update') }}">@csrf
<input type="hidden" name="kind" value="{{ $kind }}">
<button class="btn btn-primary" name="action" value="regenerate" @disabled($organization->plan_tier !== \App\Enums\OrganizationPlanTier::Paid)>Generate / regenerate</button>
<button class="btn btn-outline-danger" name="action" value="revoke">Revoke</button>
</form></div></div>
@endif
@endforeach
@endsection
