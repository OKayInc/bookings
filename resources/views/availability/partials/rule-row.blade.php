<div class="availability-rule card compact" style="margin-bottom:10px">
    <div class="row">
        <div class="field">
            <label>Day</label>
            <select name="rules[{{ $index }}][weekday]">
                @foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day => $label)
                    <option value="{{ $day }}" @selected((int)($rule['weekday'] ?? 1) === $day)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field"><label>Start</label><input type="time" name="rules[{{ $index }}][start_time]" value="{{ $rule['start_time'] ?? '09:00' }}" required></div>
        <div class="field"><label>End</label><input type="time" name="rules[{{ $index }}][end_time]" value="{{ $rule['end_time'] ?? '17:00' }}" required></div>
        <div class="field"><label>&nbsp;</label><button class="btn" type="button" data-remove-rule>Remove</button></div>
    </div>
</div>
