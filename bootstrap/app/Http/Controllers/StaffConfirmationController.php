<?php

namespace App\Http\Controllers;

use App\Domain\Bookings\BookingWorkflowService;
use App\Domain\Bookings\ResourceConfirmationService;
use App\Enums\ResourceConfirmationStatus;
use App\Models\ResourceConfirmation;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class StaffConfirmationController extends Controller
{
    public function show(ResourceConfirmation $confirmation, string $token, ResourceConfirmationService $service): View
    {
        abort_unless($service->tokenMatches($confirmation, $token), 404);
        $confirmation->load(['booking.appointmentType', 'booking.appointment', 'resource', 'person']);

        return view('public.staff-confirmations.show', compact('confirmation', 'token'));
    }

    public function respond(
        Request $request,
        ResourceConfirmation $confirmation,
        string $token,
        ResourceConfirmationService $service,
        BookingWorkflowService $workflow,
    ): View|RedirectResponse {
        abort_unless($service->tokenMatches($confirmation, $token), 404);
        $data = $request->validate([
            'action' => ['required', 'in:accepted,declined'],
            'response_note' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $service->respond($confirmation, ResourceConfirmationStatus::from($data['action']), $data['response_note'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['confirmation' => $exception->getMessage()]);
        }

        $workflow->refreshStatus($confirmation->booking()->with(['appointmentType', 'contractSubmissions', 'appointment.resources.person'])->firstOrFail());
        $confirmation = $confirmation->fresh(['booking.appointmentType', 'booking.appointment', 'resource']);

        return view('public.staff-confirmations.result', compact('confirmation'));
    }

    public function mine(Request $request, OrganizationContext $context): View
    {
        $confirmations = ResourceConfirmation::query()
            ->where('organization_id', $context->organization()->getKey())
            ->with(['booking.appointmentType', 'booking.appointment', 'resource'])
            ->where('person_id', $request->user()->person_id)
            ->latest()
            ->paginate(50);

        return view('staff-confirmations.index', compact('confirmations'));
    }
}
