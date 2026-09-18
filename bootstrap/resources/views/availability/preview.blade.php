@extends('layouts.app')

@section('title', 'Availability preview')

@section('content')
<div class="page-header">
    <div>
        <h1>Availability preview</h1>
        <p class="muted">Preview bookable times and see exactly which schedules, resources, or conflicts block the day.</p>
    </div>
    <a class="btn" href="{{ route('availability.index') }}">Back</a>
</div>

<form method="get" action="{{ route('availability.preview') }}" class="section-card">
    <div class="row">
        <div class="field">
            <label for="appointment_type">Appointment type</label>
            <select id="appointment_type" name="appointment_type" required>
                <option value="">Select…</option>
                @foreach($appointmentTypes as $type)
                    <option value="{{ $type->uuid }}" @selected($selected?->uuid === $type->uuid)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="date">Date</label>
            <input id="date" type="date" name="date" value="{{ $date }}" required>
        </div>
        <div class="field">
            <label for="timezone">Display/booking timezone</label>
            <select id="timezone" name="timezone" required>
                @foreach($timezones as $timezoneOption)
                    <option value="{{ $timezoneOption }}" @selected($timezone === $timezoneOption)>{{ $timezoneOption }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="duration_value">Duration value</label>
            <input id="duration_value" type="number" min="1" name="duration_value" value="{{ $durationValue }}">
            <div class="muted">Only used for variable-duration types.</div>
        </div>
    </div>
    <label class="inline-check availability-analysis-option" for="include_optional">
        <input id="include_optional" type="checkbox" name="include_optional" value="1" @checked($includeOptional)>
        <span>
            <strong>Show optional resources in the analysis</strong>
            <span class="muted">Optional resources are shown in purple and never block the base appointment.</span>
        </span>
    </label>
    <button class="btn btn-primary" type="submit">Analyze availability</button>
</form>

@if(isset($previewError) && $previewError)
    <div class="alert alert-error">{{ $previewError }}</div>
@endif

@if($selected && $analysis)
<section class="section-card availability-analysis" aria-labelledby="availability-analysis-heading">
    <div class="availability-analysis-heading">
        <div>
            <h2 id="availability-analysis-heading">Availability analysis</h2>
            <p class="muted mb-0">{{ $selected->name }} · {{ $date }} · {{ $timezone }}</p>
        </div>
        <span class="badge rounded-pill {{ $analysis['available_count'] > 0 ? 'text-bg-success' : 'text-bg-danger' }}">
            {{ number_format($analysis['available_count']) }} bookable {{ \Illuminate\Support\Str::plural('start', $analysis['available_count']) }}
        </span>
    </div>

    <div class="availability-analysis-summary" aria-label="Analysis settings">
        <div><span>Duration</span><strong>{{ $analysis['duration_label'] }}</strong></div>
        <div><span>Start interval</span><strong>{{ number_format($analysis['interval_minutes']) }} min</strong></div>
        <div><span>Buffers</span><strong>{{ $analysis['buffer_label'] }}</strong></div>
        <div><span>Starts checked</span><strong>{{ number_format($analysis['candidate_count']) }}</strong></div>
    </div>

    @if($analysis['available_count'] === 0)
        <div class="alert alert-warning availability-analysis-result-message" role="status">
            <strong>No free starts were found.</strong> Red required rows identify what blocks the day. Focus or hover a timeline segment, or expand its blocked-period list, for the exact reason.
        </div>
    @else
        <div class="alert alert-success availability-analysis-result-message" role="status">
            Green areas in the result row are bookable start ranges. Other rows explain why the remaining starts are unavailable.
        </div>
    @endif

    <div class="availability-analysis-legend" aria-label="Timeline legend">
        <span><i class="availability-analysis-swatch availability-analysis-swatch--result"></i>Bookable start</span>
        <span><i class="availability-analysis-swatch availability-analysis-swatch--required"></i>Required available</span>
        <span><i class="availability-analysis-swatch availability-analysis-swatch--blocked"></i>Required blocker</span>
        <span><i class="availability-analysis-swatch availability-analysis-swatch--optional"></i>Optional resource</span>
        <span><i class="availability-analysis-swatch availability-analysis-swatch--appointment"></i>Scheduled activity</span>
        <span><i class="availability-analysis-swatch availability-analysis-swatch--hold"></i>Active hold</span>
    </div>

    <p class="availability-analysis-help muted">
        Each coloured area represents possible <strong>start times</strong>, tested using the selected duration and buffers. Organization activity is context only; a booking blocks the result only when the availability engine connects it to this type or a required resource.
    </p>

    <div class="availability-analysis-scroll" tabindex="0" aria-label="Scrollable day availability timeline">
        <div class="availability-analysis-chart">
            <div class="availability-analysis-axis-row" aria-hidden="true">
                <div class="availability-analysis-axis-label">Rule or resource</div>
                <div class="availability-analysis-axis">
                    @foreach($analysis['ticks'] as $tick)
                        <span class="availability-analysis-tick @if($loop->first) is-first @elseif($loop->last) is-last @endif" style="left: {{ $tick['left_percent'] }}%">
                            {{ $tick['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>

            @foreach($analysis['rows'] as $row)
                <div class="availability-analysis-row availability-analysis-row--{{ $row['kind'] }}" data-analysis-row="{{ $row['key'] }}">
                    <div class="availability-analysis-row-label">
                        <div class="availability-analysis-row-title">
                            <strong>{{ $row['label'] }}</strong>
                            <span class="availability-analysis-kind">{{ $row['badge'] }}</span>
                        </div>
                        <p>{{ $row['description'] }}</p>
                        @if($row['blockers'] !== [])
                            <details class="availability-analysis-blockers">
                                <summary>{{ number_format(count($row['blockers'])) }} blocked {{ \Illuminate\Support\Str::plural('period', count($row['blockers'])) }}</summary>
                                <ul>
                                    @foreach($row['blockers'] as $blocker)
                                        <li><strong>{{ $blocker['time_label'] }}</strong><span>{{ $blocker['reason'] }}</span></li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                    <div class="availability-analysis-track" style="--analysis-lanes: {{ $row['lane_count'] }}">
                        @foreach($analysis['ticks'] as $tick)
                            <i class="availability-analysis-gridline" style="left: {{ $tick['left_percent'] }}%" aria-hidden="true"></i>
                        @endforeach
                        @if($row['empty_message'])
                            <span class="availability-analysis-empty">{{ $row['empty_message'] }}</span>
                        @endif
                        @foreach($row['segments'] as $segment)
                            @php($segmentTitle = $segment['time_label'].' — '.$segment['reason'])
                            <span
                                class="availability-analysis-segment availability-analysis-segment--{{ $segment['state'] }}"
                                style="left: {{ $segment['left_percent'] }}%; width: {{ $segment['width_percent'] }}%; --analysis-lane: {{ $segment['lane'] }}"
                                tabindex="0"
                                title="{{ $segmentTitle }}"
                                aria-label="{{ $segmentTitle }}"
                            ><span>{{ $segment['short_label'] ?? ucfirst($segment['state']) }}</span></span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="section-card" aria-labelledby="available-starts-heading">
    <div class="page-heading">
        <div>
            <h2 id="available-starts-heading">Available start times</h2>
            <p class="muted mb-0">Times are shown in {{ $timezone }}; UTC is included for diagnostics.</p>
        </div>
        <span class="badge rounded-pill text-bg-secondary">{{ number_format(count($slots)) }}</span>
    </div>
    <div class="table-scroll">
        <table class="table table-hover align-middle">
            <thead><tr><th>Start</th><th>End</th><th>UTC</th></tr></thead>
            <tbody>
            @forelse($slots as $slot)
                <tr>
                    <td>{{ $slot->startsAtUtc->setTimezone($timezone)->format('H:i') }}</td>
                    <td>{{ $slot->endsAtUtc->setTimezone($timezone)->format('H:i') }}</td>
                    <td class="muted">{{ $slot->startsAtUtc->format('Y-m-d H:i') }}Z → {{ $slot->endsAtUtc->format('Y-m-d H:i') }}Z</td>
                </tr>
            @empty
                <tr><td colspan="3">No available slots for this date/configuration. Review the analysis above for the cause.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endif
@endsection
