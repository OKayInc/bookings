<?php

namespace App\Http\Controllers;

use App\Domain\Availability\OrganizationHolidayPresetCatalog;
use App\Domain\Availability\OrganizationHolidayService;
use App\Enums\HolidayRuleType;
use App\Http\Requests\StoreOrganizationHolidayRequest;
use App\Models\OrganizationHoliday;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrganizationHolidayController extends Controller
{
    public function index(
        OrganizationContext $context,
        OrganizationHolidayPresetCatalog $presets,
        OrganizationHolidayService $service,
    ): View {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $holidays = $organization->holidays()->orderByDesc('is_active')->orderBy('name')->get();

        return view('availability.holidays.index', [
            'organization' => $organization,
            'holidays' => $holidays,
            'presets' => $presets->all(),
            'ruleTypes' => HolidayRuleType::cases(),
            'descriptions' => $holidays->mapWithKeys(fn (OrganizationHoliday $holiday) => [
                $holiday->uuid => $service->ruleDescription($holiday),
            ]),
            'nextOccurrences' => $holidays->mapWithKeys(fn (OrganizationHoliday $holiday) => [
                $holiday->uuid => $service->nextOccurrence($holiday, $organization->timezone),
            ]),
        ]);
    }

    public function store(
        StoreOrganizationHolidayRequest $request,
        OrganizationContext $context,
        OrganizationHolidayPresetCatalog $presets,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $data = $request->validated();
        $emptyRule = [
            'month' => null,
            'day' => null,
            'weekday' => null,
            'occurrence' => null,
            'easter_offset_days' => null,
            'specific_date' => null,
        ];

        if (! empty($data['preset_key'])) {
            $preset = $presets->get($data['preset_key']);
            abort_unless($preset !== null, 422);
            $organization->holidays()->updateOrCreate(
                ['preset_key' => $data['preset_key']],
                array_merge($emptyRule, $preset, ['is_active' => true]),
            );

            return back()->with('success', $preset['name'].' is now an organization-wide closure.');
        }

        $organization->holidays()->create(array_merge($emptyRule, [
            'preset_key' => null,
            'name' => $data['name'],
            'rule_type' => $data['rule_type'],
            'month' => $data['month'] ?? null,
            'day' => $data['day'] ?? null,
            'weekday' => $data['weekday'] ?? null,
            'occurrence' => $data['occurrence'] ?? null,
            'easter_offset_days' => $data['easter_offset_days'] ?? null,
            'specific_date' => $data['specific_date'] ?? null,
            'is_active' => true,
        ]));

        return back()->with('success', 'Holiday closure created.');
    }

    public function toggle(OrganizationHoliday $holiday, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $this->ensureOwned($holiday, $organization->getKey());
        $holiday->update(['is_active' => ! $holiday->is_active]);

        return back()->with('success', $holiday->name.($holiday->is_active ? ' enabled.' : ' disabled.'));
    }

    public function destroy(OrganizationHoliday $holiday, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $this->ensureOwned($holiday, $organization->getKey());
        $name = $holiday->name;
        $holiday->delete();

        return back()->with('success', $name.' removed.');
    }

    private function ensureOwned(OrganizationHoliday $holiday, mixed $organizationKey): void
    {
        abort_unless(hash_equals((string) $holiday->organization_id, (string) $organizationKey), 404);
    }
}
