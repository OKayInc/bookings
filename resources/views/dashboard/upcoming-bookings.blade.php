<section class="card" aria-labelledby="upcoming-bookings-title">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h2 id="upcoming-bookings-title" class="h4 mb-1">Upcoming bookings</h2>
            <p class="muted small mb-0">
                {{ $canManageBookings ? 'Organization bookings' : 'Bookings assigned to you' }} · {{ $organization->timezone }}
            </p>
        </div>
        <form method="get" action="{{ route('dashboard') }}" class="d-flex flex-wrap align-items-end gap-2 ms-sm-auto" data-dashboard-filters>
            <div>
                <label for="dashboard-per-page" class="form-label small mb-1">Bookings per page</label>
                <select id="dashboard-per-page" name="per_page" class="form-select form-select-sm">
                    @foreach($pageSizeOptions as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="dashboard-range" class="form-label small mb-1">Date range</label>
                <select id="dashboard-range" name="range" class="form-select form-select-sm" aria-describedby="dashboard-range-description">
                    @foreach($rangeOptions as $value => $label)
                        <option value="{{ $value }}" @selected($range === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
        </form>
    </div>
    <p id="dashboard-range-description" class="small muted mb-3">
        Upcoming and in-progress events ·
        @if($rangeEnd)
            {{ $now->format('M j, Y') }} – {{ $rangeEnd->subDay()->format('M j, Y') }}
        @else
            All upcoming dates
        @endif
        · {{ number_format($upcomingBookings->total()) }} {{ $upcomingBookings->total() === 1 ? 'booking' : 'bookings' }}
    </p>

    @if($upcomingBookings->count())
        <div class="table-scroll" role="region" aria-label="Upcoming booking list" tabindex="0">
            <table class="table table-hover align-middle">
                <caption class="visually-hidden">Upcoming bookings in chronological order. Times are in {{ $organization->timezone }}.</caption>
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Appointment / booking</th>
                        <th scope="col">Client</th>
                        <th scope="col">Booking status</th>
                        <th scope="col">Payment status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($upcomingBookings as $booking)
                        @php
                            $start = $booking->appointment->starts_at_utc->setTimezone($organization->timezone);
                            $end = $booking->appointment->ends_at_utc->setTimezone($organization->timezone);
                            $pending = in_array($booking->status, [
                                \App\Enums\BookingStatus::PendingEmailVerification,
                                \App\Enums\BookingStatus::PendingContractReview,
                                \App\Enums\BookingStatus::PendingStaffConfirmation,
                                \App\Enums\BookingStatus::PendingPayment,
                            ], true);
                            $noPaymentRequired = $booking->price_minor === 0
                                && $booking->paid_minor === 0
                                && $booking->refunded_minor === 0
                                && in_array($booking->payment_status, [\App\Enums\BookingPaymentStatus::Unpaid, \App\Enums\BookingPaymentStatus::Paid], true);
                        @endphp
                        <tr>
                            <td>
                                <strong class="text-nowrap"><time datetime="{{ $start->toIso8601String() }}">{{ $start->format('D, M j, Y') }}</time></strong>
                                <div class="small text-nowrap">{{ $start->format('g:i A T') }} – {{ $end->format($start->isSameDay($end) ? 'g:i A T' : 'D, M j, Y g:i A T') }}</div>
                                @if($booking->appointment->ticketing_enabled)
                                    <div class="small muted">Doors open at {{ $start->format('g:i A') }}</div>
                                    @if($booking->appointment->show_starts_at_utc)
                                        <div class="small muted">Show starts {{ $booking->appointment->show_starts_at_utc->setTimezone($organization->timezone)->format('M j, g:i A') }}</div>
                                    @endif
                                @endif
                                @if($booking->status->occupiesCapacity() && $booking->appointment->status === \App\Enums\AppointmentStatus::Scheduled && $start->lte($now))
                                    <span class="badge text-bg-info mt-1">In progress</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('bookings.show', $booking) }}" class="fw-semibold">{{ $booking->appointmentType->name }}</a>
                                <div class="small muted">{{ $booking->reference }}</div>
                            </td>
                            <td>
                                {{ $booking->first_name }} {{ $booking->last_name }}
                                <div class="small muted">{{ $booking->attendee_count }} {{ $booking->attendee_count === 1 ? 'attendee' : 'attendees' }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $booking->status->badgeClass() }} text-wrap">{{ $pending ? 'To be confirmed' : $booking->status->label() }}</span>
                                @if($pending)
                                    <div class="small muted mt-1">{{ $booking->status->label() }}</div>
                                @endif
                                @if($booking->appointment->status === \App\Enums\AppointmentStatus::Cancelled && $booking->status !== \App\Enums\BookingStatus::Cancelled)
                                    <div class="mt-1"><span class="badge text-bg-danger">Event cancelled</span></div>
                                @endif
                                @if($booking->schedule_warning_count > 0)
                                    <div class="mt-1"><span class="badge text-bg-warning">Schedule warning</span></div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $noPaymentRequired ? 'text-bg-secondary' : $booking->payment_status->badgeClass() }} text-wrap">{{ $noPaymentRequired ? 'No payment required' : $booking->payment_status->label() }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
            <span class="small muted">Showing {{ $upcomingBookings->firstItem() }}–{{ $upcomingBookings->lastItem() }} of {{ number_format($upcomingBookings->total()) }}</span>
            {{ $upcomingBookings->links('pagination::simple-bootstrap-5') }}
        </div>
    @else
        <div class="text-center border rounded bg-body-tertiary p-4">
            <p class="fw-semibold mb-1">No upcoming bookings{{ $upcomingBookings->total() > 0 ? ' on this page' : ' in this date range' }}.</p>
            <p class="small muted mb-2">{{ $canManageBookings ? 'Try a wider date range to see more bookings.' : 'Bookings appear here when one of your resources is assigned.' }}</p>
            @if($upcomingBookings->total() > 0)
                <a href="{{ route('dashboard', ['range' => $range, 'per_page' => $perPage]) }}" class="btn btn-sm">Return to first page</a>
            @elseif($range !== 'all')
                <a href="{{ route('dashboard', ['range' => 'all', 'per_page' => $perPage]) }}" class="btn btn-sm">Show all upcoming dates</a>
            @endif
        </div>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mt-3 small" aria-label="Status colour legend">
        <span class="muted">Status colours:</span>
        <span class="badge text-bg-success">Confirmed / paid</span>
        <span class="badge text-bg-warning">Pending / partially paid</span>
        <span class="badge text-bg-danger">Cancelled / unpaid</span>
        <span class="badge text-bg-dark">Declined</span>
        <span class="badge text-bg-info">In progress / partially refunded</span>
        <span class="badge text-bg-secondary">Refunded / waived / no payment required</span>
    </div>
</section>

@push('scripts')
<script src="{{ asset('js/dashboard-filters.js') }}" defer></script>
@endpush
