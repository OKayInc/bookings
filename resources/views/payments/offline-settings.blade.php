@php
    $globalMinutes = max(1, (int) ($settings->offline_payment_window_minutes ?: 1440));
    $globalInstructions = app(\App\Support\Html\OfflinePaymentInstructions::class)
        ->sanitize(old('offline_payment_instructions', $settings->offline_payment_instructions));
@endphp
<section class="section-card mb-4" id="offline-payments" aria-labelledby="offline-payments-heading">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-start">
        <div>
            <h2 id="offline-payments-heading">Offline payments / e-Transfer</h2>
            <p>Set organization defaults once. Appointment types inherit them unless you explicitly configure an override.</p>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('booking-payment-review.index') }}">Review offline payments and unpaid balances</a>
    </div>

    <input type="hidden" name="offline_payment_enabled" value="0">
    <label class="inline-check" for="offline_payment_enabled">
        <input id="offline_payment_enabled" type="checkbox" name="offline_payment_enabled" value="1"
            @checked(old('offline_payment_enabled', (bool) $settings->offline_payment_enabled))>
        Offer offline payment / e-Transfer by default
    </label>
    @error('offline_payment_enabled')<p class="text-danger">{{ $message }}</p>@enderror

    <div class="field mt-3">
        <label for="offline_payment_window_minutes">Default payment window (minutes)</label>
        <input id="offline_payment_window_minutes" type="number" name="offline_payment_window_minutes"
            min="1" max="43200" step="1" value="{{ old('offline_payment_window_minutes', $globalMinutes) }}">
        <p class="muted">Default: 1,440 minutes (24 hours). Maximum: 43,200 minutes (30 days). The deadline can never extend past the appointment start.</p>
        @error('offline_payment_window_minutes')<p class="text-danger">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="offline_payment_instructions">Default instructions shown to customers</label>
        <textarea id="offline_payment_instructions" name="offline_payment_instructions" data-rich-text-editor rows="10" maxlength="10000">{{ $globalInstructions }}</textarea>
        <p class="muted">Required when the organization default is enabled. Include recipient details and the booking reference customers should use. Unsafe HTML is removed.</p>
        @error('offline_payment_instructions')<p class="text-danger">{{ $message }}</p>@enderror
    </div>

    <div class="alert alert-info">
        A transfer reference is not proof of payment. Staff must verify the money received and record the amount. Existing bookings keep the instructions and deadline already snapshotted when offline payment was selected.
    </div>

    @if($offlineAppointmentTypes->isEmpty())
        <p>No appointment types yet. New appointment types will inherit these defaults.</p>
        <a class="btn btn-outline-primary" href="{{ route('appointment-types.create') }}">Create appointment type</a>
    @else
        <h3>Appointment types</h3>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Appointment type</th><th>Source</th><th>Offline payment</th><th>Payment window</th><th>Setup</th></tr></thead>
                <tbody>
                @foreach($offlineAppointmentTypes as $offlineType)
                    @php
                        $effective = $offlineType->effectiveOfflinePaymentSettings();
                        $minutes = $effective['window_minutes'];
                        $divisor = $minutes % 1440 === 0 ? 1440 : ($minutes % 60 === 0 ? 60 : 1);
                        $value = intdiv($minutes, $divisor);
                        $unit = $divisor === 1440 ? 'day' : ($divisor === 60 ? 'hour' : 'minute');
                    @endphp
                    <tr>
                        <td>{{ $offlineType->name }} @if(! $offlineType->is_active)<span class="badge text-bg-secondary">Inactive appointment type</span>@endif</td>
                        <td><span class="badge {{ $effective['source'] === 'organization' ? 'text-bg-info' : 'text-bg-secondary' }}">{{ $effective['source'] === 'organization' ? 'Organization default' : 'Custom override' }}</span></td>
                        <td>
                            @if(! $effective['enabled'])
                                <span class="badge text-bg-secondary">Disabled</span>
                            @elseif(app(\App\Support\Html\OfflinePaymentInstructions::class)->sanitize($effective['instructions']) === null)
                                <span class="badge text-bg-warning">Needs instructions</span>
                            @else
                                <span class="badge text-bg-success">Enabled</span>
                            @endif
                        </td>
                        <td>{{ $value }} {{ $unit }}{{ $value === 1 ? '' : 's' }}</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('appointment-types.offline-payments.edit', $offlineType) }}">Configure / override</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
