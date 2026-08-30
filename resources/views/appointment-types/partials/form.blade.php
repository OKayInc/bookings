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
        <select name="pricing_mode" id="pricing_mode">
            @foreach($pricingModes as $mode)
                <option value="{{ $mode->value }}" @selected(old('pricing_mode', $appointmentType?->pricing_mode?->value ?? 'free') === $mode->value)>
                    {{ ucfirst($mode->value) }}
                </option>
            @endforeach
        </select>
        <div class="muted">Currency: {{ $organization->currency }}. Payment collection/retainers will be implemented in M8.</div>
    </div>

    <div class="field" id="fixed-price-fields">
        <label for="fixed_price">Fixed total price ({{ $organization->currency }})</label>
        <input id="fixed_price" inputmode="decimal" name="fixed_price" value="{{ old('fixed_price', $fixedPriceInput) }}" placeholder="150.00">
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
    <div class="muted">A deadline of 0 allows cancellation until the appointment starts. Payment/refund consequences will be connected in M8.</div>
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

    function setSectionState(section, enabled) {
        section.style.display = enabled ? 'block' : 'none';

        section.querySelectorAll('input, select, textarea').forEach((control) => {
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
        const fixedPricingMode = pricingMode.value === 'fixed';
        const ratePricingMode = pricingMode.value === 'rate';

        setSectionState(passwordField, passwordProtected);
        setSectionState(capacityField, groupAttendance);
        setSectionState(meetingProviderFields, onlineAppointment);
        setSectionState(bookingSeasonFields, seasonalAppointment);
        setSectionState(fixedDuration, fixedDurationMode);
        setSectionState(variableDuration, !fixedDurationMode);
        setSectionState(fixedPrice, fixedPricingMode);
        setSectionState(ratePrice, ratePricingMode);

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
    }

    [visibility, attendanceMode, online, seasonalAvailability, durationMode, pricingMode].forEach((element) => element.addEventListener('change', sync));
    sync();
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
