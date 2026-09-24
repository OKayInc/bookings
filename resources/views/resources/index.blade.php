@extends('layouts.app')
@section('title', 'Resources')
@section('content')
<div class="actions" style="justify-content:space-between"><h1>Resources</h1><a class="btn btn-primary" href="{{ route('resources.create') }}">Add resource</a></div>

<form method="get" action="{{ route('resources.index') }}" class="card card-body mb-3" role="search">
<div class="row g-2 align-items-end">
<div class="col-12 col-lg-7">
<label class="form-label mb-1" for="resource-search">Search resources</label>
<input class="form-control" id="resource-search" name="search" type="search" value="{{ $search }}" placeholder="Search by resource name" autocomplete="off">
</div>
<div class="col-12 col-sm-6 col-lg-3">
<label class="form-label mb-1" for="resource-type">Type</label>
<select class="form-select" id="resource-type" name="type">
<option value="">All types</option>
@foreach($resourceTypes as $value => $label)
<option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
@endforeach
</select>
</div>
<div class="col-12 col-sm-6 col-lg-2 d-flex gap-2">
<button class="btn btn-primary flex-grow-1" type="submit">Search</button>
@if($search !== '' || $type !== null)
<a class="btn btn-outline-secondary" href="{{ route('resources.index') }}" aria-label="Clear resource filters">Clear</a>
@endif
</div>
</div>
</form>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Name</th><th>Type / stock</th><th>Default deposit</th><th>Person</th><th>Timezone</th><th>Organization settings</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($resources as $resource)
<tr><td>{{ $resource->name }}</td><td>@include('resources.partials.type-icon', ['type' => $resource->type])@if($resource->usesQuantityInventory())<br><span class="badge">{{ $resource->inventory_quantity }} pieces</span>@elseif($resource->type === 'equipment')<br><span class="badge">Quantity tracking off</span>@endif</td><td>@if($resource->type === 'person' || $resource->deposit_amount_minor === null || $resource->deposit_amount_minor === 0)—@else{{ app(\App\Domain\Money\MoneyService::class)->format($resource->deposit_amount_minor, $organization->currency) }}@if($resource->usesQuantityInventory())<span class="muted"> / piece</span>@endif @endif</td><td>{{ $resource->type === 'person' ? ($resource->person?->full_name ?? '—') : '—' }}</td><td>{{ $resource->type === 'person' ? ($resource->timezone ?? 'Organization default') : '—' }}</td><td>
@php
    $holidayRegion = $resource->pivot->holiday_region ?: ($resourceHolidaySuggestions[$resource->uuid] ?? null);
@endphp
@if(hash_equals($resource->organization_id, $organization->getKey()))
<div class="d-flex flex-column align-items-start gap-1">
<span class="badge">{{ $resource->pivot->is_required_by_default ? 'Required' : 'Optional' }}</span>
@if($resource->type === 'person')
<span class="badge {{ $resource->pivot->enforce_holidays ? 'text-bg-info' : '' }}">{{ $resource->pivot->enforce_holidays ? 'Holidays: '.($holidayRegions[$holidayRegion] ?? $holidayRegion) : 'Resource holidays off' }}</span>
@endif
</div>
@else
<form method="post" action="{{ route('resources.organization-settings.update', $resource) }}" class="d-flex flex-column gap-2">@csrf @method('PATCH')
<select name="default_requirement" class="form-select form-select-sm">
<option value="required" @selected($resource->pivot->is_required_by_default)>Required</option>
<option value="optional" @selected(! $resource->pivot->is_required_by_default)>Optional</option>
</select>
@if($resource->type === 'person')
<label class="small"><input type="checkbox" name="enforce_holidays" value="1" @checked($resource->pivot->enforce_holidays)> Enforce resource holidays</label>
<select name="holiday_region" class="form-select form-select-sm">
<option value="">Choose region</option>
@foreach($holidayRegions as $code => $label)<option value="{{ $code }}" @selected($holidayRegion === $code)>{{ $label }}</option>@endforeach
</select>
@endif
<button class="btn btn-sm" type="submit">Save</button>
</form>
@endif
</td><td><span class="badge">{{ $resource->is_active ? 'Active' : 'Inactive' }}</span></td><td>@if(hash_equals($resource->organization_id, $organization->getKey()))<div class="d-flex flex-wrap gap-1"><a class="btn" href="{{ route('resources.edit', $resource) }}">Edit</a>@if((int) $resource->appointments_count === 0 && (int) $resource->booking_holds_count === 0 && (int) $resource->confirmations_count === 0)<form method="post" action="{{ route('resources.destroy', $resource) }}" onsubmit="return confirm('Delete this unused resource? Its appointment-type assignments, availability, and calendar configuration will also be removed.');">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Delete</button></form>@endif</div>@else<span class="badge">Shared from {{ $resource->organization->name }}</span>@endif</td></tr>
@empty<tr><td colspan="8">{{ $search !== '' || $type !== null ? 'No resources match your search or filter.' : 'No resources yet.' }}</td></tr>@endforelse
</tbody></table></div></div>
@endsection
