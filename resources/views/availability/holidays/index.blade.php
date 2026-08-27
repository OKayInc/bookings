@extends('layouts.app')

@section('title', 'Holiday closures')

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="mb-1">Holiday closures</h1>
        <p class="text-body-secondary mb-0">Optional organization-wide closed days for {{ $organization->name }}.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('availability.index') }}">Back to availability</a>
</div>

<div class="alert alert-info">
    Active holidays block every appointment type and resource in <strong>{{ $organization->timezone }}</strong>. They override weekly hours, custom schedules, and extra-availability exceptions. No holiday is honored unless you add and enable it here.
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h4">Add a common holiday</h2>
                <p class="text-body-secondary">These are convenient date rules, not an automatic or complete statutory-holiday calendar.</p>
                <form method="post" action="{{ route('availability.holidays.store') }}">
                    @csrf
                    <label class="form-label" for="preset-key">Holiday</label>
                    <select class="form-select mb-3" id="preset-key" name="preset_key" required>
                        <option value="">Choose a holiday</option>
                        @foreach($presets as $key => $preset)
                            <option value="{{ $key }}" @selected(old('preset_key') === $key)>{{ $preset['name'] }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary" type="submit">Add closure</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h4">Add a custom holiday</h2>
                <form method="post" action="{{ route('availability.holidays.store') }}" id="custom-holiday-form">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="holiday-name">Name</label>
                            <input class="form-control" id="holiday-name" name="name" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="rule-type">Date rule</label>
                            <select class="form-select" id="rule-type" name="rule_type" required>
                                @foreach($ruleTypes as $ruleType)
                                    <option value="{{ $ruleType->value }}" @selected(old('rule_type', 'fixed_annual') === $ruleType->value)>{{ $ruleType->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-sm-7 holiday-fields" data-rule-fields="fixed_annual,nth_weekday">
                            <label class="form-label" for="holiday-month">Month</label>
                            <select class="form-select" id="holiday-month" name="month">
                                @foreach([1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $number => $month)
                                    <option value="{{ $number }}" @selected((int) old('month', 1) === $number)>{{ $month }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-5 holiday-fields" data-rule-fields="fixed_annual">
                            <label class="form-label" for="holiday-day">Day</label>
                            <input class="form-control" id="holiday-day" type="number" name="day" min="1" max="31" value="{{ old('day', 1) }}">
                        </div>

                        <div class="col-sm-7 holiday-fields" data-rule-fields="nth_weekday">
                            <label class="form-label" for="holiday-weekday">Weekday</label>
                            <select class="form-select" id="holiday-weekday" name="weekday">
                                @foreach(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $number => $weekday)
                                    <option value="{{ $number }}" @selected((int) old('weekday', 1) === $number)>{{ $weekday }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-5 holiday-fields" data-rule-fields="nth_weekday">
                            <label class="form-label" for="holiday-occurrence">Occurrence</label>
                            <select class="form-select" id="holiday-occurrence" name="occurrence">
                                @foreach([1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Fourth', 5 => 'Fifth'] as $number => $ordinal)
                                    <option value="{{ $number }}" @selected((int) old('occurrence', 1) === $number)>{{ $ordinal }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 holiday-fields" data-rule-fields="easter_relative">
                            <label class="form-label" for="easter-offset">Days relative to Easter Sunday</label>
                            <input class="form-control" id="easter-offset" type="number" name="easter_offset_days" min="-30" max="30" value="{{ old('easter_offset_days', 0) }}">
                            <div class="form-text">Use 0 for Easter Sunday, -2 for Good Friday, or 1 for Easter Monday.</div>
                        </div>

                        <div class="col-12 holiday-fields" data-rule-fields="one_time">
                            <label class="form-label" for="specific-date">Date</label>
                            <input class="form-control" id="specific-date" type="date" name="specific_date" value="{{ old('specific_date') }}">
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit">Create custom closure</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h2 class="h4">Configured holidays</h2>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Holiday</th><th>Rule</th><th>Next date</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($holidays as $holiday)
                    <tr>
                        <td>{{ $holiday->name }}</td>
                        <td>{{ $descriptions[$holiday->uuid] }}</td>
                        <td>{{ $nextOccurrences[$holiday->uuid]?->format('D, M j, Y') ?? 'Past/no occurrence' }}</td>
                        <td><span class="badge {{ $holiday->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $holiday->is_active ? 'Enabled' : 'Disabled' }}</span></td>
                        <td>
                            <div class="d-flex justify-content-end gap-2">
                                <form method="post" action="{{ route('availability.holidays.toggle', $holiday) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">{{ $holiday->is_active ? 'Disable' : 'Enable' }}</button>
                                </form>
                                <form method="post" action="{{ route('availability.holidays.destroy', $holiday) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-body-secondary">No holiday closures are configured. Normal availability remains unchanged.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const ruleType = document.getElementById('rule-type');
    const groups = [...document.querySelectorAll('.holiday-fields')];
    const refresh = () => groups.forEach(group => {
        const visible = group.dataset.ruleFields.split(',').includes(ruleType.value);
        group.classList.toggle('d-none', !visible);
        group.querySelectorAll('input, select').forEach(input => input.disabled = !visible);
    });
    ruleType.addEventListener('change', refresh);
    refresh();
})();
</script>
@endpush
