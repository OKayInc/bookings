@extends('layouts.app')
@section('title', 'External resource sharing')
@section('content')
<div class="actions flex-wrap"><h1>External resource sharing</h1><a class="btn" href="{{ route('resources.index') }}">Resources</a></div>
<p>Share a resource with another business without giving its owner access to your organization.</p>
@if(session('share_code'))
<div class="alert alert-info">
<label for="share-code">Send this one-time code privately to the invited owner. It expires in seven days.</label>
<input id="share-code" class="form-control" readonly value="{{ session('share_code') }}" onclick="this.select()">
</div>
@endif
<div class="row g-3">
<div class="col-12 col-lg-6"><form class="card card-body gap-2" method="post" action="{{ route('resource-sharing.invite') }}">
@csrf
<h2 class="h5">Invite another owner</h2>
<label for="share-resource">Resource</label>
<select class="form-select" id="share-resource" name="resource_uuid" required>
@foreach($resources as $resource)<option value="{{ $resource->uuid }}">{{ $resource->name }}</option>@endforeach
</select>
<label for="share-email">Receiving owner's account email</label>
<input class="form-control" id="share-email" name="email" type="email" maxlength="254" required>
<button class="btn btn-primary" type="submit" @disabled($resources->isEmpty())>Generate invitation code</button>
</form></div>
<div class="col-12 col-lg-6"><form class="card card-body gap-2" method="post" action="{{ route('resource-sharing.accept') }}">
@csrf
<h2 class="h5">Accept an invitation</h2>
<p>Switch to the organization that should receive the resource before accepting.</p>
<label for="accept-code">Invitation code</label>
<input class="form-control" id="accept-code" name="code" maxlength="64" autocomplete="off" required>
<button class="btn btn-primary" type="submit">Accept resource</button>
</form></div>
</div>
<div class="card mt-3"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Resource</th><th>Owner</th><th>Recipient</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($shares as $share)
<tr><td>{{ $share->resource_name }}</td><td>{{ $share->owner_name }}</td><td>{{ $share->recipient_name ?? $share->recipient_email }}</td><td>{{ $share->status === 'pending' && now()->gte(\Illuminate\Support\Carbon::parse($share->expires_at)) ? 'Expired' : ucfirst($share->status) }}</td><td>
<form method="post" action="{{ route('resource-sharing.revoke', $share->id) }}" onsubmit="return confirm('End this resource share or cancel this invitation?');">@csrf
<button class="btn btn-outline-danger btn-sm" type="submit">{{ $share->status === 'pending' ? 'Cancel' : 'End share' }}</button></form>
</td></tr>
@empty<tr><td colspan="5">No external resource shares.</td></tr>@endforelse
</tbody></table></div></div>
{{ $shares->links('pagination::bootstrap-5') }}
@endsection
