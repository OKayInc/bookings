<?php

namespace App\Http\Requests;

use App\Domain\Appointments\AttendeePricingService;
use App\Enums\AttendeePricingMode;
use App\Enums\AppointmentVisibility;
use App\Enums\AttendanceMode;
use App\Enums\BookingNoticeUnit;
use App\Enums\DurationMode;
use App\Enums\DurationUnit;
use App\Enums\EmailVerificationMode;
use App\Enums\ConferenceProvider;
use App\Enums\EquipmentPricingMode;
use App\Enums\PricingMode;
use App\Enums\PaymentCollectionMode;
use App\Enums\RetainerType;
use App\Enums\PricingAdjustmentType;
use App\Enums\ReminderThresholdBasis;
use App\Enums\ResourceRequirementMode;
use App\Enums\SeasonRecurrence;
use App\Enums\TicketSeatingScheme;
use App\Models\Resource;
use App\Models\AppointmentType;
use App\Domain\Money\MoneyService;
use App\Domain\Conferences\ConferenceProviderCatalog;
use App\Domain\Questionnaires\PercentageService;
use App\Domain\Tickets\TicketSeatingService;
use App\Rules\MoneyAmount;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StoreAppointmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $currency = app(OrganizationContext::class)->organization()->currency;
        $usesAttendeeRanges = fn (): bool => $this->input('pricing_mode') === PricingMode::PerAttendee->value
            && in_array($this->input('attendee_pricing_mode'), [AttendeePricingMode::Absolute->value, AttendeePricingMode::Accumulative->value], true);

        return [
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'alpha_dash:ascii', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'visibility' => ['required', Rule::enum(AppointmentVisibility::class)],
            'access_password' => [
                Rule::requiredIf(function (): bool {
                    if ($this->input('visibility') !== AppointmentVisibility::PasswordProtected->value) {
                        return false;
                    }

                    $appointmentType = $this->route('appointment_type');

                    return ! $appointmentType || empty($appointmentType->access_password);
                }),
                'nullable', 'string', 'min:8', 'max:200',
            ],

            'attendance_mode' => ['required', Rule::enum(AttendanceMode::class)],
            'ticketing_enabled' => ['nullable', 'boolean'],
            'show_start_offset_minutes' => [
                Rule::requiredIf(fn (): bool => $this->boolean('ticketing_enabled')),
                'nullable', 'integer', 'min:0',
            ],
            'show_end_offset_minutes' => ['nullable', 'integer', 'min:0'],
            'ticket_seating_scheme' => [
                Rule::requiredIf(fn (): bool => $this->boolean('ticketing_enabled')),
                'nullable', Rule::enum(TicketSeatingScheme::class),
            ],
            'ticket_seat_optional' => ['nullable', 'boolean'],
            'ticket_seat_blocks' => [
                Rule::requiredIf(function (): bool {
                    $scheme = TicketSeatingScheme::tryFrom((string) $this->input('ticket_seating_scheme'));

                    return $this->boolean('ticketing_enabled') && $scheme?->usesBlocks();
                }),
                'nullable', 'array', 'max:1000',
            ],
            'ticket_seat_blocks.*.section' => ['nullable', 'string', 'max:80'],
            'ticket_seat_blocks.*.row' => ['nullable', 'string', 'max:80'],
            'ticket_seat_blocks.*.first_seat' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'ticket_seat_blocks.*.last_seat' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'ticket_seat_blocks.*.quantity' => ['nullable', 'integer', 'min:1', 'max:'.config('appointment-types.max_capacity', 100000)],
            'ticket_seat_blocks.*.seat_fee' => ['nullable', new MoneyAmount($currency)],
            'is_online' => ['nullable', 'boolean'],
            'meeting_provider' => [
                Rule::requiredIf(fn (): bool => $this->boolean('is_online')),
                'nullable', Rule::enum(ConferenceProvider::class),
            ],
            'capacity' => [
                Rule::requiredIf(fn (): bool => $this->input('attendance_mode') === AttendanceMode::Group->value),
                'nullable', 'integer', 'min:2', 'max:'.config('appointment-types.max_capacity', 100000),
            ],

            'duration_mode' => ['required', Rule::enum(DurationMode::class)],
            'duration_unit' => ['required', Rule::enum(DurationUnit::class)],
            'duration_value' => [
                Rule::requiredIf(fn (): bool => $this->input('duration_mode') === DurationMode::Fixed->value),
                'nullable', 'integer', 'min:1',
            ],
            'minimum_duration_value' => [
                Rule::requiredIf(fn (): bool => $this->input('duration_mode') === DurationMode::Variable->value),
                'nullable', 'integer', 'min:1',
            ],
            'maximum_duration_value' => [
                Rule::requiredIf(fn (): bool => $this->input('duration_mode') === DurationMode::Variable->value),
                'nullable', 'integer', 'min:1',
            ],
            'duration_increment_value' => [
                Rule::requiredIf(fn (): bool => $this->input('duration_mode') === DurationMode::Variable->value),
                'nullable', 'integer', 'min:1',
            ],

            'start_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:'.config('availability.max_start_interval_minutes', 1440)],
            'booking_notice_value' => ['nullable', 'integer', 'min:0'],
            'booking_notice_unit' => ['nullable', Rule::enum(BookingNoticeUnit::class)],
            'maximum_booking_notice_value' => ['nullable', 'integer', 'min:0'],
            'maximum_booking_notice_unit' => ['nullable', Rule::enum(BookingNoticeUnit::class)],
            'seasonal_availability_enabled' => ['nullable', 'boolean'],
            'season_start_date' => [
                Rule::requiredIf(fn (): bool => $this->boolean('seasonal_availability_enabled')),
                'nullable', 'date_format:Y-m-d',
            ],
            'season_end_date' => [
                Rule::requiredIf(fn (): bool => $this->boolean('seasonal_availability_enabled')),
                'nullable', 'date_format:Y-m-d',
            ],
            'season_recurrence' => [
                Rule::requiredIf(fn (): bool => $this->boolean('seasonal_availability_enabled')),
                'nullable', Rule::enum(SeasonRecurrence::class),
            ],

            'short_notice_fees' => ['nullable', 'array', 'max:50'],
            'short_notice_fees.*.threshold_value' => ['required', 'integer', 'min:1'],
            'short_notice_fees.*.threshold_unit' => ['required', Rule::enum(BookingNoticeUnit::class)],
            'short_notice_fees.*.adjustment_type' => [
                'required',
                Rule::in([PricingAdjustmentType::Fixed->value, PricingAdjustmentType::Percentage->value]),
            ],
            'short_notice_fees.*.fixed_amount' => ['nullable', new MoneyAmount($currency)],
            'short_notice_fees.*.percentage' => ['nullable', 'string', 'max:20'],

            'buffer_before_minutes' => ['required', 'integer', 'min:0', 'max:'.config('appointment-types.max_buffer_minutes', 10080)],
            'buffer_after_minutes' => ['required', 'integer', 'min:0', 'max:'.config('appointment-types.max_buffer_minutes', 10080)],

            'pricing_mode' => ['required', Rule::enum(PricingMode::class)],
            'fixed_price' => [
                Rule::requiredIf(fn (): bool => $this->input('pricing_mode') === PricingMode::Fixed->value),
                'nullable', new MoneyAmount($currency),
            ],
            'attendee_price' => [
                Rule::requiredIf(fn (): bool => $this->input('pricing_mode') === PricingMode::PerAttendee->value
                    && $this->input('attendee_pricing_mode') === AttendeePricingMode::Flat->value),
                'nullable', new MoneyAmount($currency),
            ],
            'attendee_pricing_mode' => [
                Rule::requiredIf(fn (): bool => $this->input('pricing_mode') === PricingMode::PerAttendee->value),
                'nullable', Rule::enum(AttendeePricingMode::class),
            ],
            'attendee_price_ranges' => [
                Rule::excludeIf(fn (): bool => ! $usesAttendeeRanges()),
                Rule::requiredIf($usesAttendeeRanges),
                'array', 'min:1', 'max:50',
            ],
            'attendee_price_ranges.*.min_attendees' => ['required', 'integer', 'min:1', 'max:'.config('appointment-types.max_capacity', 100000)],
            'attendee_price_ranges.*.max_attendees' => ['required', 'integer', 'min:1', 'max:'.config('appointment-types.max_capacity', 100000)],
            'attendee_price_ranges.*.unit_price' => ['required', new MoneyAmount($currency)],
            'rate_amount' => [
                Rule::requiredIf(fn (): bool => $this->input('pricing_mode') === PricingMode::Rate->value),
                'nullable', new MoneyAmount($currency),
            ],
            'rate_unit' => [
                Rule::requiredIf(fn (): bool => $this->input('pricing_mode') === PricingMode::Rate->value),
                'nullable', Rule::enum(DurationUnit::class),
            ],
            'payment_collection_mode' => ['nullable', Rule::enum(PaymentCollectionMode::class)],
            'retainer_type' => [
                Rule::requiredIf(fn (): bool => ($this->input('pricing_mode') !== PricingMode::Free->value || $this->hasPaidEquipment())
                    && $this->input('payment_collection_mode') === PaymentCollectionMode::Retainer->value),
                'nullable', Rule::enum(RetainerType::class),
            ],
            'retainer_amount' => [
                Rule::requiredIf(fn (): bool => ($this->input('pricing_mode') !== PricingMode::Free->value || $this->hasPaidEquipment())
                    && $this->input('payment_collection_mode') === PaymentCollectionMode::Retainer->value
                    && $this->input('retainer_type') === RetainerType::Fixed->value),
                'nullable', new MoneyAmount($currency),
            ],
            'retainer_percentage' => [
                Rule::requiredIf(fn (): bool => ($this->input('pricing_mode') !== PricingMode::Free->value || $this->hasPaidEquipment())
                    && $this->input('payment_collection_mode') === PaymentCollectionMode::Retainer->value
                    && $this->input('retainer_type') === RetainerType::Percentage->value),
                'nullable', 'string', 'max:20',
            ],
            'balance_due_value' => ['nullable', 'integer', 'min:0', 'max:12000'],
            'balance_due_unit' => ['nullable', Rule::enum(BookingNoticeUnit::class)],
            'client_refund_percentage' => ['nullable', 'string', 'max:20'],
            'staff_refund_percentage' => ['nullable', 'string', 'max:20'],

            'resource_uuids' => ['array'],
            'resource_uuids.*' => ['uuid', 'distinct'],
            'resource_requirement_modes' => ['nullable', 'array'],
            'resource_requirement_modes.*' => ['nullable', Rule::enum(ResourceRequirementMode::class)],
            'resource_replacement_groups' => ['nullable', 'array'],
            'resource_replacement_groups.*' => ['nullable', 'string', 'max:80'],
            'resource_quantities' => ['nullable', 'array'],
            'resource_quantities.*' => [
                'nullable',
                'integer',
                'min:1',
                'max:'.config('equipment.max_inventory_quantity', 100000),
            ],
            'resource_equipment_pricing_modes' => ['nullable', 'array'],
            'resource_equipment_pricing_modes.*' => ['nullable', Rule::enum(EquipmentPricingMode::class)],
            'resource_equipment_unit_prices' => ['nullable', 'array'],
            'resource_equipment_unit_prices.*' => ['nullable', new MoneyAmount($currency)],
            'resource_equipment_fixed_prices' => ['nullable', 'array'],
            'resource_equipment_fixed_prices.*' => ['nullable', new MoneyAmount($currency)],
            'resource_equipment_bundles' => ['nullable', 'array'],
            'resource_equipment_bundles.*' => ['nullable', 'array', 'max:'.config('equipment.max_bundle_tiers', 20)],
            'resource_equipment_bundles.*.*.quantity' => [
                'nullable',
                'integer',
                'min:1',
                'max:'.config('equipment.max_inventory_quantity', 100000),
            ],
            'resource_equipment_bundles.*.*.amount' => ['nullable', new MoneyAmount($currency)],
            'requires_resource_confirmation' => ['nullable', 'boolean'],
            'email_verification_mode' => ['nullable', Rule::enum(EmailVerificationMode::class)],

            'cancellation_allowed' => ['nullable', 'boolean'],
            'cancellation_notice_value' => ['nullable', 'integer', 'min:0'],
            'cancellation_notice_unit' => ['nullable', Rule::enum(BookingNoticeUnit::class)],
            'cancellation_policy_text' => ['nullable', 'string', 'max:10000'],
            'rescheduling_allowed' => ['nullable', 'boolean'],
            'rescheduling_notice_value' => ['nullable', 'integer', 'min:0'],
            'rescheduling_notice_unit' => ['nullable', Rule::enum(BookingNoticeUnit::class)],
            'rescheduling_max_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'rescheduling_policy_text' => ['nullable', 'string', 'max:10000'],

            'reminder_enabled' => ['nullable', 'boolean'],
            'reminder_threshold_basis' => ['nullable', Rule::enum(ReminderThresholdBasis::class)],
            'reminder_threshold_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            'reminder_before_value' => ['nullable', 'integer', 'min:1'],
            'reminder_before_unit' => ['nullable', Rule::enum(BookingNoticeUnit::class)],
            'reminder_clients' => ['nullable', 'boolean'],
            'reminder_resources' => ['nullable', 'boolean'],

            'redirect_url' => ['nullable', 'url', 'max:2048'],
            'logo_file' => [
                'nullable',
                'file',
                'mimes:'.implode(',', config('appointment-types.logo_extensions', ['jpg', 'jpeg', 'png', 'webp'])),
                'max:'.config('appointment-types.max_logo_kilobytes', 5120),
            ],
            'remove_logo' => ['nullable', 'boolean'],

            'contract_file' => [
                'nullable',
                'file',
                'mimes:'.implode(',', config('contracts.template_extensions', ['pdf'])),
                'max:'.config('contracts.max_template_kilobytes', 20480),
            ],
            'remove_contract' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateSeason($validator);
            $this->validateAttendeePricing($validator);
            $this->validateTicketing($validator);
            $this->validatePayments($validator);

            if ($this->input('pricing_mode') === PricingMode::PerAttendee->value
                && $this->input('attendance_mode') !== AttendanceMode::Group->value) {
                $validator->errors()->add('pricing_mode', 'Per-attendee pricing is only available for group appointment types.');
            }

            if ($this->boolean('is_online')) {
                $provider = ConferenceProvider::tryFrom((string) $this->input('meeting_provider', ''));
                if ($provider !== null) {
                    $organization = app(OrganizationContext::class)->organization();
                    if (! app(ConferenceProviderCatalog::class)->isConfigured($organization, $provider)) {
                        $validator->errors()->add(
                            'meeting_provider',
                            $provider->label().' is not fully configured in Organization > Settings.',
                        );
                    }
                }
            }

            $unitValue = (string) $this->input('duration_unit', 'minute');
            $unit = DurationUnit::tryFrom($unitValue);

            if ($unit !== null) {
                $maxForUnit = (int) config('appointment-types.max_duration.'.$unit->value, PHP_INT_MAX);
                $fields = $this->input('duration_mode') === DurationMode::Variable->value
                    ? ['minimum_duration_value', 'maximum_duration_value', 'duration_increment_value']
                    : ['duration_value'];

                foreach ($fields as $field) {
                    $value = $this->input($field);
                    if (is_numeric($value) && (int) $value > $maxForUnit) {
                        $validator->errors()->add($field, sprintf('The duration is too large for the %s unit.', $unit->value));
                    }
                }
            }

            if ($this->input('duration_mode') === DurationMode::Variable->value) {
                $minimum = filter_var($this->input('minimum_duration_value'), FILTER_VALIDATE_INT);
                $maximum = filter_var($this->input('maximum_duration_value'), FILTER_VALIDATE_INT);
                $increment = filter_var($this->input('duration_increment_value'), FILTER_VALIDATE_INT);

                if ($minimum !== false && $maximum !== false && $maximum < $minimum) {
                    $validator->errors()->add('maximum_duration_value', 'The maximum duration must be greater than or equal to the minimum duration.');
                }

                if ($minimum !== false && $maximum !== false && $increment !== false && $increment > 0 && $maximum >= $minimum) {
                    if (($maximum - $minimum) % $increment !== 0) {
                        $validator->errors()->add(
                            'duration_increment_value',
                            'The increment must land exactly on the configured maximum duration from the minimum duration.',
                        );
                    }
                }
            }

            foreach ([
                ['booking_notice_value', 'booking_notice_unit', 'minimum booking notice'],
                ['maximum_booking_notice_value', 'maximum_booking_notice_unit', 'maximum booking notice'],
                ['cancellation_notice_value', 'cancellation_notice_unit', 'cancellation notice'],
                ['rescheduling_notice_value', 'rescheduling_notice_unit', 'rescheduling notice'],
                ['reminder_before_value', 'reminder_before_unit', 'reminder timing'],
            ] as [$valueField, $unitField, $label]) {
                $noticeValue = filter_var($this->input($valueField, 0), FILTER_VALIDATE_INT);
                $noticeUnit = BookingNoticeUnit::tryFrom((string) $this->input($unitField, BookingNoticeUnit::Hour->value));
                if ($noticeValue !== false && $noticeUnit !== null) {
                    $maxNotice = (int) config('appointment-types.max_booking_notice.'.$noticeUnit->value, PHP_INT_MAX);
                    if ($noticeValue > $maxNotice) {
                        $validator->errors()->add(
                            $valueField,
                            sprintf('The %s is too large for the %s unit.', $label, $noticeUnit->value),
                        );
                    }
                }
            }

            $seenShortNoticeThresholds = [];
            foreach ((array) $this->input('short_notice_fees', []) as $index => $fee) {
                if (! is_array($fee)) {
                    continue;
                }

                $thresholdValue = filter_var($fee['threshold_value'] ?? null, FILTER_VALIDATE_INT);
                $thresholdUnit = BookingNoticeUnit::tryFrom((string) ($fee['threshold_unit'] ?? ''));
                if ($thresholdValue !== false && $thresholdValue > 0 && $thresholdUnit !== null) {
                    $maxNotice = (int) config('appointment-types.max_booking_notice.'.$thresholdUnit->value, PHP_INT_MAX);
                    if ($thresholdValue > $maxNotice) {
                        $validator->errors()->add(
                            'short_notice_fees.'.$index.'.threshold_value',
                            sprintf('The short-notice threshold is too large for the %s unit.', $thresholdUnit->value),
                        );
                    }

                    $thresholdKey = $thresholdValue.':'.$thresholdUnit->value;
                    if (isset($seenShortNoticeThresholds[$thresholdKey])) {
                        $validator->errors()->add(
                            'short_notice_fees.'.$index.'.threshold_value',
                            'Each short-notice threshold may only be configured once.',
                        );
                    }
                    $seenShortNoticeThresholds[$thresholdKey] = true;
                }

                $adjustmentType = PricingAdjustmentType::tryFrom((string) ($fee['adjustment_type'] ?? ''));
                if ($adjustmentType === PricingAdjustmentType::Fixed) {
                    $amount = $fee['fixed_amount'] ?? null;
                    if (! is_string($amount) && ! is_int($amount)) {
                        $validator->errors()->add(
                            'short_notice_fees.'.$index.'.fixed_amount',
                            'Enter a fixed short-notice fee.',
                        );
                    } else {
                        try {
                            if (app(MoneyService::class)->parse($amount, app(OrganizationContext::class)->organization()->currency) <= 0) {
                                $validator->errors()->add(
                                    'short_notice_fees.'.$index.'.fixed_amount',
                                    'The fixed short-notice fee must be greater than zero.',
                                );
                            }
                        } catch (InvalidArgumentException) {
                            // MoneyAmount reports the field-specific formatting error.
                        }
                    }
                }

                if ($adjustmentType === PricingAdjustmentType::Percentage) {
                    try {
                        $basisPoints = app(PercentageService::class)->parseToBasisPoints($fee['percentage'] ?? null);
                        if ($basisPoints === null || $basisPoints <= 0) {
                            $validator->errors()->add(
                                'short_notice_fees.'.$index.'.percentage',
                                'The short-notice percentage must be greater than zero.',
                            );
                        }
                    } catch (InvalidArgumentException $exception) {
                        $validator->errors()->add(
                            'short_notice_fees.'.$index.'.percentage',
                            $exception->getMessage(),
                        );
                    }
                }
            }

            if ($this->boolean('reminder_enabled') && ! $this->boolean('reminder_clients') && ! $this->boolean('reminder_resources')) {
                $validator->errors()->add('reminder_clients', 'Enable at least one reminder recipient: clients or resources.');
            }

            $selectedResourceUuids = array_values(array_filter(
                (array) $this->input('resource_uuids', []),
                fn (mixed $uuid): bool => is_string($uuid) && Str::isUuid($uuid),
            ));
            $requirementModes = (array) $this->input('resource_requirement_modes', []);
            $replacementNames = (array) $this->input('resource_replacement_groups', []);
            $replacementGroups = [];
            $organization = app(OrganizationContext::class)->organization();
            $availableResources = $organization->resources()->get()->keyBy('uuid');
            $money = app(MoneyService::class);

            foreach ($selectedResourceUuids as $uuid) {
                /** @var Resource|null $resource */
                $resource = $availableResources->get($uuid);
                if ($resource === null || $resource->type !== 'equipment') {
                    continue;
                }

                $requirementMode = ResourceRequirementMode::tryFrom((string) ($requirementModes[$uuid] ?? ResourceRequirementMode::Inherit->value))
                    ?? ResourceRequirementMode::Inherit;
                // Replacement resources retain binary one-candidate semantics for
                // backwards compatibility, even if the resource also has stock
                // tracking enabled. The replacement group owns the selection.
                $quantityManaged = $resource->usesQuantityInventory()
                    && $requirementMode !== ResourceRequirementMode::Replacement;
                if ($quantityManaged) {
                    $quantity = filter_var(data_get($this->input('resource_quantities', []), $uuid), FILTER_VALIDATE_INT);
                    if ($quantity === false || $quantity < 1) {
                        $validator->errors()->add('resource_quantities.'.$uuid, 'Enter how many pieces this appointment requires.');
                    } elseif ($quantity > (int) $resource->inventory_quantity) {
                        $validator->errors()->add(
                            'resource_quantities.'.$uuid,
                            'The required quantity cannot exceed the equipment stock of '.$resource->inventory_quantity.'.',
                        );
                    }
                }

                $pricingMode = EquipmentPricingMode::tryFrom((string) data_get(
                    $this->input('resource_equipment_pricing_modes', []),
                    $uuid,
                    EquipmentPricingMode::Free->value,
                )) ?? EquipmentPricingMode::Free;
                $effectiveRequired = match ($requirementMode) {
                    ResourceRequirementMode::Required => true,
                    ResourceRequirementMode::Replacement => true,
                    ResourceRequirementMode::Optional => false,
                    ResourceRequirementMode::Inherit => $resource->defaultRequiredForOrganization($organization),
                };
                if ($pricingMode !== EquipmentPricingMode::Free && ! $effectiveRequired) {
                    $validator->errors()->add(
                        'resource_equipment_pricing_modes.'.$uuid,
                        'Paid equipment must be required so the displayed booking price is deterministic.',
                    );
                }

                if ($pricingMode === EquipmentPricingMode::PerUnit) {
                    $this->validatePositiveMoney(
                        $validator,
                        'resource_equipment_unit_prices.'.$uuid,
                        data_get($this->input('resource_equipment_unit_prices', []), $uuid),
                        $money,
                        $organization->currency,
                    );
                }
                if ($pricingMode === EquipmentPricingMode::Fixed) {
                    $this->validatePositiveMoney(
                        $validator,
                        'resource_equipment_fixed_prices.'.$uuid,
                        data_get($this->input('resource_equipment_fixed_prices', []), $uuid),
                        $money,
                        $organization->currency,
                    );
                }
                if ($pricingMode === EquipmentPricingMode::Bundles) {
                    $bundles = data_get($this->input('resource_equipment_bundles', []), $uuid, []);
                    if (! is_array($bundles) || $bundles === []) {
                        $validator->errors()->add('resource_equipment_bundles.'.$uuid, 'Add at least one equipment bundle.');
                        continue;
                    }

                    $seenQuantities = [];
                    foreach ($bundles as $index => $bundle) {
                        if (! is_array($bundle)) {
                            continue;
                        }
                        $bundleQuantity = filter_var($bundle['quantity'] ?? null, FILTER_VALIDATE_INT);
                        if ($bundleQuantity === false || $bundleQuantity < 1) {
                            $validator->errors()->add(
                                'resource_equipment_bundles.'.$uuid.'.'.$index.'.quantity',
                                'Enter a valid bundle quantity.',
                            );
                        } elseif ($bundleQuantity > (int) $resource->inventory_quantity) {
                            $validator->errors()->add(
                                'resource_equipment_bundles.'.$uuid.'.'.$index.'.quantity',
                                'A bundle cannot exceed the equipment stock.',
                            );
                        } elseif (isset($seenQuantities[$bundleQuantity])) {
                            $validator->errors()->add(
                                'resource_equipment_bundles.'.$uuid.'.'.$index.'.quantity',
                                'Each bundle quantity must be unique.',
                            );
                        } else {
                            $seenQuantities[$bundleQuantity] = true;
                        }

                        $this->validatePositiveMoney(
                            $validator,
                            'resource_equipment_bundles.'.$uuid.'.'.$index.'.amount',
                            $bundle['amount'] ?? null,
                            $money,
                            $organization->currency,
                        );
                    }
                    if (! isset($seenQuantities[1])) {
                        $validator->errors()->add(
                            'resource_equipment_bundles.'.$uuid,
                            'Bundle pricing requires a one-piece tier so every quantity has an exact price.',
                        );
                    }
                }
            }

            foreach ($selectedResourceUuids as $uuid) {
                $mode = ResourceRequirementMode::tryFrom((string) ($requirementModes[$uuid] ?? ResourceRequirementMode::Inherit->value));
                if ($mode !== ResourceRequirementMode::Replacement) {
                    continue;
                }

                $name = Str::squish((string) ($replacementNames[$uuid] ?? ''));
                if ($name === '') {
                    $validator->errors()->add(
                        'resource_replacement_groups.'.$uuid,
                        'Enter a replacement group name for every replacement resource.',
                    );
                    continue;
                }

                $replacementGroups[Str::lower($name)][] = $uuid;
            }

            foreach ($replacementGroups as $members) {
                if (count($members) < 2) {
                    $validator->errors()->add(
                        'resource_uuids',
                        'Each replacement group must contain at least two selected resources.',
                    );
                }
            }

            $appointmentType = $this->route('appointmentType') ?? $this->route('appointment_type');
            if ($appointmentType instanceof AppointmentType) {
                $appointmentType->loadMissing('questions.resourceRequirementRule.resources');
                foreach ($appointmentType->questions as $question) {
                    $conditionalRule = $question->resourceRequirementRule;
                    if ($conditionalRule === null) {
                        continue;
                    }

                    foreach ($conditionalRule->resources as $resource) {
                        if (! in_array($resource->uuid, $selectedResourceUuids, true)) {
                            $validator->errors()->add(
                                'resource_uuids',
                                'The optional resource “'.$resource->name.'” is used by the conditional group “'.$conditionalRule->group_name.'” and cannot be unassigned.',
                            );
                            continue;
                        }

                        $mode = ResourceRequirementMode::tryFrom((string) ($requirementModes[$resource->uuid] ?? ResourceRequirementMode::Inherit->value))
                            ?? ResourceRequirementMode::Inherit;
                        $effectiveRequired = match ($mode) {
                            ResourceRequirementMode::Required, ResourceRequirementMode::Replacement => true,
                            ResourceRequirementMode::Optional => false,
                            ResourceRequirementMode::Inherit => $resource->defaultRequiredForOrganization($organization),
                        };
                        if ($effectiveRequired) {
                            $validator->errors()->add(
                                'resource_requirement_modes.'.$resource->uuid,
                                'The resource “'.$resource->name.'” must remain optional because the question “'.$question->label.'” promotes it conditionally.',
                            );
                        }
                    }
                }
            }

            if ($this->boolean('requires_resource_confirmation')) {
                $resourceUuids = $selectedResourceUuids;

                if ($resourceUuids === []) {
                    $validator->errors()->add('resource_uuids', 'At least one employee resource is required when resource confirmation is enabled.');
                } else {
                    $organizationKey = app(OrganizationContext::class)->organization()->getKey();
                    $modes = $requirementModes;
                    $hasRequiredEmployee = collect($resourceUuids)->contains(function (string $uuid) use ($organizationKey, $modes): bool {
                        $resource = Resource::whereUuid($uuid)
                            ->whereHas('organizations', fn ($query) => $query->where('organizations.id', $organizationKey))
                            ->with('person')
                            ->first();

                        if ($resource === null || $resource->person_id === null) {
                            return false;
                        }

                        $mode = ResourceRequirementMode::tryFrom((string) ($modes[$uuid] ?? ResourceRequirementMode::Inherit->value))
                            ?? ResourceRequirementMode::Inherit;
                        $required = match ($mode) {
                            ResourceRequirementMode::Required => true,
                            ResourceRequirementMode::Replacement => true,
                            ResourceRequirementMode::Optional => false,
                            ResourceRequirementMode::Inherit => $resource->defaultRequiredForOrganization(app(OrganizationContext::class)->organization()),
                        };

                        return $required && filled($resource->person?->primary_email);
                    });

                    if (! $hasRequiredEmployee) {
                        $validator->errors()->add('resource_uuids', 'Resource confirmation requires at least one required employee resource with an email address.');
                    }
                }
            }

            $redirect = $this->input('redirect_url');
            if (is_string($redirect) && $redirect !== '') {
                $scheme = strtolower((string) parse_url($redirect, PHP_URL_SCHEME));
                if (! in_array($scheme, ['http', 'https'], true)) {
                    $validator->errors()->add('redirect_url', 'The redirect URL must use http or https.');
                }
            }
        });
    }

    private function validateAttendeePricing(Validator $validator): void
    {
        if ($this->input('pricing_mode') !== PricingMode::PerAttendee->value
            || array_filter($validator->errors()->keys(), fn (string $key): bool =>
                str_starts_with($key, 'attendee_') || in_array($key, ['attendance_mode', 'capacity'], true)) !== []) {
            return;
        }

        $mode = AttendeePricingMode::tryFrom((string) $this->input('attendee_pricing_mode'));
        if ($mode === null) {
            return;
        }

        $money = app(MoneyService::class);
        $currency = app(OrganizationContext::class)->organization()->currency;
        try {
            $ranges = $mode === AttendeePricingMode::Flat ? [] : array_map(fn (array $range): array => [
                'min_attendees' => (int) $range['min_attendees'],
                'max_attendees' => (int) $range['max_attendees'],
                'unit_amount_minor' => $money->parse($range['unit_price'], $currency),
            ], $this->input('attendee_price_ranges', []));
            $type = new \App\Models\AppointmentType([
                'attendance_mode' => $this->input('attendance_mode'),
                'capacity' => (int) $this->input('capacity'),
                'attendee_pricing_mode' => $mode,
                'attendee_price_minor' => $mode === AttendeePricingMode::Flat ? $money->parse($this->input('attendee_price'), $currency) : null,
                'attendee_price_ranges' => $ranges,
            ]);
            app(AttendeePricingService::class)->breakdown($type, (int) $type->capacity);
        } catch (\InvalidArgumentException $exception) {
            $validator->errors()->add($mode === AttendeePricingMode::Flat ? 'attendee_price' : 'attendee_price_ranges', $exception->getMessage());
        }
    }

    private function validatePayments(Validator $validator): void
    {
        $percentages = app(PercentageService::class);
        foreach ([
            'client_refund_percentage' => 'Client cancellation refund',
            'staff_refund_percentage' => 'Staff cancellation refund',
        ] as $field => $label) {
            try {
                $basisPoints = $percentages->parseToBasisPoints($this->input($field, $field === 'staff_refund_percentage' ? '100' : '0'));
                if ($basisPoints === null || $basisPoints > 10000) {
                    $validator->errors()->add($field, $label.' must be between 0% and 100%.');
                }
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add($field, $exception->getMessage());
            }
        }

        if (($this->input('pricing_mode') === PricingMode::Free->value && ! $this->hasPaidEquipment())
            || $this->input('payment_collection_mode') !== PaymentCollectionMode::Retainer->value) {
            return;
        }

        $type = RetainerType::tryFrom((string) $this->input('retainer_type'));
        if ($type === RetainerType::Fixed) {
            try {
                $amount = app(MoneyService::class)->parse(
                    (string) $this->input('retainer_amount'),
                    app(OrganizationContext::class)->organization()->currency,
                );
                if ($amount <= 0) {
                    $validator->errors()->add('retainer_amount', 'The retainer amount must be greater than zero.');
                }
            } catch (InvalidArgumentException) {
                // MoneyAmount provides the field-specific formatting error.
            }
        }
        if ($type === RetainerType::Percentage) {
            try {
                $basisPoints = $percentages->parseToBasisPoints($this->input('retainer_percentage'));
                if ($basisPoints === null || $basisPoints <= 0 || $basisPoints > 10000) {
                    $validator->errors()->add('retainer_percentage', 'The retainer percentage must be greater than 0% and no more than 100%.');
                }
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('retainer_percentage', $exception->getMessage());
            }
        }
    }

    private function hasPaidEquipment(): bool
    {
        $selected = array_fill_keys((array) $this->input('resource_uuids', []), true);
        foreach ((array) $this->input('resource_equipment_pricing_modes', []) as $uuid => $mode) {
            if (isset($selected[$uuid]) && $mode !== EquipmentPricingMode::Free->value) {
                return true;
            }
        }

        return false;
    }

    private function validatePositiveMoney(
        Validator $validator,
        string $field,
        mixed $value,
        MoneyService $money,
        string $currency,
    ): void {
        if (! is_string($value) && ! is_int($value)) {
            $validator->errors()->add($field, 'Enter an amount greater than zero.');

            return;
        }

        try {
            if ($money->parse($value, $currency) <= 0) {
                $validator->errors()->add($field, 'The amount must be greater than zero.');
            }
        } catch (InvalidArgumentException) {
            // MoneyAmount provides the field-specific formatting error.
        }
    }

    private function validateTicketing(Validator $validator): void
    {
        $this->validateTicketingSnapshotLock($validator);

        if (! $this->boolean('ticketing_enabled')) {
            return;
        }

        if ($this->input('attendance_mode') !== AttendanceMode::Group->value) {
            $validator->errors()->add('attendance_mode', 'Ticketed events must use group attendance so separate clients share event capacity.');
        }
        if ($this->input('duration_mode') !== DurationMode::Fixed->value) {
            $validator->errors()->add('duration_mode', 'Ticketed events require one fixed doors-open-to-booking-end duration.');
        }
        if (! in_array($this->input('pricing_mode'), [PricingMode::Free->value, PricingMode::PerAttendee->value], true)) {
            $validator->errors()->add('pricing_mode', 'Ticketed events must use free or per-attendee pricing.');
        }

        $duration = filter_var($this->input('duration_value'), FILTER_VALIDATE_INT);
        $unit = DurationUnit::tryFrom((string) $this->input('duration_unit'));
        $showStart = filter_var($this->input('show_start_offset_minutes'), FILTER_VALIDATE_INT);
        $showEndInput = $this->input('show_end_offset_minutes');
        $showEnd = $showEndInput === null || $showEndInput === ''
            ? null
            : filter_var($showEndInput, FILTER_VALIDATE_INT);

        if ($duration !== false && $duration > 0 && $unit !== null) {
            $durationMinutes = $duration * $unit->minutes();
            if ($showStart !== false && ($showStart < 0 || $showStart > $durationMinutes)) {
                $validator->errors()->add('show_start_offset_minutes', 'Show start must fall between doors open and the booking end.');
            }
            if ($showEnd !== null && $showEnd !== false) {
                if ($showEnd < 0 || $showEnd > $durationMinutes) {
                    $validator->errors()->add('show_end_offset_minutes', 'Show end must fall between doors open and the booking end.');
                } elseif ($showStart !== false && $showEnd < $showStart) {
                    $validator->errors()->add('show_end_offset_minutes', 'Show end must be at or after show start.');
                }
            }
        }

        $scheme = TicketSeatingScheme::tryFrom((string) $this->input('ticket_seating_scheme'));
        $capacity = filter_var($this->input('capacity'), FILTER_VALIDATE_INT);
        if ($scheme === null || $capacity === false || $capacity < 1) {
            return;
        }

        try {
            app(TicketSeatingService::class)->normalizeInput(
                $scheme,
                $this->boolean('ticket_seat_optional'),
                (array) $this->input('ticket_seat_blocks', []),
                $capacity,
                app(OrganizationContext::class)->organization()->currency,
                $this->input('pricing_mode') === PricingMode::PerAttendee->value,
            );
        } catch (InvalidArgumentException $exception) {
            $validator->errors()->add('ticket_seat_blocks', $exception->getMessage());
        }
    }

    private function validateTicketingSnapshotLock(Validator $validator): void
    {
        $type = $this->route('appointment_type');
        if (! $type instanceof AppointmentType
            || ! $type->appointments()->where('status', 'scheduled')->where('ends_at_utc', '>', now('UTC'))->exists()) {
            return;
        }

        $enabled = $this->boolean('ticketing_enabled');
        $changed = $enabled !== (bool) $type->ticketing_enabled;
        if ($enabled && $type->ticketing_enabled) {
            $scheme = TicketSeatingScheme::tryFrom((string) $this->input('ticket_seating_scheme'));
            $capacity = filter_var($this->input('capacity'), FILTER_VALIDATE_INT);
            $optional = $scheme?->supportsOptionalSeat() && $this->boolean('ticket_seat_optional');
            $blocks = null;
            if ($scheme !== null && $capacity !== false && $capacity > 0) {
                try {
                    $blocks = app(TicketSeatingService::class)->normalizeInput(
                        $scheme,
                        $optional,
                        (array) $this->input('ticket_seat_blocks', []),
                        $capacity,
                        app(OrganizationContext::class)->organization()->currency,
                        $this->input('pricing_mode') === PricingMode::PerAttendee->value,
                    );
                } catch (InvalidArgumentException) {
                    return;
                }
            }

            $showEnd = $this->input('show_end_offset_minutes');
            $storedBlocks = $type->ticket_seating_scheme?->usesBlocks()
                ? app(TicketSeatingService::class)->normalize(
                    $type->ticket_seating_scheme,
                    (bool) $type->ticket_seat_optional,
                    $type->ticket_seat_blocks ?? [],
                    (int) $type->capacity,
                )
                : [];
            $changed = $changed
                || (int) $this->input('capacity') !== (int) $type->capacity
                || (string) $this->input('duration_unit') !== $type->duration_unit->value
                || (int) $this->input('duration_value') !== (int) $type->duration_value
                || (int) $this->input('show_start_offset_minutes') !== (int) $type->show_start_offset_minutes
                || ($showEnd === null || $showEnd === '' ? null : (int) $showEnd) !== $type->show_end_offset_minutes
                || $scheme !== $type->ticket_seating_scheme
                || $optional !== (bool) $type->ticket_seat_optional
                || $blocks !== $storedBlocks;
        }

        if ($changed) {
            $validator->errors()->add(
                'ticketing_enabled',
                'Ticket timing and seating cannot be changed while this appointment type has a future booked event. Existing event snapshots must remain consistent for every buyer.',
            );
        }
    }

    private function validateSeason(Validator $validator): void
    {
        if (! $this->boolean('seasonal_availability_enabled')) {
            return;
        }

        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->input('season_start_date'));
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->input('season_end_date'));
        if ($start === false || $end === false
            || $start->format('Y-m-d') !== $this->input('season_start_date')
            || $end->format('Y-m-d') !== $this->input('season_end_date')) {
            return;
        }

        if ($end < $start) {
            $validator->errors()->add('season_end_date', 'The season end date must be on or after its start date. For a season crossing New Year, select an end date in the following year.');
            return;
        }

        if ($this->input('season_recurrence') === SeasonRecurrence::Yearly->value
            && $end >= $start->modify('+1 year')) {
            $validator->errors()->add('season_end_date', 'A yearly season must be shorter than one year.');
        }
    }
}
