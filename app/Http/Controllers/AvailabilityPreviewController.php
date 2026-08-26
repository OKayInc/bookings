<?php

namespace App\Http\Controllers;

use App\Domain\Availability\AvailabilityService;
use App\Enums\DurationMode;
use App\Http\Requests\AvailabilityPreviewRequest;
use App\Models\AppointmentType;
use App\Support\Organizations\OrganizationContext;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\View\View;

class AvailabilityPreviewController extends Controller
{
    public function __invoke(
        AvailabilityPreviewRequest $request,
        OrganizationContext $context,
        AvailabilityService $availability,
    ): View {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $appointmentTypes = $organization->appointmentTypes()->with('resources')->orderBy('name')->get();
        $selected = null;
        $slots = [];
        $date = $request->validated('date') ?: now($organization->timezone)->format('Y-m-d');
        $timezone = $request->validated('timezone') ?: $organization->timezone;
        $durationValue = $request->validated('duration_value');
        $previewError = null;
        $timezones = DateTimeZone::listIdentifiers();

        if ($request->filled('appointment_type')) {
            $selected = AppointmentType::whereUuid((string) $request->input('appointment_type'))
                ->where('organization_id', $organization->getKey())
                ->with(['organization', 'resources.organization'])
                ->firstOrFail();

            if ($selected->duration_mode === DurationMode::Variable && $durationValue === null) {
                $durationValue = (int) $selected->minimum_duration_value;
            }

            $localStart = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', $timezone);
            $localEnd = $localStart->addDay();
            try {
                $slots = $availability->slots($selected, $localStart->utc(), $localEnd->utc(), $durationValue ? (int) $durationValue : null, $timezone);
            } catch (\InvalidArgumentException $exception) {
                $previewError = $exception->getMessage();
                $slots = [];
            }
        }

        return view('availability.preview', compact(
            'organization', 'appointmentTypes', 'selected', 'slots', 'date', 'timezone', 'durationValue', 'previewError', 'timezones'
        ));
    }
}
