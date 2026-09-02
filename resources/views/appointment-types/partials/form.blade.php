<div class="section-card">
    <h2>Basics</h2>
    <div class="row">
        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" value="{{ old('name', $appointmentType?->name) }}" required>
        </div>
        <div class="field">
            <label for="slug">Slug (optional)</label>
            <input id="slug" name="slug" value="{{ old('slug', $appointmentType?->slug) }}">
            <div class="muted">Used in public/password-protected URLs. Leave blank to generate from the name.</div>
        </div>
    </div>

    <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description">{{ old('description', $appointmentType?->description) }}</textarea>
    </div>

    <div class="field">
        <label>Appointment-specific logo (optional)</label>
        @if($appointmentType?->logo_url)
            <div class="logo-preview">
                <img src="{{ $appointmentType->logo_url }}" alt="Current appointment type logo">
                <label class="inline-check"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>
            </div>
        @endif
        <input type="file" name="logo_file" accept=".jpg,.jpeg,.png,.webp">
        <div class="muted">JPEG, PNG or WebP. Uploading a new image replaces the current logo.</div>
    </div>
</div>

<div class="section-card">
    <h2>Access</h2>
    <div class="field">
        <label for="visibility">Visibility</label>
        <select name="visibility" id="visibility">
            @foreach($visibilities as $visibility)
                <option value="{{ $visibility->value }}" @selected(old('visibility', $appointmentType?->visibility?->value ?? 'public') === $visibility->value)>
                    {{ ucwords(str_replace('_', ' ', $visibility->value)) }}
                </option>
            @endforeach
        </select>
        <div class="muted">
            Public = listed; Unlisted = secret link; Invite only = token invitation; Password protected = direct URL plus password.
        </div>
    </div>

    <div class="field" id="password-field">
        <label for="access_password">Access password</label>
        <input id="access_password" type="password" name="access_password" autocomplete="new-password">
        <div class="muted">Required for new password-protected types. Leave blank while editing to retain the current password.</div>
    </div>

    @if($appointmentType)
        <div class="field" id="access-link-field">
            <label>Current access link</label>
            @if($appointmentType->visibility->value === 'public' || $appointmentType->visibility->value === 'password_protected')
                <div class="copy-row">
                    <input readonly value="{{ route('public.appointment-types.show', ['organizationSlug' => $organization->slug, 'appointmentSlug' => $appointmentType->slug]) }}">
                    <a class="btn" target="_blank" rel="noopener" href="{{ route('public.appointment-types.show', ['organizationSlug' => $organization->slug, 'appointmentSlug' => $appointmentType->slug]) }}">Open</a>
                </div>
            @elseif($appointmentType->visibility->value === 'unlisted')
                <div class="copy-row">
                    <input readonly value="{{ route('public.appointment-types.unlisted', ['organizationSlug' => $organization->slug, 'token' => $appointmentType->public_token]) }}">
                    <a class="btn" target="_blank" rel="noopener" href="{{ route('public.appointment-types.unlisted', ['organizationSlug' => $organization->slug, 'token' => $appointmentType->public_token]) }}">Open</a>
                </div>
            @else
                <div class="muted">Invite-only links are generated in the invitation section below.</div>
            @endif
        </div>
    @endif
</div>

