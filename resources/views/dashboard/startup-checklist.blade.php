<section class="card startup-checklist" id="startup-checklist" aria-labelledby="startup-checklist-title">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
        <div>
            <h2 class="h4 mb-1" id="startup-checklist-title">Startup checklist</h2>
            <p class="text-secondary mb-0">Get {{ $organization->name }} ready to take appointments.</p>
        </div>
        <span class="badge {{ $startupChecklist['ready'] ? 'text-bg-success' : 'text-bg-warning' }} text-wrap">
            {{ $startupChecklist['completed'] }} of {{ $startupChecklist['total'] }} essential checks complete
        </span>
    </div>
    <p class="small text-secondary">Follow the suggested order below. Checks update automatically when you save your settings.</p>
    @if($startupChecklist['next'])
        <div class="alert alert-warning py-2">
            <strong>Next step:</strong>
            @if($startupChecklist['next']['url'])
                <a class="alert-link" href="{{ $startupChecklist['next']['url'] }}">{{ $startupChecklist['next']['title'] }}</a>
            @else
                {{ $startupChecklist['next']['title'] }} — ask an owner or administrator to finish this step.
            @endif
        </div>
    @else
        <p class="text-success fw-semibold">Your essential setup checks have passed. Preview a booking before you share your links.</p>
    @endif
    <details @if(! $startupChecklist['ready']) open @endif>
        <summary class="fw-semibold mb-2">Review setup steps</summary>
        <ol class="list-unstyled mb-0">
            @foreach($startupChecklist['steps'] as $step)
                @include('dashboard.startup-checklist-step', ['number' => $loop->iteration])
            @endforeach
        </ol>
    </details>
    <details class="mt-3">
        <summary class="fw-semibold">Optional finishing touches</summary>
        <p class="small text-secondary mt-2">These features can help your business, but are not required to start taking appointments.</p>
        <ul class="list-unstyled mb-0">
            @foreach($startupChecklist['extras'] as $step)
                @include('dashboard.startup-checklist-step', ['number' => null])
            @endforeach
        </ul>
    </details>
    <div class="border-top mt-3 pt-3">
        <h3 class="h6">Finally, preview the customer experience</h3>
        <p class="small text-secondary">Check available times, prices, questions and confirmation instructions. These setup checks do not test calendar conflicts, provider connections or email delivery. Private and unlisted booking links are in the appointment editor.</p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary btn-sm" href="{{ route('availability.preview') }}">Preview available times</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('public.appointment-types.index', $organization->slug) }}" target="_blank" rel="noopener">Open booking page <span class="visually-hidden">(opens in a new tab)</span></a>
        </div>
    </div>
</section>
