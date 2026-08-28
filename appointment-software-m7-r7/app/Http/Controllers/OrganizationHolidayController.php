<?php

namespace App\Http\Controllers;

use App\Domain\Availability\HolidayRegionCatalog;
use App\Domain\Availability\OrganizationHolidayPresetCatalog;
use App\Domain\Availability\OrganizationHolidayService;
use App\Domain\Availability\PublicHolidayCalendar;
use App\Enums\HolidayRuleType;
use App\Http\Requests\StoreOrganizationHolidayRequest;
use App\Models\OrganizationHoliday;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationHolidayController extends Controller
{
    public function index(
        OrganizationContext $context,
        OrganizationHolidayService $service,
        HolidayRegionCatalog $regions,
        PublicHolidayCalendar $publicHolidays,
    ): View {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $holidays = $organization->holidays()->orderByDesc('is_active')->orderBy('name')->get();
        $suggestedRegion = $regions->detect($organization->timezone);
        $selectedRegion = $organization->holiday_region ?: $suggestedRegion;
        $year = (int) now($organization->timezone)->format('Y');
        $regionalPresets = $selectedRegion === null
            ? []
            : $this->unconfiguredRegionalPresets(
                $publicHolidays->available($selectedRegion, $year, 3),
                $holidays,
                $service,
                $year,
                $organization->timezone,
            );

        return view('availability.holidays.index', [
            'organization' => $organization,
            'holidays' => $holidays,
            'regionOptions' => $regions->options(),
            'selectedRegion' => $selectedRegion,
            'suggestedRegion' => $suggestedRegion,
            'regionalPresets' => $regionalPresets,
            'ruleTypes' => array_values(array_filter(
                HolidayRuleType::cases(),
                fn (HolidayRuleType $type): bool => $type !== HolidayRuleType::RegionalCalendar,
            )),
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
        OrganizationHolidayService $service,
        PublicHolidayCalendar $publicHolidays,
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
            'region_code' => null,
            'provider_holiday_key' => null,
        ];

        if (! empty($data['region_code']) && ! empty($data['provider_holiday_key'])) {
            $year = (int) now($organization->timezone)->format('Y');
            $definition = $publicHolidays->definition($data['region_code'], $data['provider_holiday_key'], $year);
            abort_unless($definition !== null, 422, 'That holiday is not available for the selected country or region.');
            if ($organization->holiday_region !== $data['region_code']) {
                $organization->update(['holiday_region' => $data['region_code']]);
            }

            $presetKey = $publicHolidays->presetKey($data['region_code'], $data['provider_holiday_key']);
            $existing = $organization->holidays()->where('preset_key', $presetKey)->first();
            $equivalent = $existing ?? $this->equivalentHoliday(
                $organization->holidays()->get(),
                $definition['dates'],
                $service,
                $year,
                $organization->timezone,
            );

            if ($equivalent !== null) {
                $equivalent->update(['is_active' => true]);

                return back()->with('success', $equivalent->name.' was already configured and is now enabled.');
            }

            $organization->holidays()->create(array_merge($emptyRule, [
                'preset_key' => $presetKey,
                'region_code' => $data['region_code'],
                'provider_holiday_key' => $data['provider_holiday_key'],
                'name' => $definition['name'],
                'rule_type' => HolidayRuleType::RegionalCalendar->value,
                'is_active' => true,
            ]));

            return back()->with('success', $definition['name'].' is now an organization-wide closure.');
        }

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

    public function updateRegion(
        Request $request,
        OrganizationContext $context,
        HolidayRegionCatalog $regions,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $data = $request->validate([
            'holiday_region' => ['required', 'string', Rule::in(array_keys($regions->options()))],
        ]);
        $organization->update(['holiday_region' => $data['holiday_region']]);

        return back()->with('success', 'Holiday country or region updated.');
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

    /**
     * @param array<string, array{key:string,preset_key:string,name:string,type:string,dates:array<int,string>}> $presets
     * @return array<string, array{key:string,preset_key:string,name:string,type:string,dates:array<int,string>}>
     */
    private function unconfiguredRegionalPresets(
        array $presets,
        Collection $holidays,
        OrganizationHolidayService $service,
        int $year,
        string $timezone,
    ): array {
        $configuredPresetKeys = $holidays->pluck('preset_key')->filter()->flip();

        return array_filter($presets, function (array $preset) use ($configuredPresetKeys, $holidays, $service, $year, $timezone): bool {
            if ($configuredPresetKeys->has($preset['preset_key'])) {
                return false;
            }

            return $this->equivalentHoliday($holidays, $preset['dates'], $service, $year, $timezone) === null;
        });
    }

    /** @param array<int, string> $dates */
    private function equivalentHoliday(
        Collection $holidays,
        array $dates,
        OrganizationHolidayService $service,
        int $year,
        string $timezone,
    ): ?OrganizationHoliday {
        $candidate = [];
        for ($candidateYear = $year; $candidateYear <= $year + 2; $candidateYear++) {
            $candidate[$candidateYear] = $dates[$candidateYear] ?? null;
        }

        if (collect($candidate)->filter()->isEmpty()) {
            return null;
        }

        return $holidays->first(function (OrganizationHoliday $holiday) use ($candidate, $service, $year, $timezone): bool {
            $configured = [];
            for ($candidateYear = $year; $candidateYear <= $year + 2; $candidateYear++) {
                $configured[$candidateYear] = $service->dateForYear($holiday, $candidateYear, $timezone)?->format('Y-m-d');
            }

            return $configured === $candidate;
        });
    }
}
