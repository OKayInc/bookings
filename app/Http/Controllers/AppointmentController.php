<?php

namespace App\Http\Controllers;

use App\Domain\Plans\PlanEntitlementService;
use App\Enums\PlanLevel;
use App\Models\Appointment;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function show(Request $request, Appointment $appointment, OrganizationContext $context, PlanEntitlementService $plans): View
    {
        $organization = $context->organization();
        abort_unless(hash_equals($appointment->organization_id, $organization->getKey()), 404);
        $canManage = Gate::forUser($request->user())->allows('manageScheduling', $organization);
        $isAssignedStaff = $appointment->resources()->where('resources.person_id', $request->user()->person_id)->exists();
        abort_unless($canManage || $isAssignedStaff, 403);

        $appointment->load(['appointmentType', 'bookings' => fn ($query) => $query
            ->whereNotIn('status', ['cancelled', 'declined'])->orderBy('created_at')]);

        return view('appointments.show', [
            'appointment' => $appointment,
            'showAppointmentAdvertisement' => $plans->for($organization)->level === PlanLevel::Free
                && config('plans.adsense.enabled')
                && filled(config('plans.adsense.client'))
                && filled(config('plans.adsense.slot')),
        ]);
    }
}
