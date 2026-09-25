<li class="startup-checklist-step d-flex flex-column flex-sm-row gap-2 py-3 border-bottom" data-startup-step="{{ $step['id'] }}">
    <div class="d-flex align-items-start gap-2 flex-grow-1">
        <span class="startup-checklist-icon fs-5 fw-bold {{ $step['notNeeded'] ? 'text-secondary' : ($step['complete'] ? 'text-success' : ($step['required'] ? 'text-danger' : 'text-secondary')) }}" aria-hidden="true">{{ $step['notNeeded'] ? '—' : ($step['complete'] ? '✓' : '✗') }}</span>
        <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h3 class="h6 mb-0">@if($number){{ $number }}. @endif{{ $step['title'] }}</h3>
                <span class="small {{ $step['complete'] && ! $step['notNeeded'] ? 'text-success' : 'text-secondary' }}">
                    {{ $step['notNeeded'] ? 'Not needed now' : ($step['complete'] ? 'Complete' : ($step['required'] ? 'Needs attention' : 'Optional')) }}
                </span>
            </div>
            <p class="small text-secondary mb-0 mt-1">{{ $step['description'] }}</p>
        </div>
    </div>
    <div class="startup-checklist-action align-self-sm-center flex-shrink-0">
        @if($step['url'])
            <a class="btn btn-outline-secondary btn-sm" href="{{ $step['url'] }}">{{ $step['action'] }}</a>
        @else
            <span class="small text-secondary">Owner or administrator</span>
        @endif
    </div>
</li>
