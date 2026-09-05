@extends('layouts.app')
@section('title', 'Resources')
@section('content')
<div class="actions" style="justify-content:space-between"><h1>Resources</h1><a class="btn btn-primary" href="{{ route('resources.create') }}">Add resource</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Name</th><th>Type / stock</th><th>Default deposit</th><th>Person</th><th>Timezone</th><th>Organization settings</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($resources as $resource)
<tr><td>{{ $resource->name }}</td><td>{{ $resource->type }}@if($resource->usesQuantityInventory())<br><span class="badge">{{ $resource->inventory_quantity }} pieces</span>@elseif($resource->type === 'equipment')<br><span class="badge">Quantity tracking off</span>@endif</td><td>@if($resource->deposit_amount_minor === null || $resource->deposit_amount_minor === 0)—@else{{ app(\App\Domain\Money\MoneyService::class)->format($resource->deposit_amount_minor, $organization->currency) }}@if($resource->usesQuantityInventory())<span class="muted"> / piece</span>@endif @endif</td><td>{{ $resource->person?->full_name ?? '—' }}</td><td>{{ $resource->timezone ?? 'Organization default' }}</td><td>
@php
    $holidayRegion = $resource->pivot->holiday_region ?: ($resourceHolidaySuggestions[$resource->uuid] ?? null);
@endphp
@if(hash_equals($resource->organization_id, $organization->getKey()))
<div class="d-flex flex-column align-items-start gap-1">
<span class="badge">{{ $resource->pivot->is_required_by_default ? 'Required' : 'Optional' }}</span>
<span class="badge {{ $resource->pivot->enforce_holidays ? 'text-bg-info' : '' }}">{{ $resource->pivot->enforce_holidays ? 'Holidays: '.($holidayRegions[$holidayRegion] ?? $holidayRegion) : 'Resource holidays off' }}</span>
</div>
@else
<form method="post" action="{{ route('resources.organization-settings.update', $resource) }}" class="d-flex flex-column gap-2">@csrf @method('PATCH')
<select name="default_requirement" class="form-select form-select-sm">
<option value="required" @selected($resource->pivot->is_required_by_default)>Required</option>
<option value="optional" @selected(! $resource->pivot->is_required_by_default)>Optional</option>
</select>
<label class="small"><input type="checkbox" name="enforce_holidays" value="1" @checked($resource->pivot->enforce_holidays)> Enforce resource holidays</label>
<select name="holiday_region" class="form-select form-select-sm">
<option value="">Choose region</option>
@foreach($holidayRegions as $code => $label)<option value="{{ $code }}" @selected($holidayRegion === $code)>{{ $label }}</option>@endforeach
</select>
<button class="btn btn-sm" type="submit">Save</button>
</form>
@endif
</td><td><span class="badge">{{ $resource->is_active ? 'Active' : 'Inactive' }}</span></td><td>@if(hash_equals($resource->organization_id, $organization->getKey()))<div class="d-flex flex-wrap gap-1"><a class="btn" href="{{ route('resources.edit', $resource) }}">Edit</a>@if((int) $resource->appointments_count === 0 && (int) $resource->booking_holds_count === 0 && (int) $resource->confirmations_count === 0)<form method="post" action="{{ route('resources.destroy', $resource) }}" onsubmit="return confirm('Delete this unused resource? Its appointment-type assignments, availability, and calendar configuration will also be removed.');">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Delete</button></form>@endif</div>@else<span class="badge">Shared from {{ $resource->organization->name }}</span>@endif</td></tr>
@empty<tr><td colspan="8">No resources yet.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
