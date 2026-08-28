<?php

namespace App\Http\Requests;

use App\Enums\AppointmentVisibility;
use App\Enums\AttendanceMode;
use App\Enums\BookingNoticeUnit;
use App\Enums\DurationMode;
use App\Enums\DurationUnit;
use App\Enums\EmailVerificationMode;
use App\Enums\PricingMode;
use App\Enums\PricingAdjustmentType;
use App\Enums\ReminderThresholdBasis;
use App\Enums\ResourceRequirementMode;
use App\Models\Resource;
use App\Domain\Money\MoneyService;
use App\Domain\Questionnaires\PercentageService;
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
            'rate_amount' => [
                Rule::requiredIf(fn (): bool => $this->input('pricing_mode') === PricingMode::Rate->value),
                'nullable', new MoneyAmount($currency),
            ],
            'rate_unit' => [
                Rule::requiredIf(fn (): bool => $this->input('pricing_mode') === PricingMode::Rate->value),
                'nullable', Rule::enum(DurationUnit::class),
            ],

            'resource_uuids' => ['array'],
            'resource_uuids.*' => ['uuid', 'distinct'],
            'resource_requirement_modes' => ['nullable', 'array'],
            'resource_requirement_modes.*' => ['nullable', Rule::enum(ResourceRequirementMode::class)],
            'resource_replacement_groups' => ['nullable', 'array'],
            'resource_replacement_groups.*' => ['nullable', 'string', 'max:80'],
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
}
