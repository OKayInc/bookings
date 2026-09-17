<?php

namespace App\Http\Controllers;

use App\Domain\Bookings\EventAdmissionApprovalService;
use App\Enums\EventAdmissionApprovalStatus;
use App\Models\BookingAnswerFile;
use App\Models\EventAdmissionApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventAdmissionApprovalController extends Controller
{
    public function show(EventAdmissionApproval $approval, string $token, EventAdmissionApprovalService $service): Response
    {
        abort_unless($service->tokenMatches($approval, $token), 404);
        $approval->load(['booking.appointmentType', 'booking.appointment', 'booking.answers.files', 'coordinator', 'respondedBy']);

        return response()->view('public.event-admission-approvals.show', compact('approval', 'token'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function respond(
        Request $request,
        EventAdmissionApproval $approval,
        string $token,
        EventAdmissionApprovalService $service,
    ): View|RedirectResponse {
        abort_unless($service->tokenMatches($approval, $token), 404);
        $data = $request->validate([
            'action' => ['required', 'in:accepted,declined'],
            'response_note' => ['nullable', 'string', 'max:5000'],
        ]);
        try {
            $service->respond($approval, EventAdmissionApprovalStatus::from($data['action']), $data['response_note'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        return view('public.event-admission-approvals.result', [
            'approval' => $approval->fresh(['booking.appointmentType']),
        ]);
    }

    public function answerFile(
        EventAdmissionApproval $approval,
        string $token,
        BookingAnswerFile $file,
        EventAdmissionApprovalService $service,
    ): StreamedResponse {
        abort_unless($service->tokenMatches($approval, $token), 404);
        abort_unless(hash_equals($file->booking_id, $approval->booking_id), 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, [
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
