<div class="card compact" data-index="{{ $rangeIndex }}">
    <div class="row three">
        <div class="field">
            <label for="attendee_range_{{ $rangeIndex }}_min">From attendee</label>
            <input id="attendee_range_{{ $rangeIndex }}_min" data-range-min type="number" min="1" step="1" max="{{ config('appointment-types.max_capacity', 100000) }}" name="attendee_price_ranges[{{ $rangeIndex }}][min_attendees]" value="{{ $range['min_attendees'] ?? '' }}">
        </div>
        <div class="field">
            <label for="attendee_range_{{ $rangeIndex }}_max">Through attendee</label>
            <input id="attendee_range_{{ $rangeIndex }}_max" data-range-max type="number" min="1" step="1" max="{{ config('appointment-types.max_capacity', 100000) }}" name="attendee_price_ranges[{{ $rangeIndex }}][max_attendees]" value="{{ $range['max_attendees'] ?? '' }}">
        </div>
        <div class="field">
            <label for="attendee_range_{{ $rangeIndex }}_price">Price per attendee ({{ $organization->currency }})</label>
            <input id="attendee_range_{{ $rangeIndex }}_price" inputmode="decimal" name="attendee_price_ranges[{{ $rangeIndex }}][unit_price]" value="{{ $range['unit_price'] ?? '' }}" placeholder="2.00">
        </div>
    </div>
    <button type="button" class="btn" data-remove-attendee-range>Remove range</button>
</div>
