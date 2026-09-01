<?php

namespace App\Http\Controllers;

use App\Domain\Appointments\AppointmentTypeDeletionService;
use App\Domain\Appointments\AppointmentTypeLogoService;
use App\Domain\Appointments\AppointmentTypeSummaryService;
use App\Domain\Appointments\AttendeePricingService;
use App\Domain\Contracts\ContractTemplateService;
use App\Domain\Bookings\ShortNoticeFeeRuleService;
use App\Domain\Conferences\ConferenceProviderCatalog;
use App\Domain\Money\MoneyService;
use App\Domain\Questionnaires\PercentageService;
use App\Enums\AppointmentVisibility;
use App\Enums\AttendanceMode;
use App\Enums\AttendeePricingMode;
use App\Enums\BookingNoticeUnit;
use App\Enums\DurationMode;
use App\Enums\DurationUnit;
use App\Enums\EmailVerificationMode;
use App\Enums\PricingMode;
use App\Enums\PricingAdjustmentType;
use App\Enums\ResourceRequirementMode;
use App\Enums\ReminderThresholdBasis;
use App\Enums\SeasonRecurrence;
use App\Enums\TicketSeatingScheme;
use App\Domain\Tickets\TicketSeatingService;
use App\Http\Requests\StoreAppointmentTypeRequest;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\Resource;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AppointmentTypeController extends Controller
{
    public function index(OrganizationContext $context, AppointmentTypeSummaryService $summary): View
    {
        $appointmentTypes = $context->organization()->appointmentTypes()
            ->with('organization')
            ->withCount(['resources', 'bookings'])
            ->withExists(['contractTemplate as has_contract'])
            ->orderBy('name')
            ->get();

        return view('appointment-types.index', compact('appointmentTypes', 'summary'));
    }

    public function create(OrganizationContext $context): View
    {
        $this->authorize('manageScheduling', $context->organization());

        return view('appointment-types.create', array_merge($this->formData($context), [
            'fixedPriceInput' => '',
            'attendeePriceInput' => '',
            'attendeePriceRangeInputs' => [],
            'rateAmountInput' => '',
            'shortNoticeFeeInputs' => [],
            'ticketSeatBlockInputs' => [],
        ]));
    }

    public function store(
        StoreAppointmentTypeRequest $request,
        OrganizationContext $context,
        ContractTemplateService $contracts,
        AppointmentTypeLogoService $logos,
        MoneyService $money,
        ShortNoticeFeeRuleService $shortNoticeFees,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $data = $request->validated();
        $slug = $this->uniqueSlug($organization->getKey(), $data['slug'] ?? $data['name']);

        $appointmentType = $organization->appointmentTypes()->create(array_merge(
            [
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'visibility' => $data['visibility'],
                'access_password' => $data['visibility'] === AppointmentVisibility::PasswordProtected->value
                    ? Hash::make($data['access_password'])
                    : null,
                'public_token' => $data['visibility'] === AppointmentVisibility::Unlisted->value
                    ? Str::random(48)
                    : null,
                'is_active' => $request->boolean('is_active', true),
            ],
            $this->configurationData($data, $organization->currency, $request, $money),
        ));

        $appointmentType->resources()->sync($this->resourceSyncData(
            $data['resource_uuids'] ?? [],
            $data['resource_requirement_modes'] ?? [],
            $data['resource_replacement_groups'] ?? [],
            $organization->getKey(),
        ));
        $shortNoticeFees->sync($appointmentType, $data['short_notice_fees'] ?? [], $organization->currency);

        if ($request->hasFile('logo_file')) {
            $logos->replace($appointmentType, $request->file('logo_file'));
        }

        if ($request->hasFile('contract_file')) {
            $contracts->replace($appointmentType, $request->file('contract_file'), $request->user()->person);
        }

        return redirect()->route('appointment-types.index')->with('success', 'Appointment type created.');
    }

    public function edit(
        AppointmentType $appointmentType,
        OrganizationContext $context,
        MoneyService $money,
        PercentageService $percentages,
    ): View
    {
        $this->ensureSameOrganization($appointmentType, $context);
        $this->authorize('manage', $appointmentType);
        $appointmentType->loadCount('bookings');
        $appointmentType->load([
            'organization',
            'resources',
            'shortNoticeFeeRules',
            'contractTemplate',
            'invitations' => fn ($query) => $query->latest()->limit(50),
        ]);

        return view('appointment-types.edit', array_merge(
            $this->formData($context),
            [
                'appointmentType' => $appointmentType,
                'fixedPriceInput' => $appointmentType->fixed_price_minor === null
                    ? ''
                    : $money->decimal((int) $appointmentType->fixed_price_minor, $context->organization()->currency),
                'rateAmountInput' => $appointmentType->rate_amount_minor === null
                    ? ''
                    : $money->decimal((int) $appointmentType->rate_amount_minor, $context->organization()->currency),
                'attendeePriceInput' => $appointmentType->attendee_price_minor === null
                    ? ''
                    : $money->decimal((int) $appointmentType->attendee_price_minor, $context->organization()->currency),
                'attendeePriceRangeInputs' => array_map(fn (array $range): array => [
                    'min_attendees' => $range['min_attendees'],
                    'max_attendees' => $range['max_attendees'],
                    'unit_price' => $money->decimal($range['unit_amount_minor'], $context->organization()->currency),
                ], $appointmentType->attendee_price_ranges ?? []),
                'shortNoticeFeeInputs' => $appointmentType->shortNoticeFeeRules->map(fn ($rule): array => [
                    'threshold_value' => $rule->threshold_value,
                    'threshold_unit' => $rule->threshold_unit->value,
                    'adjustment_type' => $rule->adjustment_type->value,
                    'fixed_amount' => $rule->fixed_amount_minor === null
                        ? ''
                        : $money->decimal((int) $rule->fixed_amount_minor, $context->organization()->currency),
                    'percentage' => $percentages->display($rule->percentage_bps),
                ])->values()->all(),
                'ticketSeatBlockInputs' => $appointmentType->ticket_seat_blocks ?? [],
            ],
        ));
    }

    public function update(
        StoreAppointmentTypeRequest $request,
        AppointmentType $appointmentType,
        OrganizationContext $context,
        ContractTemplateService $contracts,
        AppointmentTypeLogoService $logos,
        MoneyService $money,
        ShortNoticeFeeRuleService $shortNoticeFees,
    ): RedirectResponse {
        $this->ensureSameOrganization($appointmentType, $context);
        $this->authorize('manage', $appointmentType);
        $data = $request->validated();
        $previousVisibility = $appointmentType->visibility;

        $password = $appointmentType->access_password;
        if ($data['visibility'] !== AppointmentVisibility::PasswordProtected->value) {
            $password = null;
        } elseif (! empty($data['access_password'])) {
            $password = Hash::make($data['access_password']);
        }

        $appointmentType->update(array_merge(
            [
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($context->organization()->getKey(), $data['slug'] ?? $data['name'], $appointmentType),
                'description' => $data['description'] ?? null,
                'visibility' => $data['visibility'],
                'access_password' => $password,
                'public_token' => $data['visibility'] === AppointmentVisibility::Unlisted->value
                    ? ($appointmentType->public_token ?: Str::random(48))
                    : null,
                'is_active' => $request->boolean('is_active'),
            ],
            $this->configurationData($data, $context->organization()->currency, $request, $money),
        ));

        $appointmentType->resources()->sync($this->resourceSyncData(
            $data['resource_uuids'] ?? [],
            $data['resource_requirement_modes'] ?? [],
            $data['resource_replacement_groups'] ?? [],
            $context->organization()->getKey(),
        ));
        $shortNoticeFees->sync(
            $appointmentType,
            $data['short_notice_fees'] ?? [],
            $context->organization()->currency,
        );

        if ($previousVisibility === AppointmentVisibility::InviteOnly && $appointmentType->visibility !== AppointmentVisibility::InviteOnly) {
            $appointmentType->invitations()->where('is_active', true)->update(['is_active' => false, 'updated_at' => now()]);
        }

        if ($request->hasFile('logo_file')) {
            $logos->replace($appointmentType, $request->file('logo_file'));
        } elseif ($request->boolean('remove_logo')) {
            $logos->remove($appointmentType);
        }

        if ($request->boolean('remove_contract')) {
            $contracts->remove($appointmentType);
        }

        if ($request->hasFile('contract_file')) {
            $contracts->replace($appointmentType, $request->file('contract_file'), $request->user()->person);
        }

        return redirect()->route('appointment-types.index')->with('success', 'Appointment type updated.');
    }


    public function disable(AppointmentType $appointmentType, OrganizationContext $context): RedirectResponse
    {
        $this->ensureSameOrganization($appointmentType, $context);
        $this->authorize('manage', $appointmentType);

        if (! $appointmentType->is_active) {
            return redirect()->route('appointment-types.index')->with('success', 'Appointment type is already disabled.');
        }

        $appointmentType->forceFill(['is_active' => false])->save();

        // A hold is not yet a booking. Once the type is disabled, prevent an already
        // open guest browser from completing a new booking through an old hold.
        $appointmentType->bookingHolds()
            ->where('status', \App\Enums\BookingHoldStatus::Active->value)
            ->update([
                'status' => \App\Enums\BookingHoldStatus::Released->value,
                'updated_at' => now(),
            ]);

        return redirect()->route('appointment-types.index')->with('success', 'Appointment type disabled. Existing bookings were preserved.');
    }

    public function destroy(
        AppointmentType $appointmentType,
        OrganizationContext $context,
        AppointmentTypeDeletionService $deletion,
    ): RedirectResponse {
        $this->ensureSameOrganization($appointmentType, $context);
        $this->authorize('manage', $appointmentType);

        if (! $deletion->deleteIfUnused($appointmentType)) {
            return redirect()->route('appointment-types.index')->with(
                'error',
                'This appointment type has booking history and cannot be deleted. Disable it instead.',
            );
        }

        return redirect()->route('appointment-types.index')->with('success', 'Appointment type permanently deleted.');
    }

    private function formData(OrganizationContext $context): array
    {
        return [
            'appointmentType' => null,
            'resources' => $context->organization()->resources()->where('resources.is_active', true)->orderBy('name')->get(),
            'visibilities' => AppointmentVisibility::cases(),
            'attendanceModes' => AttendanceMode::cases(),
            'meetingProviders' => app(ConferenceProviderCatalog::class)->options($context->organization()),
            'durationModes' => DurationMode::cases(),
            'durationUnits' => DurationUnit::cases(),
            'bookingNoticeUnits' => BookingNoticeUnit::cases(),
            'emailVerificationModes' => EmailVerificationMode::cases(),
            'pricingModes' => PricingMode::cases(),
            'attendeePricingModes' => AttendeePricingMode::cases(),
            'shortNoticeAdjustmentTypes' => [PricingAdjustmentType::Fixed, PricingAdjustmentType::Percentage],
            'resourceRequirementModes' => ResourceRequirementMode::cases(),
            'reminderThresholdBases' => ReminderThresholdBasis::cases(),
            'seasonRecurrences' => SeasonRecurrence::cases(),
            'ticketSeatingSchemes' => TicketSeatingScheme::cases(),
            'organization' => $context->organization(),
        ];
    }

    private function configurationData(array $data, string $currency, StoreAppointmentTypeRequest $request, MoneyService $money): array
    {
        $isFixedDuration = $data['duration_mode'] === DurationMode::Fixed->value;
        $isFixedPrice = $data['pricing_mode'] === PricingMode::Fixed->value;
        $isRatePrice = $data['pricing_mode'] === PricingMode::Rate->value;
        $isPerAttendee = $data['pricing_mode'] === PricingMode::PerAttendee->value;
        $attendeeMode = $isPerAttendee ? AttendeePricingMode::from($data['attendee_pricing_mode']) : AttendeePricingMode::Flat;
        $attendeeRanges = $isPerAttendee && $attendeeMode !== AttendeePricingMode::Flat
            ? app(AttendeePricingService::class)->validateRanges(array_map(fn (array $range): array => [
                'min_attendees' => (int) $range['min_attendees'],
                'max_attendees' => (int) $range['max_attendees'],
                'unit_amount_minor' => $money->parse($range['unit_price'], $currency),
            ], $data['attendee_price_ranges']), (int) $data['capacity'])
            : null;
        $ticketingEnabled = $request->boolean('ticketing_enabled');
        $ticketScheme = $ticketingEnabled
            ? TicketSeatingScheme::from($data['ticket_seating_scheme'])
            : TicketSeatingScheme::None;
        $ticketSeatOptional = $ticketingEnabled
            && $ticketScheme->supportsOptionalSeat()
            && $request->boolean('ticket_seat_optional');
        $ticketSeatBlocks = $ticketingEnabled
            ? app(TicketSeatingService::class)->normalize(
                $ticketScheme,
                $ticketSeatOptional,
                $data['ticket_seat_blocks'] ?? [],
                (int) $data['capacity'],
            )
            : null;

        return [
            'attendance_mode' => $data['attendance_mode'],
            'ticketing_enabled' => $ticketingEnabled,
            'show_start_offset_minutes' => $ticketingEnabled ? (int) $data['show_start_offset_minutes'] : null,
            'show_end_offset_minutes' => $ticketingEnabled && ($data['show_end_offset_minutes'] ?? '') !== ''
                ? (int) $data['show_end_offset_minutes']
                : null,
            'ticket_seating_scheme' => $ticketScheme->value,
            'ticket_seat_optional' => $ticketSeatOptional,
            'ticket_seat_blocks' => $ticketSeatBlocks,
            'is_online' => $request->boolean('is_online'),
            'meeting_provider' => $request->boolean('is_online') ? $data['meeting_provider'] : null,
            'capacity' => $data['attendance_mode'] === AttendanceMode::Single->value ? 1 : (int) $data['capacity'],
            'duration_mode' => $data['duration_mode'],
            'duration_unit' => $data['duration_unit'],
            'duration_value' => $isFixedDuration ? (int) $data['duration_value'] : 1,
            'minimum_duration_value' => $isFixedDuration ? null : (int) $data['minimum_duration_value'],
            'maximum_duration_value' => $isFixedDuration ? null : (int) $data['maximum_duration_value'],
            'duration_increment_value' => $isFixedDuration ? null : (int) $data['duration_increment_value'],
            'start_interval_minutes' => (int) ($data['start_interval_minutes'] ?? config('availability.default_start_interval_minutes', 15)),
            'booking_notice_value' => (int) ($data['booking_notice_value'] ?? 0),
            'booking_notice_unit' => $data['booking_notice_unit'] ?? BookingNoticeUnit::Hour->value,
            'maximum_booking_notice_value' => (int) ($data['maximum_booking_notice_value'] ?? 365),
            'maximum_booking_notice_unit' => $data['maximum_booking_notice_unit'] ?? BookingNoticeUnit::Day->value,
            'seasonal_availability_enabled' => $request->boolean('seasonal_availability_enabled'),
            'season_start_date' => $request->boolean('seasonal_availability_enabled') ? $data['season_start_date'] : null,
            'season_end_date' => $request->boolean('seasonal_availability_enabled') ? $data['season_end_date'] : null,
            'season_recurrence' => $request->boolean('seasonal_availability_enabled') ? $data['season_recurrence'] : null,
            'buffer_before_minutes' => (int) $data['buffer_before_minutes'],
            'buffer_after_minutes' => (int) $data['buffer_after_minutes'],
            'pricing_mode' => $data['pricing_mode'],
            'fixed_price_minor' => $isFixedPrice ? $money->parse($data['fixed_price'], $currency) : null,
            'attendee_price_minor' => $isPerAttendee && $attendeeMode === AttendeePricingMode::Flat
                ? $money->parse($data['attendee_price'], $currency)
                : null,
            'attendee_pricing_mode' => $attendeeMode,
            'attendee_price_ranges' => $attendeeRanges,
            'rate_amount_minor' => $isRatePrice ? $money->parse($data['rate_amount'], $currency) : null,
            'rate_unit' => $isRatePrice ? $data['rate_unit'] : null,
            'requires_resource_confirmation' => $request->boolean('requires_resource_confirmation'),
            'email_verification_mode' => $data['email_verification_mode'] ?? EmailVerificationMode::BeforeConfirmation->value,
            'cancellation_allowed' => $request->has('cancellation_allowed') ? $request->boolean('cancellation_allowed') : true,
            'cancellation_notice_value' => (int) ($data['cancellation_notice_value'] ?? 24),
            'cancellation_notice_unit' => $data['cancellation_notice_unit'] ?? BookingNoticeUnit::Hour->value,
            'cancellation_policy_text' => $data['cancellation_policy_text'] ?? null,
            'rescheduling_allowed' => $request->has('rescheduling_allowed') ? $request->boolean('rescheduling_allowed') : true,
            'rescheduling_notice_value' => (int) ($data['rescheduling_notice_value'] ?? 24),
            'rescheduling_notice_unit' => $data['rescheduling_notice_unit'] ?? BookingNoticeUnit::Hour->value,
            'rescheduling_max_count' => (int) ($data['rescheduling_max_count'] ?? 0),
            'rescheduling_policy_text' => $data['rescheduling_policy_text'] ?? null,
            'reminder_enabled' => $request->boolean('reminder_enabled'),
            'reminder_threshold_basis' => $data['reminder_threshold_basis'] ?? ReminderThresholdBasis::LeadTime->value,
            'reminder_threshold_days' => (int) ($data['reminder_threshold_days'] ?? 7),
            'reminder_before_value' => (int) ($data['reminder_before_value'] ?? 1),
            'reminder_before_unit' => $data['reminder_before_unit'] ?? BookingNoticeUnit::Day->value,
            'reminder_clients' => $request->has('reminder_enabled') ? $request->boolean('reminder_clients') : true,
            'reminder_resources' => $request->has('reminder_enabled') ? $request->boolean('reminder_resources') : true,
            'redirect_url' => $data['redirect_url'] ?? null,
        ];
    }

    private function uniqueSlug(string $organizationKey, string $source, ?AppointmentType $ignore = null): string
    {
        $base = Str::slug($source) ?: 'appointment';
        $slug = $base;
        $counter = 2;

        while (AppointmentType::where('organization_id', $organizationKey)
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->getKey()))
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function resourceSyncData(array $uuids, array $modes, array $groups, string $organizationKey): array
    {
        $sync = [];
        $organization = Organization::query()->findOrFail($organizationKey);
        $canonicalGroupNames = [];

        foreach ($uuids as $uuid) {
            $mode = ResourceRequirementMode::tryFrom((string) ($modes[$uuid] ?? ResourceRequirementMode::Inherit->value));
            if ($mode !== ResourceRequirementMode::Replacement) {
                continue;
            }

            $name = Str::squish((string) ($groups[$uuid] ?? ''));
            if ($name !== '') {
                $canonicalGroupNames[Str::lower($name)] ??= $name;
            }
        }

        foreach ($uuids as $uuid) {
            $resource = Resource::whereUuid((string) $uuid)
                ->whereHas('organizations', fn ($query) => $query->where('organizations.id', $organizationKey))
                ->firstOrFail();
            $defaultRequired = $resource->defaultRequiredForOrganization($organization);
            $mode = ResourceRequirementMode::tryFrom((string) ($modes[$uuid] ?? ResourceRequirementMode::Inherit->value))
                ?? ResourceRequirementMode::Inherit;
            $effectiveRequired = match ($mode) {
                ResourceRequirementMode::Required => true,
                ResourceRequirementMode::Replacement => true,
                ResourceRequirementMode::Optional => false,
                ResourceRequirementMode::Inherit => $defaultRequired,
            };
            $replacementGroup = null;
            if ($mode === ResourceRequirementMode::Replacement) {
                $submittedName = Str::squish((string) ($groups[$uuid] ?? ''));
                $replacementGroup = $canonicalGroupNames[Str::lower($submittedName)] ?? null;
            }

            $sync[$resource->getKey()] = [
                'requirement_mode' => $mode->value,
                'is_required' => $effectiveRequired,
                'replacement_group' => $replacementGroup,
            ];
        }

        return $sync;
    }

    private function ensureSameOrganization(AppointmentType $appointmentType, OrganizationContext $context): void
    {
        abort_unless(hash_equals($appointmentType->organization_id, $context->organization()->getKey()), 404);
    }
}
