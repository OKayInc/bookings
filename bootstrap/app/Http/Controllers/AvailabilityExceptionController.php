<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAvailabilityExceptionRequest;
use App\Models\AvailabilityException;
use App\Models\AvailabilitySchedule;
use App\Support\Organizations\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class AvailabilityExceptionController extends Controller
{
    public function store(
        StoreAvailabilityExceptionRequest $request,
        AvailabilitySchedule $schedule,
        OrganizationContext $context,
    ): RedirectResponse {
        $organization = $context->organization();
        abort_unless(hash_equals((string) $schedule->organization_id, (string) $organization->getKey()), 404);
        $this->authorize('manageScheduling', $organization);
        $data = $request->validated();

        $start = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $data['starts_at_local'], $schedule->timezone)->utc();
        $end = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $data['ends_at_local'], $schedule->timezone)->utc();

        $schedule->exceptions()->create([
            'starts_at_utc' => $start,
            'ends_at_utc' => $end,
            'mode' => $data['mode'],
            'timezone' => $schedule->timezone,
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Availability exception added.');
    }

    public function destroy(
        AvailabilitySchedule $schedule,
        AvailabilityException $exception,
        OrganizationContext $context,
    ): RedirectResponse {
        $organization = $context->organization();
        abort_unless(hash_equals((string) $schedule->organization_id, (string) $organization->getKey()), 404);
        abort_unless(hash_equals((string) $exception->schedule_id, (string) $schedule->getKey()), 404);
        $this->authorize('manageScheduling', $organization);

        $exception->delete();

        return back()->with('success', 'Availability exception removed.');
    }
}
