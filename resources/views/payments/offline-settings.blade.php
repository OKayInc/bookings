<section class="section-card mb-4" id="offline-payments" aria-labelledby="offline-payments-heading">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-start">
        <div>
            <h2 id="offline-payments-heading">Offline payments / e-Transfer</h2>
            <p>Accept e-Transfers or another offline payment method without a Stripe or PayPal account.</p>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('booking-payment-review.index') }}">Review offline payments and unpaid balances</a>
    </div>
    <p>Enable offline payment, enter the instructions customers should follow, and set the payment window separately for each appointment type below.</p>
    <div class="alert alert-info">
        A transfer reference is not proof of payment. Staff must verify the money received and record the amount. When the deadline expires with no verified payment, the reservation is released and the customer receives an explanation.
    </div>

    @if($offlineAppointmentTypes->isEmpty())
        <p>No appointment types yet. Create an appointment type to configure offline payments.</p>
        <a class="btn btn-primary" href="{{ route('appointment-types.create') }}">Create appointment type</a>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th scope="col">Appointment type</th><th scope="col">Offline payment</th><th scope="col">Payment window</th><th scope="col">Setup</th></tr></thead>
                <tbody>
                @foreach($offlineAppointmentTypes as $offlineType)
                    @php
                        $minutes = max(1, (int) ($offlineType->offline_payment_window_minutes ?: 1440));
                        $divisor = $minutes % 1440 === 0 ? 1440 : ($minutes % 60 === 0 ? 60 : 1);
                        $value = intdiv($minutes, $divisor);
                        $unit = $divisor === 1440 ? 'day' : ($divisor === 60 ? 'hour' : 'minute');
                    @endphp
                    <tr>
                        <td>
                            {{ $offlineType->name }}
                            @if(! $offlineType->is_active)<span class="badge text-bg-secondary">Inactive appointment type</span>@endif
                        </td>
                        <td>
                            @if(! $offlineType->offline_payment_enabled)
                                <span class="badge text-bg-secondary">Disabled</span>
                            @elseif(app(\App\Support\Html\OfflinePaymentInstructions::class)->sanitize($offlineType->offline_payment_instructions) === null)
                                <span class="badge text-bg-warning">Needs instructions</span>
                            @else
                                <span class="badge text-bg-success">Enabled</span>
                            @endif
                        </td>
                        <td>{{ $value }} {{ $unit }}{{ $value === 1 ? '' : 's' }}</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('appointment-types.offline-payments.edit', $offlineType) }}" aria-label="Configure offline payments for {{ $offlineType->name }}">Configure offline payments</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
    <p class="muted mb-0">Each Configure link opens that appointment type's existing offline-payment settings. Changes there do not alter existing booking deadlines or instructions. The online checkout preference below applies only to Stripe and PayPal.</p>
</section>
