<?php

namespace App\Http\Controllers;

use App\Domain\Plans\PlanEntitlementService;
use App\Enums\PlanLevel;
use App\Models\Appointment;
use App\Support\Organizations\ActiveOrganizationResolver;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function show(Request $request, Appointment $appointment, OrganizationContext $context, ActiveOrganizationResolver $resolver, PlanEntitlementService $plans): View
    {
        $organization = $appointment->organization;
        abort_unless(Gate::forUser($request->user())->allows('checkInTickets', $organization), 404);
        $canManage = Gate::forUser($request->user())->allows('manageScheduling', $organization);
        $isAssignedStaff = $appointment->resources()->where('resources.person_id', $request->user()->person_id)->exists();
        abort_unless($canManage || $isAssignedStaff, 403);

        // Calendar links must work even while the member is viewing another organization.
        $resolver->select(
            $request->user(), $organization, $request,
            saveUser: ! hash_equals((string) $request->user()->active_organization_id, (string) $organization->getKey()),
        );
        $context->set($organization);

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
