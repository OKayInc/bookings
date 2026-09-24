<?php

namespace App\Http\Controllers;

use App\Domain\Customers\CustomerReputationService;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingOutcomeReviewController extends Controller
{
    public function show(Booking $booking): View
    {
        $booking->load(['appointmentType', 'appointment', 'outcome']);
        return view('public.bookings.outcome-review', compact('booking'));
    }

    public function store(Request $request, Booking $booking, CustomerReputationService $service): RedirectResponse
    {
        $data = $request->validate(['outcome' => ['required', 'in:successful,no_show']]);
        $service->recordOutcome($booking, $data['outcome'], null, 'email_link');

        return redirect()->to($request->fullUrl())->with('success', 'Attendance outcome recorded.');
    }
}
