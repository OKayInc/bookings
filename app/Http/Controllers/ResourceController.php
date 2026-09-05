<?php

namespace App\Http\Controllers;

use App\Domain\Availability\HolidayRegionCatalog;
use App\Domain\Money\MoneyService;
use App\Enums\ConditionalResourceFulfillmentMode;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Http\Requests\StoreResourceRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Resource;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResourceController extends Controller
{
    public function index(OrganizationContext $context, HolidayRegionCatalog $holidayRegions): View
    {
        $organization = $context->organization();
        $resources = $organization->resources()
            ->with(['person', 'organization'])
            ->withCount(['appointments', 'bookingHolds', 'confirmations'])
            ->orderBy('name')
            ->get();

        return view('resources.index', [
            'organization' => $organization,
            'resources' => $resources,
            'holidayRegions' => $holidayRegions->options(),
            'resourceHolidaySuggestions' => $resources->mapWithKeys(fn (Resource $resource): array => [
                $resource->uuid => $holidayRegions->detect($resource->timezone ?: $organization->timezone)
                    ?: $organization->holiday_region
                    ?: $holidayRegions->detect($organization->timezone),
            ]),
        ]);
    }

    public function create(OrganizationContext $context, Request $request): View
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);

        return view('resources.create', $this->formData(
            $organization,
            null,
            $this->requestedActiveMemberUuid($request, $organization),
        ));
    }

    public function store(StoreResourceRequest $request, OrganizationContext $context, MoneyService $money): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $data = $request->validated();
        $personKey = $this->personKey($data, $organization);
        $quantityEnabled = $data['type'] === 'equipment' && $request->boolean('quantity_enabled');
        $depositMinor = ($data['default_deposit'] ?? '') === ''
            ? null
            : $money->parse((string) $data['default_deposit'], $organization->currency);

        DB::transaction(function () use ($request, $organization, $data, $personKey, $quantityEnabled, $depositMinor): void {
            $resource = Resource::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $personKey,
                'name' => $data['name'],
                'type' => $data['type'],
                'inventory_quantity' => $quantityEnabled ? (int) $data['inventory_quantity'] : 1,
                'quantity_enabled' => $quantityEnabled,
                'deposit_amount_minor' => $depositMinor,
                'timezone' => $data['timezone'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'is_required_by_default' => ($data['default_requirement'] ?? 'required') === 'required',
            ]);

            $this->syncSharedOrganizations($request, $resource, $organization);
        });

        return redirect()->route('resources.index')->with('success', 'Resource created.');
    }

    public function updateOrganizationSettings(Resource $resource, OrganizationContext $context, Request $request): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        abort_unless($resource->isAvailableToOrganization($organization), 404);

        $data = $request->validate([
            'default_requirement' => ['required', 'in:required,optional'],
            'enforce_holidays' => ['nullable', 'boolean'],
            'holiday_region' => [
                Rule::requiredIf(fn (): bool => $request->boolean('enforce_holidays')),
                'nullable',
                'string',
                Rule::in(array_keys(app(HolidayRegionCatalog::class)->options())),
            ],
        ]);
        $required = $data['default_requirement'] === 'required';
        $enforceHolidays = $request->boolean('enforce_holidays');
        if ($required && $this->conditionalRuleUsesInheritedRequirement($resource, $organization)) {
            return back()->withErrors([
                'default_requirement' => 'This resource must remain optional while an appointment question promotes it conditionally. Change those appointment assignments to explicitly optional before changing the organization default.',
            ]);
        }

        $resource->organizations()->updateExistingPivot($organization->getKey(), [
            'is_required_by_default' => $required,
            'enforce_holidays' => $enforceHolidays,
            'holiday_region' => $data['holiday_region'] ?? null,
            'updated_at' => now(),
        ]);

        $resource->appointmentTypes()
            ->where('appointment_types.organization_id', $organization->getKey())
            ->wherePivot('requirement_mode', 'inherit')
            ->get()
            ->each(fn ($type) => $resource->appointmentTypes()->updateExistingPivot($type->getKey(), [
                'is_required' => $required,
                'updated_at' => now(),
            ]));

        if (hash_equals($resource->organization_id, $organization->getKey())) {
            $resource->forceFill(['is_required_by_default' => $required])->save();
        }

        return back()->with('success', 'Resource settings updated for '.$organization->name.'.');
    }

    public function edit(Resource $resource, OrganizationContext $context): View
    {
        $organization = $context->organization();
        $this->ensureOwned($resource, $organization);
        $this->authorize('manage', $resource);

        return view('resources.edit', $this->formData($organization, $resource));
    }

    public function update(StoreResourceRequest $request, Resource $resource, OrganizationContext $context, MoneyService $money): RedirectResponse
    {
        $organization = $context->organization();
        $this->ensureOwned($resource, $organization);
        $this->authorize('manage', $resource);
        $data = $request->validated();
        $personKey = $this->personKey($data, $organization);
        $defaultRequired = ($data['default_requirement'] ?? 'required') === 'required';
        $quantityEnabled = $data['type'] === 'equipment' && $request->boolean('quantity_enabled');
        $depositMinor = ($data['default_deposit'] ?? '') === ''
            ? null
            : $money->parse((string) $data['default_deposit'], $organization->currency);
        if ($defaultRequired && $this->conditionalRuleUsesInheritedRequirement($resource, $organization)) {
            return back()->withErrors([
                'default_requirement' => 'This resource must remain optional while an appointment question promotes it conditionally. Change those appointment assignments to explicitly optional before changing the organization default.',
            ]);
        }
        if ($quantityEnabled && $resource->conditionalRequirementRules()
            ->where('fulfillment_mode', ConditionalResourceFulfillmentMode::OneOf->value)->exists()) {
            return back()->withErrors([
                'quantity_enabled' => 'Quantity tracking cannot be enabled while this resource belongs to a one-of-N conditional group.',
            ]);
        }

        DB::transaction(function () use ($request, $resource, $organization, $data, $personKey, $defaultRequired, $quantityEnabled, $depositMinor): void {
            $resource->update([
                'person_id' => $personKey,
                'name' => $data['name'],
                'type' => $data['type'],
                'inventory_quantity' => $quantityEnabled ? (int) $data['inventory_quantity'] : 1,
                'quantity_enabled' => $quantityEnabled,
                'deposit_amount_minor' => $depositMinor,
                'timezone' => $data['timezone'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'is_required_by_default' => $defaultRequired,
            ]);

            $resource->organizations()->updateExistingPivot($organization->getKey(), [
                'is_required_by_default' => $defaultRequired,
                'enforce_holidays' => $request->boolean('enforce_holidays'),
                'holiday_region' => $data['holiday_region'] ?? null,
                'updated_at' => now(),
            ]);
            $this->syncSharedOrganizations($request, $resource, $organization);

            $resource->appointmentTypes()
                ->where('appointment_types.organization_id', $organization->getKey())
                ->wherePivot('requirement_mode', 'inherit')
                ->get()
                ->each(fn ($type) => $resource->appointmentTypes()->updateExistingPivot($type->getKey(), [
                    'is_required' => $defaultRequired,
                    'updated_at' => now(),
                ]));
        });

        return redirect()->route('resources.index')->with('success', 'Resource updated.');
    }

    public function destroy(Resource $resource, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->ensureOwned($resource, $organization);
        $this->authorize('manage', $resource);

        return DB::transaction(function () use ($resource): RedirectResponse {
            $locked = Resource::query()->whereKey($resource->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->appointments()->exists()
                || $locked->bookingHolds()->exists()
                || $locked->confirmations()->exists()) {
                return redirect()->route('resources.index')->withErrors([
                    'resource' => 'This resource cannot be deleted because it has booking or appointment history. Disable it instead.',
                ]);
            }
            if ($locked->conditionalRequirementRules()->exists()) {
                return redirect()->route('resources.index')->withErrors([
                    'resource' => 'Remove this resource from its conditional questionnaire group before deleting it.',
                ]);
            }

            // Availability schedules use a polymorphic binary scope without a database
            // foreign key, so remove them explicitly. Other resource configuration is
            // protected by cascading foreign keys.
            $locked->availabilitySchedules()->delete();
            $locked->delete();

            return redirect()->route('resources.index')->with('success', 'Unused resource deleted.');
        });
    }

    private function formData(
        Organization $organization,
        ?Resource $resource,
        ?string $preselectedPersonUuid = null,
    ): array
    {
        $regions = app(HolidayRegionCatalog::class);
        $settings = $resource?->holidaySettingsForOrganization($organization) ?? ['enforce' => false, 'region' => null];
        $suggestedRegion = $regions->detect($resource?->timezone ?: $organization->timezone)
            ?: $organization->holiday_region
            ?: $regions->detect($organization->timezone);

        return [
            'organization' => $organization,
            'resource' => $resource,
            'members' => $organization->people()
                ->wherePivot('status', MembershipStatus::Active->value)
                ->with('user')
                ->orderBy('first_name')
                ->get(),
            'selectedPersonUuid' => $resource?->person?->uuid ?: $preselectedPersonUuid,
            'pendingMemberInvitations' => $organization->memberInvitations()
                ->whereNull('accepted_at_utc')
                ->whereNull('revoked_at_utc')
                ->where('expires_at_utc', '>', now('UTC'))
                ->orderBy('email_normalized')
                ->get(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'holidayRegions' => $regions->options(),
            'resourceHolidayEnforced' => $settings['enforce'],
            'resourceHolidayRegion' => $settings['region'] ?: $suggestedRegion,
            'suggestedHolidayRegion' => $suggestedRegion,
            'shareableOrganizations' => $this->ownedOrganizations($organization),
            'sharedOrganizationKeys' => $resource
                ? $resource->organizations()->where('organizations.id', '!=', $organization->getKey())->pluck('organizations.id')->all()
                : [],
        ];
    }

    private function conditionalRuleUsesInheritedRequirement(Resource $resource, Organization $organization): bool
    {
        return $resource->conditionalRequirementRules()
            ->with('question.appointmentType')
            ->get()
            ->contains(function ($rule) use ($resource, $organization): bool {
                $type = $rule->question?->appointmentType;
                if ($type === null || ! hash_equals($type->organization_id, $organization->getKey())) {
                    return false;
                }

                return $resource->appointmentTypes()
                    ->whereKey($type->getKey())
                    ->wherePivot('requirement_mode', 'inherit')
                    ->exists();
            });
    }

    private function requestedActiveMemberUuid(Request $request, Organization $organization): ?string
    {
        $uuid = trim((string) $request->query('person', ''));
        if ($uuid === '') {
            return null;
        }

        abort_unless(Str::isUuid($uuid), 404);
        $person = Person::whereUuid($uuid)->firstOrFail();
        abort_unless(
            $organization->memberships()
                ->where('person_id', $person->getKey())
                ->where('status', MembershipStatus::Active->value)
                ->exists(),
            404,
        );

        return $person->uuid;
    }

    private function ownedOrganizations(Organization $current): \Illuminate\Support\Collection
    {
        if (! $this->userOwnsOrganization($current)) {
            return collect();
        }

        return auth()->user()->person->organizations()
            ->wherePivot('status', MembershipStatus::Active->value)
            ->wherePivot('role', MembershipRole::Owner->value)
            ->where('organizations.id', '!=', $current->getKey())
            ->orderBy('name')
            ->get();
    }

    private function userOwnsOrganization(Organization $organization): bool
    {
        return $organization->memberships()
            ->where('person_id', auth()->user()->person_id)
            ->where('status', MembershipStatus::Active->value)
            ->where('role', MembershipRole::Owner->value)
            ->exists();
    }

    private function syncSharedOrganizations(StoreResourceRequest $request, Resource $resource, Organization $owner): void
    {
        $ownerSettings = [
            'is_required_by_default' => (bool) $resource->is_required_by_default,
            'enforce_holidays' => $request->boolean('enforce_holidays'),
            'holiday_region' => $request->input('holiday_region') ?: null,
        ];

        if (! $this->userOwnsOrganization($owner)) {
            $resource->organizations()->updateExistingPivot($owner->getKey(), array_merge($ownerSettings, ['updated_at' => now()]));

            return;
        }

        $allowed = $this->ownedOrganizations($owner)->keyBy('uuid');
        $requested = collect((array) $request->input('shared_organization_uuids', []))
            ->filter(fn ($uuid) => is_string($uuid) && $allowed->has($uuid));

        $sync = [$owner->getKey() => $ownerSettings];
        foreach ($requested as $uuid) {
            $organization = $allowed->get($uuid);
            $existing = $resource->organizations()->whereKey($organization->getKey())->first();
            $sync[$organization->getKey()] = [
                'is_required_by_default' => (bool) ($existing?->pivot?->is_required_by_default ?? $resource->is_required_by_default),
                'enforce_holidays' => (bool) ($existing?->pivot?->enforce_holidays ?? false),
                'holiday_region' => $existing?->pivot?->holiday_region,
            ];
        }

        $resource->organizations()->sync($sync);
    }

    private function personKey(array $data, Organization $organization): mixed
    {
        if (empty($data['person_uuid'])) {
            return null;
        }

        $person = Person::whereUuid($data['person_uuid'])->firstOrFail();
        abort_unless(
            $organization->memberships()->where('person_id', $person->getKey())->where('status', MembershipStatus::Active->value)->exists(),
            422,
            'The selected person is not an active member of this organization.'
        );

        return $person->getKey();
    }

    private function ensureOwned(Resource $resource, Organization $organization): void
    {
        abort_unless(hash_equals($resource->organization_id, $organization->getKey()), 404);
    }
}
