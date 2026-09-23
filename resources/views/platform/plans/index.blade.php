@extends('layouts.app')
@section('title', 'Platform plan administration')
@section('content')
<div class="mb-4"><h1 class="mb-1">Platform plan administration</h1><p class="text-body-secondary mb-0">Owner-only grants, one-time-visible promotion codes, and billing audit history.</p></div>

@if($newPromotionCode)
    <div class="alert alert-warning"><strong>Copy this code now:</strong> <code class="user-select-all">{{ $newPromotionCode }}</code><br><span class="small">It cannot be recovered because only a cryptographic hash is stored.</span></div>
@endif

<div class="card mb-4"><div class="card-body">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div><h2 class="h4">Organizations</h2><p class="text-body-secondary">Environment allowlisted organization UUIDs cannot be revoked here; the deployment setting has highest precedence.</p></div>
        <form class="d-flex gap-2" method="get"><input class="form-control" name="q" value="{{ $search }}" placeholder="Name or slug"><button class="btn btn-outline-primary">Search</button></form>
    </div>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Organization</th><th>Effective plan</th><th>Subscription</th><th>Database grant</th><th></th></tr></thead>
        <tbody>
        @foreach($organizations as $organization)
            @php($activeGrant = $organization->planGrants->first(fn ($grant) => $grant->isActive()))
            <tr>
                <td><strong>{{ $organization->name }}</strong><br><span class="small text-body-secondary">{{ $organization->slug }} · {{ $organization->uuid }}</span></td>
                <td>{{ $organization->effectivePlan->level->label() }}<br><span class="small text-body-secondary">{{ $organization->effectivePlan->source }}</span></td>
                <td>{{ $organization->planSubscription?->status ?? '—' }}@if($organization->planSubscription?->billing_interval)<br><span class="small text-body-secondary">{{ $organization->planSubscription->billing_interval }}</span>@endif</td>
                <td>{{ $activeGrant?->reason ?? '—' }}</td>
                <td class="text-end">
                    @if($organization->effectivePlan->source === 'environment')
                        <span class="badge text-bg-secondary">Managed by .env</span>
                    @elseif($activeGrant)
                        <form method="post" action="{{ route('platform.plans.grants.destroy', $activeGrant) }}" onsubmit="return confirm('Revoke this complimentary grant?');">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Revoke grant</button></form>
                    @else
                        <form method="post" action="{{ route('platform.plans.grants.store', $organization) }}" class="d-flex gap-2 justify-content-end">@csrf<input class="form-control form-control-sm" name="reason" maxlength="255" placeholder="Reason (optional)"><button class="btn btn-outline-success btn-sm text-nowrap">Grant unlimited</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    {{ $organizations->links() }}
</div></div>

<div class="row g-4 mb-4">
    <div class="col-lg-5"><div class="card h-100"><div class="card-body">
        <h2 class="h4">Create forever-free code</h2>
        <form method="post" action="{{ route('platform.plans.promotions.store') }}">@csrf
            <div class="mb-3"><label class="form-label" for="max-redemptions">Maximum redemptions <span class="text-body-secondary">(blank = unlimited)</span></label><input class="form-control" id="max-redemptions" type="number" min="1" max="100000" name="max_redemptions"></div>
            <div class="mb-3"><label class="form-label" for="expires-at">Expires at UTC <span class="text-body-secondary">(blank = never)</span></label><input class="form-control" id="expires-at" type="datetime-local" name="expires_at_utc"></div>
            <button class="btn btn-primary">Create code</button>
        </form>
    </div></div></div>
    <div class="col-lg-7"><div class="card h-100"><div class="card-body">
        <h2 class="h4">Recent codes</h2>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Hint</th><th>Uses</th><th>Expiry</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($promotionCodes as $code)
            <tr><td><code>{{ $code->code_hint }}</code></td><td>{{ $code->redemption_count }} / {{ $code->max_redemptions ?? '∞' }}</td><td>{{ $code->expires_at_utc?->format('M j, Y') ?? 'Never' }}</td><td>{{ $code->is_active ? 'Active' : 'Disabled' }}</td><td class="text-end">@if($code->is_active)<form method="post" action="{{ route('platform.plans.promotions.destroy', $code) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Disable</button></form>@endif</td></tr>
        @empty<tr><td colspan="5" class="text-body-secondary">No promotion codes.</td></tr>@endforelse
        </tbody></table></div>
    </div></div></div>
</div>

<div class="card"><div class="card-body"><h2 class="h4">Recent audit events</h2><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>UTC time</th><th>Event</th><th>Organization</th><th>Actor</th><th>Details</th></tr></thead><tbody>
@forelse($auditEvents as $event)<tr><td>{{ $event->created_at->utc()->format('Y-m-d H:i:s') }}</td><td>{{ $event->event }}</td><td>{{ $event->organization?->name ?? '—' }}</td><td>{{ $event->actor?->email ?? 'system' }}</td><td><code>{{ \Illuminate\Support\Str::limit(json_encode($event->details, JSON_UNESCAPED_SLASHES), 120) }}</code></td></tr>@empty<tr><td colspan="5" class="text-body-secondary">No audit events.</td></tr>@endforelse
</tbody></table></div></div></div>
@endsection
