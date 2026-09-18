<?php

namespace App\Http\Controllers;

use App\Support\Organizations\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OrganizationContext $context): View
    {
        $organization = $context->organization();
        $rangeOptions = [
            'today' => 'Today',
            'tomorrow' => 'Today and tomorrow',
            'week' => 'From today to 1 week',
            'month' => 'From today to next month',
            'all' => 'All',
        ];
        $pageSizeOptions = [10, 25, 50, 100];
        $filters = $request->validate([
            'range' => ['sometimes', 'required', 'string', Rule::in(array_keys($rangeOptions))],
            'per_page' => ['sometimes', 'required', 'integer', Rule::in($pageSizeOptions)],
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000'],
        ]);
        $range = $filters['range'] ?? 'week';
        $perPage = (int) ($filters['per_page'] ?? 10);
        $now = CarbonImmutable::now($organization->timezone);
        $today = $now->startOfDay();
        // Calendar arithmetic in the organization timezone preserves DST boundaries.
        $rangeEnd = match ($range) {
            'today' => $today->addDay(),
            'tomorrow' => $today->addDays(2),
            'week' => $today->addWeek(),
            'month' => $today->addMonthNoOverflow(),
            'all' => null,
        };
        $canManageBookings = $request->user()->can('manageScheduling', $organization);
        $query = $organization->bookings()
            ->select('bookings.*')
            ->join('appointments', 'appointments.id', '=', 'bookings.appointment_id')
            ->where('appointments.organization_id', $organization->getKey())
            // Include events already in progress, including overnight events.
            ->where('appointments.ends_at_utc', '>', $now->utc())
            ->when($rangeEnd, fn ($query) => $query->where('appointments.starts_at_utc', '<', $rangeEnd->utc()))
            ->with(['appointmentType', 'appointment'])
            ->withCount(['scheduleProposals as schedule_warning_count' => fn ($query) => $query->where('warning_active', true)])
            ->orderBy('appointments.starts_at_utc')
            ->orderBy('bookings.reference');

        // Match BookingController::show: employees may view assigned bookings only.
        if (! $canManageBookings) {
            $query->whereHas('appointment.resources', fn ($query) => $query->where('resources.person_id', $request->user()->person_id));
        }

        $upcomingBookings = $query->paginate($perPage)
            ->appends(['range' => $range, 'per_page' => $perPage]);

        return view('dashboard', [
            'organization' => $organization,
            'upcomingBookings' => $upcomingBookings,
            'canManageBookings' => $canManageBookings,
            'rangeOptions' => $rangeOptions,
            'pageSizeOptions' => $pageSizeOptions,
            'range' => $range,
            'perPage' => $perPage,
            'now' => $now,
            'rangeEnd' => $rangeEnd,
            'resourceCount' => $organization->resources()->count(),
            'appointmentTypeCount' => $organization->appointmentTypes()->count(),
            'memberCount' => $organization->memberships()->count(),
        ]);
    }
}
