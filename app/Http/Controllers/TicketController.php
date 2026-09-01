<?php

namespace App\Http\Controllers;

use App\Domain\Tickets\TicketBarcodeService;
use App\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\Ticket;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class TicketController extends Controller
{
    public function index(OrganizationContext $context): View
    {
        $organization = $context->organization();
        $this->authorize('checkInTickets', $organization);

        $appointments = $organization->appointments()
            ->where('ticketing_enabled', true)
            ->with('appointmentType')
            ->withCount([
                'tickets as active_tickets_count' => fn ($query) => $query->where('status', '!=', TicketStatus::Voided->value),
                'tickets as checked_in_tickets_count' => fn ($query) => $query->where('status', TicketStatus::CheckedIn->value),
            ])
            ->orderByDesc('starts_at_utc')
            ->limit(50)
            ->get();

        $recentTickets = $organization->tickets()
            ->whereNotNull('checked_in_at_utc')
            ->with(['attendee', 'booking.appointmentType', 'appointment', 'checkedInBy'])
            ->latest('checked_in_at_utc')
            ->limit(20)
            ->get();

        return view('tickets.index', compact('organization', 'appointments', 'recentTickets'));
    }

    public function checkIn(Request $request, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('checkInTickets', $organization);
        $data = $request->validate(['code' => ['required', 'string', 'max:24']]);
        $code = strtoupper(trim($data['code']));

        try {
            $ticket = DB::transaction(function () use ($organization, $request, $code): Ticket {
                $ticket = Ticket::query()
                    ->where('organization_id', $organization->getKey())
                    ->where('code', $code)
                    ->lockForUpdate()
                    ->first();

                if ($ticket === null) {
                    throw new RuntimeException('No ticket with that code belongs to this organization.');
                }

                $ticket->load(['booking', 'attendee']);
                if ($ticket->status === TicketStatus::CheckedIn) {
                    throw new RuntimeException('This ticket was already checked in at '.$ticket->checked_in_at_utc?->setTimezone($organization->timezone)->format('M j, Y g:i A').'.');
                }
                if ($ticket->status !== TicketStatus::Issued || $ticket->booking->status->value !== 'confirmed') {
                    throw new RuntimeException('This ticket is not valid for admission. Its current status is '.$ticket->status->label().'.');
                }

                $ticket->update([
                    'status' => TicketStatus::CheckedIn->value,
                    'checked_in_at_utc' => now('UTC'),
                    'checked_in_by_person_id' => $request->user()->person_id,
                ]);

                return $ticket->fresh(['attendee']);
            }, 3);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $attendee = trim(($ticket->attendee?->first_name ?? '').' '.($ticket->attendee?->last_name ?? '')) ?: 'Unnamed attendee';

        return back()->with('success', 'Checked in '.$attendee.' · '.$ticket->seat_display.' · '.$ticket->code.'.');
    }

    public function undo(Ticket $ticket, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('checkInTickets', $organization);
        abort_unless(hash_equals($ticket->organization_id, $organization->getKey()), 404);

        $updated = Ticket::query()
            ->whereKey($ticket->getKey())
            ->where('status', TicketStatus::CheckedIn->value)
            ->update([
                'status' => TicketStatus::Issued->value,
                'checked_in_at_utc' => null,
                'checked_in_by_person_id' => null,
                'updated_at' => now(),
            ]);

        return back()->with(
            $updated === 1 ? 'success' : 'error',
            $updated === 1 ? 'Ticket check-in was undone.' : 'Only a checked-in ticket can be returned to valid status.',
        );
    }

    public function show(
        Booking $booking,
        Ticket $ticket,
        OrganizationContext $context,
        TicketBarcodeService $barcodes,
    ): View {
        $organization = $context->organization();
        $this->authorize('checkInTickets', $organization);
        abort_unless(hash_equals($booking->organization_id, $organization->getKey()), 404);
        abort_unless(hash_equals($ticket->booking_id, $booking->getKey()), 404);
        $ticket->load(['attendee', 'appointment', 'booking.appointmentType']);

        return view('tickets.show', [
            'organization' => $organization,
            'booking' => $booking,
            'ticket' => $ticket,
            'barcodeSvg' => $barcodes->svg($ticket->code),
        ]);
    }
}
