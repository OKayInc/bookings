<?php

namespace App\Http\Controllers;

use App\Domain\Bookings\BookingScheduleProposalService;
use App\Enums\ScheduleProposalStatus;
use App\Models\BookingScheduleProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ScheduleProposalController extends Controller
{
    public function show(
        BookingScheduleProposal $proposal,
        string $token,
        BookingScheduleProposalService $service,
    ): View {
        abort_unless($service->tokenMatches($proposal, $token), 404);
        if ($proposal->status === ScheduleProposalStatus::Pending && $proposal->expires_at_utc?->isPast()) {
            $service->expireForBooking($proposal->booking);
            $proposal->refresh();
        }
        $proposal->load(['booking.organization', 'booking.appointmentType', 'booking.appointment', 'proposedBy']);

        return view('public.bookings.schedule-proposal', ['proposal' => $proposal, 'token' => $token, 'organization' => $proposal->booking->organization]);
    }

    public function respond(
        Request $request,
        BookingScheduleProposal $proposal,
        string $token,
        BookingScheduleProposalService $service,
    ): RedirectResponse|View {
        abort_unless($service->tokenMatches($proposal, $token), 404);
        $data = $request->validate([
            'action' => ['required', 'in:accept,keep,cancel'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            match ($data['action']) {
                'accept' => $service->accept($proposal),
                'keep' => $service->keepOriginal($proposal),
                'cancel' => $service->cancelBooking($proposal, $data['reason'] ?? null),
            };
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $proposal = $proposal->fresh(['booking.organization', 'booking.appointmentType', 'booking.appointment', 'proposedBy']);
        return view('public.bookings.schedule-proposal', ['proposal' => $proposal, 'token' => $token, 'organization' => $proposal->booking->organization]);
    }
}