<div class="section-card">
    <h2>Attendance</h2>
    <div class="row">
        <div class="field">
            <label for="attendance_mode">Attendance mode</label>
            <select name="attendance_mode" id="attendance_mode">
                @foreach($attendanceModes as $mode)
                    <option value="{{ $mode->value }}" @selected(old('attendance_mode', $appointmentType?->attendance_mode?->value ?? 'single') === $mode->value)>
                        {{ ucfirst($mode->value) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field" id="capacity-field">
            <label for="capacity">Session capacity</label>
            <input id="capacity" type="number" min="2" max="{{ config('appointment-types.max_capacity', 100000) }}" name="capacity" value="{{ old('capacity', $appointmentType?->capacity ?? 10) }}">
            <div class="muted">Maximum total attendees sharing the scheduled session. Remaining capacity is enforced across guest bookings.</div>
        </div>
    </div>
</div>

<div class="section-card">
    <h2>Location</h2>
    <input type="hidden" name="is_online" value="0">
    <label class="inline-check">
        <input id="is_online" type="checkbox" name="is_online" value="1" @checked((bool) old('is_online', $appointmentType?->is_online ?? false))>
        This is an online appointment
    </label>
    <div id="meeting-provider-fields" style="margin-top:1rem">
        @php
            $selectedMeetingProvider = old('meeting_provider', $appointmentType?->meeting_provider?->value ?? 'jitsi');
        @endphp
        <div class="field">
            <label for="meeting_provider">Meeting provider</label>
            <select id="meeting_provider" name="meeting_provider">
                @foreach($meetingProviders as $option)
                    @php
                        $provider = $option['provider'];
                    @endphp
                    <option value="{{ $provider->value }}" @selected($selectedMeetingProvider === $provider->value) @disabled(!$option['configured'] && $selectedMeetingProvider !== $provider->value)>
                        {{ $provider->label() }}{{ $option['configured'] ? '' : ' — not configured' }}
                    </option>
                @endforeach
            </select>
            <div class="muted">Jitsi is always available. Owners and administrators configure organization-specific credentials under Organization &gt; Settings.</div>
        </div>
    </div>
</div>

<div class="section-card">
    <h2>Duration</h2>
    <div class="row">
        <div class="field">
            <label for="duration_mode">Duration mode</label>
            <select name="duration_mode" id="duration_mode">
                @foreach($durationModes as $mode)
                    <option value="{{ $mode->value }}" @selected(old('duration_mode', $appointmentType?->duration_mode?->value ?? 'fixed') === $mode->value)>
                        {{ ucfirst($mode->value) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="duration_unit">Duration unit</label>
            <select name="duration_unit" id="duration_unit">
                @foreach($durationUnits as $unit)
                    <option value="{{ $unit->value }}" @selected(old('duration_unit', $appointmentType?->duration_unit?->value ?? 'minute') === $unit->value)>
                        {{ ucfirst($unit->value) }}{{ $unit->value === 'minute' ? 's' : 's' }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div id="fixed-duration-fields">
        <div class="field">
            <label for="duration_value">Fixed duration</label>
            <input id="duration_value" type="number" min="1" name="duration_value" value="{{ old('duration_value', $appointmentType?->duration_value ?? 60) }}">
        </div>
    </div>

    <div id="variable-duration-fields">
        <div class="row three">
            <div class="field">
                <label for="minimum_duration_value">Minimum</label>
                <input id="minimum_duration_value" type="number" min="1" name="minimum_duration_value" value="{{ old('minimum_duration_value', $appointmentType?->minimum_duration_value ?? 1) }}">
            </div>
            <div class="field">
                <label for="maximum_duration_value">Maximum</label>
                <input id="maximum_duration_value" type="number" min="1" name="maximum_duration_value" value="{{ old('maximum_duration_value', $appointmentType?->maximum_duration_value ?? 4) }}">
            </div>
            <div class="field">
                <label for="duration_increment_value">Increment</label>
                <input id="duration_increment_value" type="number" min="1" name="duration_increment_value" value="{{ old('duration_increment_value', $appointmentType?->duration_increment_value ?? 1) }}">
            </div>
        </div>
        <div class="muted">The increment must land exactly on the maximum from the minimum. Example: 1–4 hours every 1 hour, or 30–180 minutes every 15 minutes.</div>
    </div>
</div>

<div class="section-card">
    <h2>Start-time interval</h2>
    <div class="field">
        <label for="start_interval_minutes">Offer appointment starts every</label>
        <div class="row">
            <div class="field">
                <input id="start_interval_minutes" type="number" min="1" max="{{ config('availability.max_start_interval_minutes', 1440) }}" name="start_interval_minutes" value="{{ old('start_interval_minutes', $appointmentType?->start_interval_minutes ?? config('availability.default_start_interval_minutes', 15)) }}">
            </div>
            <div class="field"><span class="muted">minutes</span></div>
        </div>
        <div class="muted">This controls possible start times, independently of appointment duration. Example: a 60-minute appointment with a 15-minute interval can start at 9:00, 9:15, 9:30, etc.</div>
    </div>
</div>

<div class="section-card">
    <h2>Event tickets</h2>
    <input type="hidden" name="ticketing_enabled" value="0">
    <label class="inline-check">
        <input id="ticketing_enabled" type="checkbox" name="ticketing_enabled" value="1" @checked((bool) old('ticketing_enabled', $appointmentType?->ticketing_enabled ?? false))>
        Issue one admission ticket per attendee
    </label>
    <p class="muted">Ticketed events use group attendance and a fixed duration. The selected booking start is <strong>doors open</strong>; resources remain busy for the complete booking range.</p>

    <div id="ticketing-fields" style="margin-top:1rem">
        <div class="row">
            <div class="field">
                <label for="show_start_offset_minutes">Show starts after doors open</label>
                <div class="input-group">
                    <input id="show_start_offset_minutes" class="form-control" type="number" min="0" name="show_start_offset_minutes" value="{{ old('show_start_offset_minutes', $appointmentType?->show_start_offset_minutes ?? 60) }}">
                    <span class="input-group-text">minutes</span>
                </div>
            </div>
            <div class="field">
                <label for="show_end_offset_minutes">Show ends after doors open <span class="muted">optional</span></label>
                <div class="input-group">
                    <input id="show_end_offset_minutes" class="form-control" type="number" min="0" name="show_end_offset_minutes" value="{{ old('show_end_offset_minutes', $appointmentType?->show_end_offset_minutes) }}">
                    <span class="input-group-text">minutes</span>
                </div>
            </div>
        </div>
        <p class="muted">Show start and show end must both fall inside the doors-open-to-booking-end range. Show end may be omitted when it is not advertised.</p>

        <div class="field">
            <label for="ticket_seating_scheme">Seat numbering</label>
            <select id="ticket_seating_scheme" name="ticket_seating_scheme">
                @foreach($ticketSeatingSchemes as $scheme)
                    <option value="{{ $scheme->value }}" @selected(old('ticket_seating_scheme', $appointmentType?->ticket_seating_scheme?->value ?? 'none') === $scheme->value)>{{ $scheme->label() }}</option>
                @endforeach
            </select>
        </div>

        <div id="ticket-seat-optional-field">
            <input type="hidden" name="ticket_seat_optional" value="0">
            <label class="inline-check">
                <input id="ticket_seat_optional" type="checkbox" name="ticket_seat_optional" value="1" @checked((bool) old('ticket_seat_optional', $appointmentType?->ticket_seat_optional ?? false))>
                Allow the seat number to be omitted for a section or row
            </label>
            <p class="muted">When a block has no first/last seat, enter how many general-admission tickets belong to that section or row.</p>
        </div>

        <div id="ticket-seat-block-fields">
            <p class="muted">Blocks are allocated in the order shown. Their numbered ranges or unnumbered quantities must add up exactly to session capacity.</p>
            <div id="ticket-seat-block-list">
                @foreach(old('ticket_seat_blocks', $ticketSeatBlockInputs ?? []) as $blockIndex => $block)
                    @include('appointment-types.partials.ticket-seat-block', compact('blockIndex', 'block'))
                @endforeach
            </div>
            <button class="btn" id="add-ticket-seat-block" type="button">Add seating block</button>
            <template id="ticket-seat-block-template">
                @include('appointment-types.partials.ticket-seat-block', ['blockIndex' => '__INDEX__', 'block' => []])
            </template>
        </div>
        <p class="muted" id="ticket-consecutive-help">Consecutive numbering automatically uses seat 1 through the configured session capacity.</p>
        <p class="muted">Ticketed events may be free or use per-attendee pricing. For a paid event, each seating block can add a fee per allocated ticket.</p>
    </div>
</div>

<div class="section-card">
    <h2>Booking notice</h2>
    <div class="row">
        <div class="field">
            <label for="booking_notice_value">Minimum notice before the appointment</label>
            <input id="booking_notice_value" type="number" min="0" name="booking_notice_value" value="{{ old('booking_notice_value', $appointmentType?->booking_notice_value ?? 0) }}" required>
        </div>
        <div class="field">
            <label for="booking_notice_unit">Notice unit</label>
            <select name="booking_notice_unit" id="booking_notice_unit" required>
                @foreach($bookingNoticeUnits as $unit)
                    <option value="{{ $unit->value }}" @selected(old('booking_notice_unit', $appointmentType?->booking_notice_unit?->value ?? 'hour') === $unit->value)>
                        {{ ucfirst($unit->value) }}{{ $unit->value === 'month' ? 's' : 's' }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="muted">Use 0 for no minimum notice. Calendar months are calculated in the organization's timezone, not as a fixed 30-day duration.</div>

    <div class="row" style="margin-top: 1rem;">
        <div class="field">
            <label for="maximum_booking_notice_value">Maximum time in advance</label>
            <input id="maximum_booking_notice_value" type="number" min="0" name="maximum_booking_notice_value" value="{{ old('maximum_booking_notice_value', $appointmentType?->maximum_booking_notice_value ?? 365) }}" required>
        </div>
        <div class="field">
            <label for="maximum_booking_notice_unit">Maximum notice unit</label>
            <select name="maximum_booking_notice_unit" id="maximum_booking_notice_unit" required>
                @foreach($bookingNoticeUnits as $unit)
                    <option value="{{ $unit->value }}" @selected(old('maximum_booking_notice_unit', $appointmentType?->maximum_booking_notice_unit?->value ?? 'day') === $unit->value)>
                        {{ ucfirst($unit->value) }}s
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="muted">Use 0 for no maximum: clients may then book arbitrarily far into the future, subject to configured availability.</div>
</div>

<div class="section-card">
    <h2>Booking season</h2>
    <input type="hidden" name="seasonal_availability_enabled" value="0">
    <label class="inline-check">
        <input id="seasonal_availability_enabled" type="checkbox" name="seasonal_availability_enabled" value="1" @checked((bool) old('seasonal_availability_enabled', $appointmentType?->seasonal_availability_enabled ?? false))>
        Offer this appointment type only during a date range
    </label>

    <div id="booking-season-fields" style="margin-top:1rem">
        <div class="row three">
            <div class="field">
                <label for="season_start_date">Season starts</label>
                <input id="season_start_date" type="date" name="season_start_date" value="{{ old('season_start_date', $appointmentType?->season_start_date?->format('Y-m-d')) }}">
            </div>
            <div class="field">
                <label for="season_end_date">Season ends</label>
                <input id="season_end_date" type="date" name="season_end_date" value="{{ old('season_end_date', $appointmentType?->season_end_date?->format('Y-m-d')) }}">
            </div>
            <div class="field">
                <label for="season_recurrence">Recurrence</label>
                <select id="season_recurrence" name="season_recurrence">
                    @foreach($seasonRecurrences as $recurrence)
                        <option value="{{ $recurrence->value }}" @selected(old('season_recurrence', $appointmentType?->season_recurrence?->value ?? 'yearly') === $recurrence->value)>{{ $recurrence->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="muted">Dates use {{ $organization->timezone }}. The ending date is inclusive. For a yearly season crossing New Year, choose the ending date in the following year; only the month/day pattern repeats.</div>
    </div>
</div>

<div class="section-card">
    <h2>Short-notice fees</h2>
    <p class="muted">Optionally charge more when an appointment will start soon. Add progressively shorter thresholds for higher fees. If several thresholds match, only the shortest matching threshold is charged.</p>

    @php
        $shortNoticeFeeRows = old('short_notice_fees', $shortNoticeFeeInputs ?? []);
    @endphp
    <div id="short-notice-fee-list">
        @foreach($shortNoticeFeeRows as $index => $fee)
            @php
                $adjustmentType = $fee['adjustment_type'] ?? 'fixed';
            @endphp
            <div class="card compact short-notice-fee-row" data-index="{{ $index }}" style="margin-top: 1rem;">
                <div class="row three">
                    <div class="field">
                        <label for="short_notice_threshold_value_{{ $index }}">Charge when starting within</label>
                        <input id="short_notice_threshold_value_{{ $index }}" type="number" min="1" name="short_notice_fees[{{ $index }}][threshold_value]" value="{{ $fee['threshold_value'] ?? '' }}" required>
                    </div>
                    <div class="field">
                        <label for="short_notice_threshold_unit_{{ $index }}">Threshold unit</label>
                        <select id="short_notice_threshold_unit_{{ $index }}" name="short_notice_fees[{{ $index }}][threshold_unit]" required>
                            @foreach($bookingNoticeUnits as $unit)
                                <option value="{{ $unit->value }}" @selected(($fee['threshold_unit'] ?? 'hour') === $unit->value)>{{ ucfirst($unit->value) }}s</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="short_notice_adjustment_type_{{ $index }}">Fee type</label>
                        <select id="short_notice_adjustment_type_{{ $index }}" name="short_notice_fees[{{ $index }}][adjustment_type]" data-short-notice-type required>
                            @foreach($shortNoticeAdjustmentTypes as $type)
                                <option value="{{ $type->value }}" @selected($adjustmentType === $type->value)>{{ ucfirst($type->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="field" data-short-notice-fixed>
                        <label for="short_notice_fixed_amount_{{ $index }}">Fixed fee ({{ $organization->currency }})</label>
                        <input id="short_notice_fixed_amount_{{ $index }}" inputmode="decimal" name="short_notice_fees[{{ $index }}][fixed_amount]" value="{{ $fee['fixed_amount'] ?? '' }}" placeholder="25.00">
                    </div>
                    <div class="field" data-short-notice-percentage>
                        <label for="short_notice_percentage_{{ $index }}">Percentage of current subtotal</label>
                        <input id="short_notice_percentage_{{ $index }}" inputmode="decimal" name="short_notice_fees[{{ $index }}][percentage]" value="{{ $fee['percentage'] ?? '' }}" placeholder="20">
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary" data-remove-short-notice>Remove fee tier</button>
            </div>
        @endforeach
    </div>

    <button type="button" class="btn btn-outline-secondary" id="add-short-notice-fee" style="margin-top: 1rem;">Add fee tier</button>
    <div class="muted" style="margin-top: .75rem;">Percentage fees use the appointment subtotal after questionnaire extras. Thresholds use the organization's timezone; calendar months are not treated as fixed 30-day periods.</div>

    <template id="short-notice-fee-template">
        <div class="card compact short-notice-fee-row" data-index="__INDEX__" style="margin-top: 1rem;">
            <div class="row three">
                <div class="field">
                    <label for="short_notice_threshold_value___INDEX__">Charge when starting within</label>
                    <input id="short_notice_threshold_value___INDEX__" type="number" min="1" name="short_notice_fees[__INDEX__][threshold_value]" value="" required>
                </div>
                <div class="field">
                    <label for="short_notice_threshold_unit___INDEX__">Threshold unit</label>
                    <select id="short_notice_threshold_unit___INDEX__" name="short_notice_fees[__INDEX__][threshold_unit]" required>
                        @foreach($bookingNoticeUnits as $unit)
                            <option value="{{ $unit->value }}" @selected($unit->value === 'hour')>{{ ucfirst($unit->value) }}s</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="short_notice_adjustment_type___INDEX__">Fee type</label>
                    <select id="short_notice_adjustment_type___INDEX__" name="short_notice_fees[__INDEX__][adjustment_type]" data-short-notice-type required>
                        @foreach($shortNoticeAdjustmentTypes as $type)
                            <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="field" data-short-notice-fixed>
                    <label for="short_notice_fixed_amount___INDEX__">Fixed fee ({{ $organization->currency }})</label>
                    <input id="short_notice_fixed_amount___INDEX__" inputmode="decimal" name="short_notice_fees[__INDEX__][fixed_amount]" value="" placeholder="25.00">
                </div>
                <div class="field" data-short-notice-percentage>
                    <label for="short_notice_percentage___INDEX__">Percentage of current subtotal</label>
                    <input id="short_notice_percentage___INDEX__" inputmode="decimal" name="short_notice_fees[__INDEX__][percentage]" value="" placeholder="20">
                </div>
            </div>
            <button type="button" class="btn btn-outline-secondary" data-remove-short-notice>Remove fee tier</button>
        </div>
    </template>
</div>

<div class="section-card">
    <h2>Rest / buffer time</h2>
    <div class="row">
        <div class="field">
            <label for="buffer_before_minutes">Before appointment (minutes)</label>
            <input id="buffer_before_minutes" type="number" min="0" max="{{ config('appointment-types.max_buffer_minutes', 10080) }}" name="buffer_before_minutes" value="{{ old('buffer_before_minutes', $appointmentType?->buffer_before_minutes ?? 0) }}" required>
        </div>
        <div class="field">
            <label for="buffer_after_minutes">After appointment (minutes)</label>
            <input id="buffer_after_minutes" type="number" min="0" max="{{ config('appointment-types.max_buffer_minutes', 10080) }}" name="buffer_after_minutes" value="{{ old('buffer_after_minutes', $appointmentType?->buffer_after_minutes ?? 0) }}" required>
        </div>
    </div>
    <div class="muted">These periods will block the resource in M3 even though the client only sees the appointment's actual start/end time.</div>
</div>

<div class="section-card">
    <h2>Pricing</h2>
    <div class="field">
        <label for="pricing_mode">Pricing mode</label>
        <select name="pricing_mode" id="pricing_mode" required>
            <option value="" disabled>Choose a pricing mode</option>
            @foreach($pricingModes as $mode)
                <option value="{{ $mode->value }}" @selected(old('pricing_mode', $appointmentType?->pricing_mode?->value ?? 'free') === $mode->value) @disabled($mode->value === 'per_attendee' && old('attendance_mode', $appointmentType?->attendance_mode?->value ?? 'single') !== 'group') @if($mode->value === 'per_attendee' && old('attendance_mode', $appointmentType?->attendance_mode?->value ?? 'single') !== 'group') hidden @endif>
                    {{ $mode->label() }}
                </option>
            @endforeach
        </select>
        <div class="muted">Currency: {{ $organization->currency }}. The final booking total is snapshotted before any payment request is created.</div>
    </div>

    <div class="field" id="fixed-price-fields">
        <label for="fixed_price">Fixed total price ({{ $organization->currency }})</label>
        <input id="fixed_price" inputmode="decimal" name="fixed_price" value="{{ old('fixed_price', $fixedPriceInput) }}" placeholder="150.00">
    </div>

    <div id="attendee-price-fields">
        <div class="field">
            <label for="attendee_pricing_mode">Per-attendee calculation</label>
            <select id="attendee_pricing_mode" name="attendee_pricing_mode">
                @foreach($attendeePricingModes as $mode)
                    <option value="{{ $mode->value }}" @selected(old('attendee_pricing_mode', $appointmentType?->attendee_pricing_mode?->value ?? 'flat') === $mode->value)>{{ $mode->label() }}</option>
                @endforeach
            </select>
        </div>
        <div id="attendee-flat-fields" class="field">
            <label for="attendee_price">Price per attendee ({{ $organization->currency }})</label>
            <input id="attendee_price" inputmode="decimal" name="attendee_price" value="{{ old('attendee_price', $attendeePriceInput) }}" placeholder="25.00">
        </div>
        <div id="attendee-range-fields">
            <p class="muted">For rates 1–10 at $2 and 11–20 at $1.50: <strong>Absolute</strong> charges 12 × $1.50 = $18 for 12 attendees; <strong>Accumulative</strong> charges 10 × $2 + 2 × $1.50 = $23.</p>
            <div id="attendee-price-range-list">
                @foreach(old('attendee_price_ranges', $attendeePriceRangeInputs) as $rangeIndex => $range)
                    @include('appointment-types.partials.attendee-price-range', ['rangeIndex' => $rangeIndex, 'range' => $range])
                @endforeach
            </div>
            <button type="button" class="btn" id="add-attendee-price-range">Add attendee range</button>
            <p class="muted">Bounds are inclusive whole numbers. Cover every attendee count from 1 through the session capacity, without gaps or overlaps. Every rate must be greater than zero.</p>
            <template id="attendee-price-range-template">
                @include('appointment-types.partials.attendee-price-range', ['rangeIndex' => '__INDEX__', 'range' => []])
            </template>
        </div>
        <p class="muted">Each booking is priced using only its own attendee count, including the primary client—not the total seats booked by other clients. Questionnaire extras retain their configured rules. Group sessions allow other clients to book remaining seats.</p>
    </div>

    <div id="rate-price-fields">
        <div class="row">
            <div class="field">
                <label for="rate_amount">Rate ({{ $organization->currency }})</label>
                <input id="rate_amount" inputmode="decimal" name="rate_amount" value="{{ old('rate_amount', $rateAmountInput) }}" placeholder="150.00">
            </div>
            <div class="field">
                <label for="rate_unit">Per</label>
                <select name="rate_unit" id="rate_unit">
                    @foreach($durationUnits as $unit)
                        <option value="{{ $unit->value }}" @selected(old('rate_unit', $appointmentType?->rate_unit?->value ?? 'hour') === $unit->value)>
                            {{ $unit->value }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="muted">The selected client duration will determine the total. Pricing uses integer minor units and deterministic rounding.</div>
    </div>
</div>

<div class="section-card">
    <h2>Payment collection and refunds</h2>
    <p class="muted">Stripe and PayPal credentials are configured per organization under <a href="{{ route('payment-settings.edit') }}">Organization → Payments</a>. These terms are copied into each booking and later edits do not change existing clients.</p>
    <div id="free-payment-help" class="alert alert-info">Free appointment types do not create a payment request.</div>
    <div id="payment-policy-fields">
        <div class="field">
            <label for="payment_collection_mode">Amount due to confirm the booking</label>
            <select id="payment_collection_mode" name="payment_collection_mode" required>
                @foreach($paymentCollectionModes as $mode)
                    <option value="{{ $mode->value }}" @selected(old('payment_collection_mode', $appointmentType?->payment_collection_mode?->value ?? 'full') === $mode->value)>{{ $mode->label() }}</option>
                @endforeach
            </select>
        </div>
        <div id="retainer-fields">
            <div class="field">
                <label for="retainer_type">Retainer calculation</label>
                <select id="retainer_type" name="retainer_type">
                    @foreach($retainerTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('retainer_type', $appointmentType?->retainer_type?->value ?? 'percentage') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div id="retainer-fixed-fields" class="field">
                <label for="retainer_amount">Fixed retainer ({{ $organization->currency }})</label>
                <input id="retainer_amount" inputmode="decimal" name="retainer_amount" value="{{ old('retainer_amount', $retainerAmountInput) }}" placeholder="50.00">
                <div class="muted">If the final total is lower, the client is charged only that final total.</div>
            </div>
            <div id="retainer-percentage-fields" class="field">
                <label for="retainer_percentage">Retainer percentage</label>
                <div class="input-group"><input id="retainer_percentage" class="form-control" inputmode="decimal" name="retainer_percentage" value="{{ old('retainer_percentage', $retainerPercentageInput) }}" placeholder="25"><span class="input-group-text">%</span></div>
            </div>
            <div class="row">
                <div class="field"><label for="balance_due_value">Remaining balance due</label><input id="balance_due_value" type="number" min="0" name="balance_due_value" value="{{ old('balance_due_value', $appointmentType?->balance_due_value ?? 0) }}"></div>
                <div class="field"><label for="balance_due_unit">Before appointment</label><select id="balance_due_unit" name="balance_due_unit">@foreach($bookingNoticeUnits as $unit)<option value="{{ $unit->value }}" @selected(old('balance_due_unit', $appointmentType?->balance_due_unit?->value ?? 'day') === $unit->value)>{{ ucfirst($unit->value) }}s</option>@endforeach</select></div>
            </div>
            <p class="muted">Clients can pay the balance early from their private booking page. The due date is shown in the booking ledger; M9 does not store a card or make an off-session charge.</p>
        </div>
    </div>
    <div class="row">
        <div class="field"><label for="client_refund_percentage">Refund after client cancellation</label><div class="input-group"><input id="client_refund_percentage" class="form-control" inputmode="decimal" name="client_refund_percentage" value="{{ old('client_refund_percentage', $clientRefundPercentageInput) }}" required><span class="input-group-text">% of captured amount</span></div></div>
        <div class="field"><label for="staff_refund_percentage">Refund after staff cancellation</label><div class="input-group"><input id="staff_refund_percentage" class="form-control" inputmode="decimal" name="staff_refund_percentage" value="{{ old('staff_refund_percentage', $staffRefundPercentageInput) }}" required><span class="input-group-text">% of captured amount</span></div></div>
    </div>
    <p class="muted">Staff schedule-change cancellations use the staff percentage. Refunds are allocated across captured transactions and sent back through the original provider.</p>
</div>

<div class="section-card">
    <h2>Client access and email verification</h2>
    <div class="field">
        <label for="email_verification_mode">Client email verification</label>
        <select name="email_verification_mode" id="email_verification_mode">
            @foreach($emailVerificationModes as $mode)
                <option value="{{ $mode->value }}" @selected(old('email_verification_mode', $appointmentType?->email_verification_mode?->value ?? 'before_confirmation') === $mode->value)>
                    {{ $mode->label() }}
                </option>
            @endforeach
        </select>
        <div class="muted">Clients do not create backend accounts or passwords. Their organization-scoped contact is identified by email, with signed expiring verification/management links when required.</div>
    </div>
</div>

<div class="section-card">
    <h2>Resources and confirmation</h2>
    <div class="field">
        <label>Assigned resources</label>
        <div class="checkbox-list">
            @forelse($resources as $resource)
                @php
                    $assignedResource = $appointmentType?->resources?->firstWhere('uuid', $resource->uuid);
                    $assignedUuids = old('resource_uuids', $appointmentType?->resources?->pluck('uuid')->all() ?? []);
                    $selectedMode = old(
                        'resource_requirement_modes.'.$resource->uuid,
                        $assignedResource?->pivot?->requirement_mode ?? 'inherit'
                    );
                    $replacementGroup = old(
                        'resource_replacement_groups.'.$resource->uuid,
                        $assignedResource?->pivot?->replacement_group
                    );
                    $equipmentInput = $resourceEquipmentPricingInputs[$resource->uuid] ?? [
                        'quantity' => 1,
                        'mode' => 'free',
                        'unit_price' => '',
                        'fixed_price' => '',
                        'bundles' => [],
                    ];
                    $equipmentQuantity = old('resource_quantities.'.$resource->uuid, $equipmentInput['quantity']);
                    $equipmentPricingMode = old('resource_equipment_pricing_modes.'.$resource->uuid, $equipmentInput['mode']);
                    $equipmentUnitPrice = old('resource_equipment_unit_prices.'.$resource->uuid, $equipmentInput['unit_price']);
                    $equipmentFixedPrice = old('resource_equipment_fixed_prices.'.$resource->uuid, $equipmentInput['fixed_price']);
                    $equipmentBundles = old('resource_equipment_bundles.'.$resource->uuid, $equipmentInput['bundles']);
                    if (! is_array($equipmentBundles) || $equipmentBundles === []) {
                        $equipmentBundles = [['quantity' => 1, 'amount' => '']];
                    }
                @endphp
                <div class="card compact" style="margin-bottom:8px">
                    <label style="display:flex;align-items:center;gap:8px">
                        <input type="checkbox" name="resource_uuids[]" value="{{ $resource->uuid }}"
                            @checked(in_array($resource->uuid, $assignedUuids, true))>
                        <strong>{{ $resource->name }}</strong>
                        <span class="muted">({{ $resource->type }})</span>
                    </label>
                    <div class="row" style="margin-top:8px">
                        <div class="field" style="margin-bottom:0">
                            <label for="resource_requirement_{{ $resource->uuid }}">Requirement for this appointment type</label>
                            <select id="resource_requirement_{{ $resource->uuid }}" name="resource_requirement_modes[{{ $resource->uuid }}]">
                                @foreach($resourceRequirementModes as $mode)
                                    @if($resource->usesQuantityInventory() && $mode->value === 'replacement')
                                        @continue
                                    @endif
                                    <option value="{{ $mode->value }}" @selected($selectedMode === $mode->value)>{{ $mode->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="margin-bottom:0">
                            <label>Organization default</label>
                            <div><span class="badge">{{ $resource->pivot->is_required_by_default ? 'Required' : 'Optional' }}</span></div>
                        </div>
                        <div class="field" style="margin-bottom:0">
                            <label for="resource_replacement_group_{{ $resource->uuid }}">Replacement group</label>
                            <input id="resource_replacement_group_{{ $resource->uuid }}"
                                name="resource_replacement_groups[{{ $resource->uuid }}]"
                                value="{{ $replacementGroup }}"
                                maxlength="80"
                                placeholder="For example: Photographer">
                            <div class="muted">Used only with “One of a replacement group”. Give every alternative the same group name.</div>
                        </div>
                    </div>
                    @if($resource->type === 'equipment')
                        <div class="equipment-assignment" data-equipment-assignment style="margin-top:12px">
                            @if($resource->usesQuantityInventory())
                            <div class="alert alert-info" style="margin-bottom:8px">
                                Shared stock: <strong>{{ $resource->inventory_quantity }}</strong> pieces. Availability is reduced only by overlapping active holds and scheduled appointments.
                            </div>
                            <div class="row">
                                <div class="field" style="margin-bottom:0">
                                    <label for="resource_quantity_{{ $resource->uuid }}">Pieces required per appointment</label>
                                    <input id="resource_quantity_{{ $resource->uuid }}"
                                        type="number"
                                        name="resource_quantities[{{ $resource->uuid }}]"
                                        min="1"
                                        max="{{ $resource->inventory_quantity }}"
                                        value="{{ $equipmentQuantity }}">
                                </div>
                                <div class="field" style="margin-bottom:0">
                                    <label for="resource_equipment_pricing_{{ $resource->uuid }}">Rental pricing</label>
                                    <select id="resource_equipment_pricing_{{ $resource->uuid }}"
                                        name="resource_equipment_pricing_modes[{{ $resource->uuid }}]"
                                        data-equipment-pricing-mode>
                                        @foreach($equipmentPricingModes as $mode)
                                            <option value="{{ $mode->value }}" @selected($equipmentPricingMode === $mode->value)>{{ $mode->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @else
                            <div class="alert alert-info" style="margin-bottom:8px">Quantity tracking is off. This equipment is reserved as one unique resource and may be used in a replacement group.</div>
                            <input type="hidden" name="resource_quantities[{{ $resource->uuid }}]" value="1" max="1">
                            <div class="field" style="margin-bottom:0">
                                <label for="resource_equipment_pricing_{{ $resource->uuid }}">Rental pricing</label>
                                <select id="resource_equipment_pricing_{{ $resource->uuid }}"
                                    name="resource_equipment_pricing_modes[{{ $resource->uuid }}]"
                                    data-equipment-pricing-mode>
                                    @foreach($equipmentPricingModes as $mode)
                                        <option value="{{ $mode->value }}" @selected($equipmentPricingMode === $mode->value)>{{ $mode->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="field" data-equipment-price-fields="per_unit">
                                <label>Price per piece ({{ $organization->currency }})</label>
                                <input inputmode="decimal"
                                    name="resource_equipment_unit_prices[{{ $resource->uuid }}]"
                                    value="{{ $equipmentUnitPrice }}"
                                    placeholder="3.00">
                            </div>
                            <div class="field" data-equipment-price-fields="fixed">
                                <label>Fixed rental fee ({{ $organization->currency }})</label>
                                <input inputmode="decimal"
                                    name="resource_equipment_fixed_prices[{{ $resource->uuid }}]"
                                    value="{{ $equipmentFixedPrice }}"
                                    placeholder="20.00">
                                <div class="muted">Charged once regardless of the required piece count.</div>
                            </div>
                            <div data-equipment-price-fields="bundles">
                                <label>Bundle schedule ({{ $organization->currency }})</label>
                                <div data-equipment-bundle-list data-resource-uuid="{{ $resource->uuid }}">
                                    @foreach($equipmentBundles as $bundleIndex => $bundle)
                                        <div class="row align-items-end" data-equipment-bundle-row style="margin-bottom:8px">
                                            <div class="field" style="margin-bottom:0">
                                                <label>Pieces</label>
                                                <input type="number" min="1" max="{{ $resource->inventory_quantity }}"
                                                    name="resource_equipment_bundles[{{ $resource->uuid }}][{{ $bundleIndex }}][quantity]"
                                                    value="{{ $bundle['quantity'] ?? '' }}">
                                            </div>
                                            <div class="field" style="margin-bottom:0">
                                                <label>Bundle price</label>
                                                <input inputmode="decimal"
                                                    name="resource_equipment_bundles[{{ $resource->uuid }}][{{ $bundleIndex }}][amount]"
                                                    value="{{ $bundle['amount'] ?? '' }}"
                                                    placeholder="10.00">
                                            </div>
                                            <div class="field" style="margin-bottom:0"><button class="btn" type="button" data-remove-equipment-bundle>Remove</button></div>
                                        </div>
                                    @endforeach
                                </div>
                                <button class="btn" type="button" data-add-equipment-bundle>Add bundle</button>
                                <div class="muted">Include a one-piece tier. The checkout uses the cheapest exact combination; for example, 6 pieces can use one 5-piece bundle plus one piece.</div>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <p class="muted">Create resources first if this appointment requires them.</p>
            @endforelse
        </div>
        <div class="muted">Required resources must all be available. A replacement group requires at least one available member; all available members are reserved until one confirms. Optional resources are reserved when available but do not block the booking.</div>
    </div>
    <div class="field checkbox-list">
        <label>
            <input type="checkbox" name="requires_resource_confirmation" value="1" @checked(old('requires_resource_confirmation', $appointmentType?->requires_resource_confirmation ?? false))>
            Require required employee resources to confirm the appointment
        </label>
        <div class="muted">Required person-resources receive accept/decline requests. In a replacement group, one acceptance satisfies the group and releases the other candidates. Optional person-resources may respond but never block confirmation.</div>
    </div>
</div>

<div class="section-card">
    <h2>Cancellation policy</h2>
    <input type="hidden" name="cancellation_allowed" value="0">
    <label class="inline-check"><input type="checkbox" name="cancellation_allowed" value="1" @checked((bool) old('cancellation_allowed', $appointmentType?->cancellation_allowed ?? true))> Allow clients to cancel</label>
    <div class="row" style="margin-top:1rem">
        <div class="field">
            <label for="cancellation_notice_value">Cancellation deadline</label>
            <input id="cancellation_notice_value" type="number" min="0" name="cancellation_notice_value" value="{{ old('cancellation_notice_value', $appointmentType?->cancellation_notice_value ?? 24) }}">
        </div>
        <div class="field">
            <label for="cancellation_notice_unit">Before appointment</label>
            <select id="cancellation_notice_unit" name="cancellation_notice_unit">
                @foreach($bookingNoticeUnits as $unit)
                    <option value="{{ $unit->value }}" @selected(old('cancellation_notice_unit', $appointmentType?->cancellation_notice_unit?->value ?? 'hour') === $unit->value)>{{ ucfirst($unit->value) }}s</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="field">
        <label for="cancellation_policy_text">Policy shown to clients (optional)</label>
        <textarea id="cancellation_policy_text" name="cancellation_policy_text">{{ old('cancellation_policy_text', $appointmentType?->cancellation_policy_text) }}</textarea>
    </div>
    <div class="muted">A deadline of 0 allows cancellation until the appointment starts. The snapshotted client refund percentage is applied automatically to captured payments.</div>
</div>

<div class="section-card">
    <h2>Rescheduling policy</h2>
    <input type="hidden" name="rescheduling_allowed" value="0">
    <label class="inline-check"><input type="checkbox" name="rescheduling_allowed" value="1" @checked((bool) old('rescheduling_allowed', $appointmentType?->rescheduling_allowed ?? true))> Allow clients to reschedule</label>
    <div class="row" style="margin-top:1rem">
        <div class="field">
            <label for="rescheduling_notice_value">Rescheduling deadline</label>
            <input id="rescheduling_notice_value" type="number" min="0" name="rescheduling_notice_value" value="{{ old('rescheduling_notice_value', $appointmentType?->rescheduling_notice_value ?? 24) }}">
        </div>
        <div class="field">
            <label for="rescheduling_notice_unit">Before appointment</label>
            <select id="rescheduling_notice_unit" name="rescheduling_notice_unit">
                @foreach($bookingNoticeUnits as $unit)
                    <option value="{{ $unit->value }}" @selected(old('rescheduling_notice_unit', $appointmentType?->rescheduling_notice_unit?->value ?? 'hour') === $unit->value)>{{ ucfirst($unit->value) }}s</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="rescheduling_max_count">Maximum reschedules</label>
            <input id="rescheduling_max_count" type="number" min="0" name="rescheduling_max_count" value="{{ old('rescheduling_max_count', $appointmentType?->rescheduling_max_count ?? 0) }}">
            <div class="muted">0 = unlimited.</div>
        </div>
    </div>
    <div class="field">
        <label for="rescheduling_policy_text">Policy shown to clients (optional)</label>
        <textarea id="rescheduling_policy_text" name="rescheduling_policy_text">{{ old('rescheduling_policy_text', $appointmentType?->rescheduling_policy_text) }}</textarea>
    </div>
</div>

<div class="section-card">
    <h2>Reminders</h2>
    <input type="hidden" name="reminder_enabled" value="0">
    <label class="inline-check"><input type="checkbox" name="reminder_enabled" value="1" @checked((bool) old('reminder_enabled', $appointmentType?->reminder_enabled ?? false))> Enable appointment reminders</label>
    <div class="row" style="margin-top:1rem">
        <div class="field">
            <label for="reminder_threshold_basis">Send reminders only when</label>
            <select id="reminder_threshold_basis" name="reminder_threshold_basis">
                @foreach($reminderThresholdBases as $basis)
                    <option value="{{ $basis->value }}" @selected(old('reminder_threshold_basis', $appointmentType?->reminder_threshold_basis?->value ?? 'lead_time') === $basis->value)>{{ $basis->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="reminder_threshold_days">Threshold (days)</label>
            <input id="reminder_threshold_days" type="number" min="0" name="reminder_threshold_days" value="{{ old('reminder_threshold_days', $appointmentType?->reminder_threshold_days ?? 7) }}">
        </div>
    </div>
    <div class="row">
        <div class="field">
            <label for="reminder_before_value">Send reminder</label>
            <input id="reminder_before_value" type="number" min="1" name="reminder_before_value" value="{{ old('reminder_before_value', $appointmentType?->reminder_before_value ?? 1) }}">
        </div>
        <div class="field">
            <label for="reminder_before_unit">Before start</label>
            <select id="reminder_before_unit" name="reminder_before_unit">
                @foreach($bookingNoticeUnits as $unit)
                    <option value="{{ $unit->value }}" @selected(old('reminder_before_unit', $appointmentType?->reminder_before_unit?->value ?? 'day') === $unit->value)>{{ ucfirst($unit->value) }}s</option>
                @endforeach
            </select>
        </div>
    </div>
    <input type="hidden" name="reminder_clients" value="0"><label class="inline-check"><input type="checkbox" name="reminder_clients" value="1" @checked((bool) old('reminder_clients', $appointmentType?->reminder_clients ?? true))> Remind client(s)</label>
    <input type="hidden" name="reminder_resources" value="0"><label class="inline-check"><input type="checkbox" name="reminder_resources" value="1" @checked((bool) old('reminder_resources', $appointmentType?->reminder_resources ?? true))> Remind assigned person-resources</label>
</div>

<div class="section-card">
    <h2>Contract</h2>
    <div class="field">
        <label>Contract template (optional)</label>
        @if($appointmentType?->contractTemplate)
            <div class="card compact">
                <strong>{{ $appointmentType->contractTemplate->original_name }}</strong>
                <div class="muted">{{ number_format($appointmentType->contractTemplate->size_bytes / 1024, 1) }} KiB · {{ $appointmentType->contractTemplate->mime_type ?: 'unknown type' }}</div>
                <div class="actions" style="margin-top:8px">
                    <a class="btn" href="{{ route('appointment-types.contract-template.download', $appointmentType) }}">Download current contract</a>
                    <label class="inline-check"><input type="checkbox" name="remove_contract" value="1"> Remove current contract</label>
                </div>
            </div>
        @endif
        <input type="file" name="contract_file" accept=".pdf,.doc,.docx,.odt,.jpg,.jpeg,.png,.webp">
        <div class="muted">Stored privately and versioned. Clients can download the exact version and upload signed PDFs/page photos for manual review.</div>
    </div>
</div>

<div class="section-card">
    <h2>After booking</h2>
    <div class="field">
        <label for="redirect_url">Redirect URL (optional)</label>
        <input id="redirect_url" type="url" name="redirect_url" value="{{ old('redirect_url', $appointmentType?->redirect_url) }}" placeholder="https://example.com/thank-you">
        <div class="muted">Only HTTP/HTTPS URLs are accepted. The client is redirected here after a successful confirmed booking when configured.</div>
    </div>
</div>

<div class="section-card">
    <h2>Status</h2>
    <div class="field checkbox-list">
        <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $appointmentType?->is_active ?? true))> Active</label>
    </div>
</div>

<script>
(() => {
    const visibility = document.getElementById('visibility');
    const passwordField = document.getElementById('password-field');
    const attendanceMode = document.getElementById('attendance_mode');
    const online = document.getElementById('is_online');
    const meetingProviderFields = document.getElementById('meeting-provider-fields');
    const seasonalAvailability = document.getElementById('seasonal_availability_enabled');
    const bookingSeasonFields = document.getElementById('booking-season-fields');
    const capacityField = document.getElementById('capacity-field');
    const durationMode = document.getElementById('duration_mode');
    const fixedDuration = document.getElementById('fixed-duration-fields');
    const variableDuration = document.getElementById('variable-duration-fields');
    const pricingMode = document.getElementById('pricing_mode');
    const fixedPrice = document.getElementById('fixed-price-fields');
    const ratePrice = document.getElementById('rate-price-fields');
    const attendeePrice = document.getElementById('attendee-price-fields');
    const attendeePricingMode = document.getElementById('attendee_pricing_mode');
    const attendeeFlatFields = document.getElementById('attendee-flat-fields');
    const attendeeRangeFields = document.getElementById('attendee-range-fields');
    const attendeeRangeList = document.getElementById('attendee-price-range-list');
    const attendeeRangeTemplate = document.getElementById('attendee-price-range-template');
    const addAttendeeRange = document.getElementById('add-attendee-price-range');
    const perAttendeeOption = pricingMode.querySelector('option[value="per_attendee"]');
    let nextAttendeeRange = Array.from(attendeeRangeList.children).reduce((max, row) => Math.max(max, Number(row.dataset.index) || 0), -1) + 1;

    function addRange() {
        const previous = attendeeRangeList.lastElementChild;
        const nextMin = previous ? Number(previous.querySelector('[data-range-max]').value) + 1 : 1;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = attendeeRangeTemplate.innerHTML.replaceAll('__INDEX__', String(nextAttendeeRange++));
        const row = wrapper.firstElementChild;
        row.querySelector('[data-range-min]').value = nextMin;
        row.querySelector('[data-range-max]').value = Math.max(nextMin, Number(document.getElementById('capacity').value) || 1);
        attendeeRangeList.appendChild(row);
    }

    function setSectionState(section, enabled) {
        section.style.display = enabled ? 'block' : 'none';

        section.querySelectorAll('input, select, textarea, button').forEach((control) => {
            control.disabled = !enabled;
        });
    }

    function setRequired(control, required) {
        if (!control) {
            return;
        }

        control.required = required;
    }

    function sync() {
        const passwordProtected = visibility.value === 'password_protected';
        const groupAttendance = attendanceMode.value === 'group';
        const onlineAppointment = online.checked;
        const seasonalAppointment = seasonalAvailability.checked;
        const fixedDurationMode = durationMode.value === 'fixed';
        perAttendeeOption.hidden = !groupAttendance;
        perAttendeeOption.disabled = !groupAttendance;
        if (!groupAttendance && pricingMode.value === 'per_attendee') pricingMode.value = '';
        const fixedPricingMode = pricingMode.value === 'fixed';
        const ratePricingMode = pricingMode.value === 'rate';
        const attendeePricing = groupAttendance && pricingMode.value === 'per_attendee';
        const flatAttendeePricing = attendeePricing && attendeePricingMode.value === 'flat';
        const rangedAttendeePricing = attendeePricing && !flatAttendeePricing;
        if (rangedAttendeePricing && attendeeRangeList.children.length === 0) addRange();

        setSectionState(passwordField, passwordProtected);
        setSectionState(capacityField, groupAttendance);
        setSectionState(meetingProviderFields, onlineAppointment);
        setSectionState(bookingSeasonFields, seasonalAppointment);
        setSectionState(fixedDuration, fixedDurationMode);
        setSectionState(variableDuration, !fixedDurationMode);
        setSectionState(fixedPrice, fixedPricingMode);
        setSectionState(ratePrice, ratePricingMode);
        setSectionState(attendeePrice, attendeePricing);
        setSectionState(attendeeFlatFields, flatAttendeePricing);
        setSectionState(attendeeRangeFields, rangedAttendeePricing);
        attendeeRangeList.querySelectorAll('input').forEach((input) => { input.required = rangedAttendeePricing; });
        addAttendeeRange.disabled = !rangedAttendeePricing || attendeeRangeList.children.length >= 50;

        setRequired(document.getElementById('capacity'), groupAttendance);
        setRequired(document.getElementById('meeting_provider'), onlineAppointment);
        setRequired(document.getElementById('season_start_date'), seasonalAppointment);
        setRequired(document.getElementById('season_end_date'), seasonalAppointment);
        setRequired(document.getElementById('season_recurrence'), seasonalAppointment);
        setRequired(document.getElementById('duration_value'), fixedDurationMode);
        setRequired(document.getElementById('minimum_duration_value'), !fixedDurationMode);
        setRequired(document.getElementById('maximum_duration_value'), !fixedDurationMode);
        setRequired(document.getElementById('duration_increment_value'), !fixedDurationMode);
        setRequired(document.getElementById('fixed_price'), fixedPricingMode);
        setRequired(document.getElementById('rate_amount'), ratePricingMode);
        setRequired(document.getElementById('rate_unit'), ratePricingMode);
        setRequired(attendeePricingMode, attendeePricing);
        setRequired(document.getElementById('attendee_price'), flatAttendeePricing);
    }

    [visibility, attendanceMode, online, seasonalAvailability, durationMode, pricingMode, attendeePricingMode].forEach((element) => element.addEventListener('change', sync));
    addAttendeeRange.addEventListener('click', () => { addRange(); sync(); });
    attendeeRangeList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-attendee-range]');
        if (button) { button.closest('[data-index]').remove(); sync(); }
    });
    sync();
})();
</script>

<script>
(() => {
    const pricing = document.getElementById('pricing_mode');
    const policy = document.getElementById('payment-policy-fields');
    const freeHelp = document.getElementById('free-payment-help');
    const collection = document.getElementById('payment_collection_mode');
    const retainer = document.getElementById('retainer-fields');
    const retainerType = document.getElementById('retainer_type');
    const fixed = document.getElementById('retainer-fixed-fields');
    const percentage = document.getElementById('retainer-percentage-fields');
    const equipmentPricing = Array.from(document.querySelectorAll('[data-equipment-pricing-mode]'));
    const equipmentSelectors = equipmentPricing.map(select => select.closest('.card.compact').querySelector('input[name="resource_uuids[]"]'));

    function state(section, enabled) {
        section.style.display = enabled ? 'block' : 'none';
        section.querySelectorAll('input, select').forEach(control => { control.disabled = !enabled; });
    }

    function sync() {
        const paidEquipment = equipmentPricing.some(select => {
            const selected = select.closest('.card.compact').querySelector('input[name="resource_uuids[]"]');
            return selected.checked && select.value !== 'free';
        });
        const paid = (pricing.value !== 'free' && pricing.value !== '') || paidEquipment;
        policy.style.display = paid ? 'block' : 'none';
        freeHelp.style.display = paid ? 'none' : 'block';
        collection.disabled = false;
        if (!paid) collection.value = 'full';
        const usesRetainer = paid && collection.value === 'retainer';
        state(retainer, usesRetainer);
        if (usesRetainer) {
            state(fixed, retainerType.value === 'fixed');
            state(percentage, retainerType.value === 'percentage');
            retainerType.disabled = false;
            document.getElementById('retainer_amount').required = retainerType.value === 'fixed';
            document.getElementById('retainer_percentage').required = retainerType.value === 'percentage';
        }
    }

    [pricing, collection, retainerType, ...equipmentPricing, ...equipmentSelectors].forEach(control => control.addEventListener('change', sync));
    sync();
})();
</script>

<script>
(() => {
    const enabled = document.getElementById('ticketing_enabled');
    const fields = document.getElementById('ticketing-fields');
    const scheme = document.getElementById('ticket_seating_scheme');
    const optionalField = document.getElementById('ticket-seat-optional-field');
    const optional = document.getElementById('ticket_seat_optional');
    const blockFields = document.getElementById('ticket-seat-block-fields');
    const consecutiveHelp = document.getElementById('ticket-consecutive-help');
    const list = document.getElementById('ticket-seat-block-list');
    const template = document.getElementById('ticket-seat-block-template');
    const add = document.getElementById('add-ticket-seat-block');
    const attendance = document.getElementById('attendance_mode');
    const duration = document.getElementById('duration_mode');
    const pricing = document.getElementById('pricing_mode');
    const singleAttendance = attendance.querySelector('option[value="single"]');
    const variableDuration = duration.querySelector('option[value="variable"]');
    const fixedTotalPricing = pricing.querySelector('option[value="fixed"]');
    const durationRatePricing = pricing.querySelector('option[value="rate"]');
    let nextIndex = Array.from(list.querySelectorAll('[data-index]')).reduce(
        (highest, row) => Math.max(highest, Number.parseInt(row.dataset.index, 10) || 0),
        -1,
    ) + 1;

    function sectionState(section, active) {
        section.style.display = active ? 'block' : 'none';
        section.querySelectorAll('input, select, button').forEach(control => { control.disabled = !active; });
    }

    function prepare(row) {
        row.querySelector('[data-remove-ticket-seat-block]').addEventListener('click', () => row.remove());
    }

    function optionState(option, available) {
        option.hidden = !available;
        option.disabled = !available;
    }

    function syncRow(row, selected, seatsOptional, paidTickets) {
        const usesSection = selected === 'section_seat' || selected === 'section_row_seat';
        const usesRow = selected === 'row_seat' || selected === 'section_row_seat';
        sectionState(row.querySelector('[data-ticket-section-field]'), usesSection);
        sectionState(row.querySelector('[data-ticket-row-field]'), usesRow);
        sectionState(row.querySelector('[data-ticket-quantity-field]'), seatsOptional);
        sectionState(row.querySelector('[data-ticket-seat-fee-field]'), paidTickets);

        const first = row.querySelector('[data-ticket-first-seat-field] input');
        const last = row.querySelector('[data-ticket-last-seat-field] input');
        first.disabled = false;
        last.disabled = false;
        first.required = !seatsOptional;
        last.required = !seatsOptional;
        row.querySelector('[data-ticket-section-field] input').required = usesSection;
        row.querySelector('[data-ticket-row-field] input').required = usesRow;
    }

    function sync() {
        const active = enabled.checked;
        sectionState(fields, active);
        optionState(singleAttendance, !active);
        optionState(variableDuration, !active);
        optionState(fixedTotalPricing, !active);
        optionState(durationRatePricing, !active);
        if (!active) return;

        if (attendance.value !== 'group') {
            attendance.value = 'group';
            attendance.dispatchEvent(new Event('change'));
        }
        if (duration.value !== 'fixed') {
            duration.value = 'fixed';
            duration.dispatchEvent(new Event('change'));
        }
        if (!['free', 'per_attendee'].includes(pricing.value)) {
            pricing.value = 'per_attendee';
            pricing.dispatchEvent(new Event('change'));
        }

        const selected = scheme.value;
        const paidTickets = pricing.value === 'per_attendee';
        const usesBlocks = ['section_seat', 'row_seat', 'section_row_seat'].includes(selected);
        const supportsOptional = ['section_seat', 'row_seat'].includes(selected);
        if (!supportsOptional) optional.checked = false;
        sectionState(optionalField, supportsOptional);
        sectionState(blockFields, usesBlocks);
        consecutiveHelp.style.display = selected === 'consecutive' ? 'block' : 'none';

        if (usesBlocks && list.children.length === 0) {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
            const row = wrapper.firstElementChild;
            list.appendChild(row);
            prepare(row);
        }
        list.querySelectorAll('[data-ticket-seat-block]').forEach(row => syncRow(row, selected, supportsOptional && optional.checked, paidTickets));
    }

    list.querySelectorAll('[data-ticket-seat-block]').forEach(prepare);
    add.addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        const row = wrapper.firstElementChild;
        list.appendChild(row);
        prepare(row);
        sync();
    });
    [enabled, scheme, optional, attendance, duration, pricing].forEach(control => control.addEventListener('change', sync));
    sync();
})();
</script>

<script>
(() => {
    function setSectionState(section, active) {
        section.hidden = !active;
        section.querySelectorAll('input, button').forEach(control => {
            control.disabled = !active;
        });
    }

    function prepareBundleRow(row) {
        row.querySelector('[data-remove-equipment-bundle]').addEventListener('click', () => row.remove());
    }

    document.querySelectorAll('[data-equipment-assignment]').forEach(assignment => {
        const pricing = assignment.querySelector('[data-equipment-pricing-mode]');
        const list = assignment.querySelector('[data-equipment-bundle-list]');
        const add = assignment.querySelector('[data-add-equipment-bundle]');
        const resourceUuid = list.dataset.resourceUuid;
        const maximum = assignment.querySelector('input[name^="resource_quantities"]').max;
        let nextIndex = Array.from(list.querySelectorAll('input[name$="[quantity]"]')).reduce((highest, input) => {
            const match = input.name.match(/\]\[(\d+)\]\[quantity\]$/);
            return Math.max(highest, match ? Number.parseInt(match[1], 10) : -1);
        }, -1) + 1;

        const sync = () => {
            assignment.querySelectorAll('[data-equipment-price-fields]').forEach(section => {
                setSectionState(section, section.dataset.equipmentPriceFields === pricing.value);
            });
        };

        list.querySelectorAll('[data-equipment-bundle-row]').forEach(prepareBundleRow);
        add.addEventListener('click', () => {
            const index = nextIndex++;
            const row = document.createElement('div');
            row.className = 'row align-items-end';
            row.dataset.equipmentBundleRow = '';
            row.style.marginBottom = '8px';
            row.innerHTML = `<div class="field" style="margin-bottom:0"><label>Pieces</label><input type="number" min="1" max="${maximum}" name="resource_equipment_bundles[${resourceUuid}][${index}][quantity]"></div><div class="field" style="margin-bottom:0"><label>Bundle price</label><input inputmode="decimal" name="resource_equipment_bundles[${resourceUuid}][${index}][amount]" placeholder="10.00"></div><div class="field" style="margin-bottom:0"><button class="btn" type="button" data-remove-equipment-bundle>Remove</button></div>`;
            list.appendChild(row);
            prepareBundleRow(row);
            row.querySelector('input').focus();
        });
        pricing.addEventListener('change', sync);
        sync();
    });
})();
</script>

<script>
(() => {
    const list = document.getElementById('short-notice-fee-list');
    const template = document.getElementById('short-notice-fee-template');
    const addButton = document.getElementById('add-short-notice-fee');
    let nextIndex = Array.from(list.querySelectorAll('[data-index]')).reduce(
        (highest, row) => Math.max(highest, Number.parseInt(row.dataset.index, 10) || 0),
        -1,
    ) + 1;

    function setFeeFieldState(section, enabled) {
        section.style.display = enabled ? 'block' : 'none';
        section.querySelectorAll('input').forEach((input) => {
            input.disabled = !enabled;
            input.required = enabled;
        });
    }

    function prepareRow(row) {
        const type = row.querySelector('[data-short-notice-type]');
        const sync = () => {
            setFeeFieldState(row.querySelector('[data-short-notice-fixed]'), type.value === 'fixed');
            setFeeFieldState(row.querySelector('[data-short-notice-percentage]'), type.value === 'percentage');
        };

        type.addEventListener('change', sync);
        row.querySelector('[data-remove-short-notice]').addEventListener('click', () => row.remove());
        sync();
    }

    list.querySelectorAll('.short-notice-fee-row').forEach(prepareRow);
    addButton.addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        const row = wrapper.firstElementChild;
        list.appendChild(row);
        prepareRow(row);
        row.querySelector('input').focus();
    });
})();
</script>
