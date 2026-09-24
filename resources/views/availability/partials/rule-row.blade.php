<div class="availability-rule card compact" style="margin-bottom:10px">
    <div class="availability-rule-grid">
        <div class="field availability-rule-day">
            <label>Day</label>
            <select name="rules[{{ $index }}][weekday]">
                @foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day => $label)
                    <option value="{{ $day }}" @selected((int)($rule['weekday'] ?? 1) === $day)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field availability-rule-time"><label>Start</label><input type="time" name="rules[{{ $index }}][start_time]" value="{{ $rule['start_time'] ?? '09:00' }}" required></div>
        <div class="field availability-rule-time"><label>End</label><input type="time" name="rules[{{ $index }}][end_time]" value="{{ $rule['end_time'] ?? '17:00' }}" required></div>
        <div class="field availability-rule-remove">
            <label class="visually-hidden">Remove interval</label>
            <button class="btn btn-outline-danger btn-sm" type="button" data-remove-rule aria-label="Remove interval" title="Remove interval">
                <svg aria-hidden="true" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                    <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6Z"/>
                    <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1 0-2H5V1a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1h2.5a1 1 0 0 1 1 1ZM6 2h4V1H6v1Zm-2 2v9a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4H4Z"/>
                </svg>
            </button>
        </div>
    </div>
</div>
