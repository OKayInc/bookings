<?php

namespace App\Http\Controllers;

use App\Domain\Money\MoneyService;
use App\Domain\Questionnaires\NumericQuestionConstraintService;
use App\Domain\Questionnaires\PercentageService;
use App\Domain\Questionnaires\PhoneValidationService;
use App\Domain\Questionnaires\QuestionVisibilityService;
use App\Domain\Questionnaires\ReusableQuestionService;
use App\Domain\Resources\ConditionalResourceRequirementService;
use App\Domain\Resources\ResourceRequirementService;
use App\Enums\ConditionalResourceFulfillmentMode;
use App\Enums\PricingAdjustmentType;
use App\Enums\PricingApplicationMode;
use App\Enums\PricingPercentageBasis;
use App\Enums\QuestionType;
use App\Http\Requests\StoreAppointmentQuestionRequest;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\QuestionOption;
use App\Models\ReusableQuestion;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class AppointmentQuestionController extends Controller
{
    public function index(AppointmentType $appointmentType, OrganizationContext $context): View
    {
        $this->guard($appointmentType, $context);
        $appointmentType->load(['questions.options', 'questions.reusableQuestion', 'questions.visibilityConditions', 'questions.numericConstraints']);

        return view('questionnaire.index', compact('appointmentType'));
    }

    public function create(
        AppointmentType $appointmentType,
        OrganizationContext $context,
        PhoneValidationService $phones,
    ): View {
        $this->guard($appointmentType, $context);

        return view('questionnaire.create', [
            ...$this->formData($appointmentType, null, $context, $phones),
            ...$this->libraryData($appointmentType, $context),
        ]);
    }

    public function store(
        StoreAppointmentQuestionRequest $request,
        AppointmentType $appointmentType,
        OrganizationContext $context,
        MoneyService $money,
        PercentageService $percent,
        ReusableQuestionService $reusableQuestions,
        QuestionVisibilityService $visibility,
        NumericQuestionConstraintService $numericConstraints,
        ConditionalResourceRequirementService $conditionalResources,
    ): RedirectResponse {
        $this->guard($appointmentType, $context);
        $data = $request->validated();

        try {
            DB::transaction(function () use ($appointmentType, $data, $request, $context, $money, $percent, $reusableQuestions, $visibility, $numericConstraints, $conditionalResources): void {
                AppointmentType::query()->whereKey($appointmentType->getKey())->lockForUpdate()->firstOrFail();
                $question = $appointmentType->questions()->create(
                    $this->questionData($data, $request, $context, $money, $percent, $appointmentType),
                );
                $optionsByInputIndex = $this->syncOptions($question, $data, $context, $money, $percent);
                $conditionalResources->sync($appointmentType, $question, $data, $optionsByInputIndex);
                $visibility->sync($appointmentType, $question, (array) ($data['visibility_conditions'] ?? []));
                $numericConstraints->sync($appointmentType, $question, (array) ($data['numeric_constraints'] ?? []));
                $reusableQuestions->createFromAttachment($question, $context->organization());
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['question' => $exception->getMessage()]);
        }

        return redirect()
            ->route('appointment-types.questionnaire.index', $appointmentType)
            ->with('success', 'Reusable question created and attached.');
    }

    public function attach(
        AppointmentType $appointmentType,
        ReusableQuestion $reusableQuestion,
        OrganizationContext $context,
        ReusableQuestionService $reusableQuestions,
    ): RedirectResponse {
        $this->guard($appointmentType, $context);
        abort_unless(
            hash_equals($reusableQuestion->organization_id, $context->organization()->getKey())
                && $reusableQuestion->is_active,
            404,
        );

        $question = $reusableQuestions->attach($appointmentType, $reusableQuestion);

        return redirect()
            ->route('appointment-types.questionnaire.index', $appointmentType)
            ->with(
                'success',
                $question === null ? 'That reusable question is already attached.' : 'Reusable question attached.',
            );
    }

    public function edit(
        AppointmentType $appointmentType,
        AppointmentQuestion $question,
        OrganizationContext $context,
        PhoneValidationService $phones,
    ): View {
        $this->guardQuestion($appointmentType, $question, $context);
        $question->load([
            'options',
            'reusableQuestion',
            'visibilityConditions.sourceQuestion',
            'visibilityConditions.expectedOption',
            'visibilityConditions.expectedOptions',
            'numericConstraints.sourceQuestion',
            'resourceRequirementRule.triggerOption',
            'resourceRequirementRule.unavailableDefaultOption',
            'resourceRequirementRule.resources',
        ]);

        return view('questionnaire.edit', $this->formData($appointmentType, $question, $context, $phones));
    }

    public function update(
        StoreAppointmentQuestionRequest $request,
        AppointmentType $appointmentType,
        AppointmentQuestion $question,
        OrganizationContext $context,
        MoneyService $money,
        PercentageService $percent,
        ReusableQuestionService $reusableQuestions,
        QuestionVisibilityService $visibility,
        NumericQuestionConstraintService $numericConstraints,
        ConditionalResourceRequirementService $conditionalResources,
    ): RedirectResponse {
        $this->guardQuestion($appointmentType, $question, $context);
        $data = $request->validated();

        try {
            DB::transaction(function () use ($question, $data, $request, $context, $money, $percent, $appointmentType, $reusableQuestions, $visibility, $numericConstraints, $conditionalResources): void {
                AppointmentType::query()->whereKey($appointmentType->getKey())->lockForUpdate()->firstOrFail();
                if (! $request->boolean('resource_requirement_enabled')) {
                    $question->resourceRequirementRule()->delete();
                    $question->unsetRelation('resourceRequirementRule');
                }
                $question->update($this->questionData($data, $request, $context, $money, $percent, $appointmentType));
                $optionsByInputIndex = $this->syncOptions($question, $data, $context, $money, $percent);
                $conditionalResources->sync($appointmentType, $question, $data, $optionsByInputIndex);
                $visibility->sync($appointmentType, $question, (array) ($data['visibility_conditions'] ?? []));
                $numericConstraints->sync($appointmentType, $question, (array) ($data['numeric_constraints'] ?? []));

                if ($request->boolean('update_reusable_question')) {
                    $reusableQuestions->updateFromAttachment($question);
                }
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['question' => $exception->getMessage()]);
        }

        return redirect()
            ->route('appointment-types.questionnaire.index', $appointmentType)
            ->with(
                'success',
                $request->boolean('update_reusable_question')
                    ? 'Question and its reusable template updated.'
                    : 'Question updated for this appointment type.',
            );
    }

    public function destroy(
        AppointmentType $appointmentType,
        AppointmentQuestion $question,
        OrganizationContext $context,
    ): RedirectResponse {
        $this->guardQuestion($appointmentType, $question, $context);

        return DB::transaction(function () use ($appointmentType, $question): RedirectResponse {
            AppointmentType::query()->whereKey($appointmentType->getKey())->lockForUpdate()->firstOrFail();
            if ($question->dependentVisibilityConditions()->exists() || $question->dependentNumericConstraints()->exists()) {
                return back()->withErrors([
                    'question' => 'Remove this question from every display dependency and numeric constraint before disabling or deleting it.',
                ]);
            }

            if ($question->answers()->exists()) {
                $question->update(['is_active' => false]);

                return back()->with('success', 'Question disabled because historical booking answers exist.');
            }

            $wasReusable = $question->reusable_question_id !== null;
            $question->delete();

            return back()->with(
                'success',
                $wasReusable
                    ? 'Question removed. Its reusable template remains available.'
                    : 'Question removed.',
            );
        });
    }

    private function questionData(
        array $data,
        StoreAppointmentQuestionRequest $request,
        OrganizationContext $context,
        MoneyService $money,
        PercentageService $percent,
        AppointmentType $appointmentType,
    ): array {
        $questionType = QuestionType::from($data['type']);
        $pricing = PricingAdjustmentType::tryFrom($data['pricing_adjustment_type'] ?? 'none')
            ?? PricingAdjustmentType::None;

        if ($questionType !== QuestionType::Number) {
            $pricing = PricingAdjustmentType::None;
        }

        $configuration = [];
        foreach (['number_min', 'number_max', 'number_step'] as $field) {
            if (($data[$field] ?? '') !== '') {
                $configuration[str_replace('number_', '', $field)] = $data[$field];
            }
        }

        if ($questionType === QuestionType::File) {
            $extensions = array_values(array_filter(
                array_map(
                    fn ($extension) => strtolower(trim($extension)),
                    explode(',', $data['file_extensions'] ?? implode(',', config('questionnaire.file_extensions'))),
                ),
                fn ($extension) => preg_match('/^[a-z0-9]{1,12}$/', $extension),
            ));
            if ($extensions === []) {
                $extensions = config('questionnaire.file_extensions');
            }
            $configuration['extensions'] = $extensions;
            $configuration['max_count'] = (int) ($data['file_max_count'] ?? config('questionnaire.max_files_per_question', 20));
            $configuration['max_kilobytes'] = (int) ($data['file_max_kilobytes'] ?? config('questionnaire.max_file_kilobytes', 20480));
        }

        if ($questionType === QuestionType::Telephone) {
            $configuration['region'] = strtoupper($data['phone_region'] ?? config('questionnaire.default_phone_region', 'CA'));
        }
        if ($questionType === QuestionType::Address && ! empty($data['address_region'])) {
            $configuration['region'] = strtoupper($data['address_region']);
        }
        if ($questionType === QuestionType::Address && $request->boolean('distance_pricing_enabled')) {
            $mode = $data['distance_pricing_mode'] ?? 'fixed';
            $distancePricing = [
                'enabled' => true,
                'origin_address' => trim($data['distance_origin_address']),
                'unit' => $data['distance_unit'] ?? 'kilometer',
                'mode' => $mode,
            ];

            if ($mode === 'fixed') {
                $distancePricing['fixed_amount_minor'] = $money->parse(
                    $data['distance_fixed_amount'] ?? '0',
                    $context->organization()->currency,
                );
            } else {
                $ranges = [];
                foreach ((array) ($data['distance_ranges'] ?? []) as $range) {
                    $ranges[] = [
                        'minimum' => (float) $range['minimum'],
                        'maximum' => ($range['maximum'] ?? '') === '' ? null : (float) $range['maximum'],
                        'amount_minor' => $money->parse($range['amount'], $context->organization()->currency),
                    ];
                }
                usort($ranges, fn (array $a, array $b): int => $a['minimum'] <=> $b['minimum']);
                $distancePricing['ranges'] = $ranges;
                $distancePricing['fallback'] = [
                    'increment' => (float) $data['distance_fallback_increment'],
                    'amount_minor' => $money->parse(
                        $data['distance_fallback_amount'],
                        $context->organization()->currency,
                    ),
                ];
            }

            $configuration['distance_pricing'] = $distancePricing;
        }

        return [
            'type' => $questionType->value,
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'placeholder' => $data['placeholder'] ?? null,
            'is_required' => $request->boolean('is_required'),
            'is_active' => $request->boolean('is_active'),
            'position' => (int) ($data['position'] ?? ($appointmentType->questions()->max('position') + 1 ?: 1)),
            'configuration' => $configuration ?: null,
            'pricing_adjustment_type' => $pricing->value,
            'pricing_application_mode' => $pricing === PricingAdjustmentType::Rate
                ? PricingApplicationMode::PerUnit->value
                : ($data['pricing_application_mode'] ?? PricingApplicationMode::PerUnit->value),
            'pricing_amount_minor' => in_array($pricing, [PricingAdjustmentType::Fixed, PricingAdjustmentType::Rate], true)
                ? $money->parse($data['pricing_amount'] ?? '0', $context->organization()->currency)
                : null,
            'pricing_percentage_bps' => $pricing === PricingAdjustmentType::Percentage
                ? $percent->parseToBasisPoints($data['pricing_percentage'] ?? '0')
                : null,
            'pricing_percentage_basis' => $data['pricing_percentage_basis'] ?? PricingPercentageBasis::BasePrice->value,
            'pricing_included_units' => (int) ($data['pricing_included_units'] ?? 0),
        ];
    }

    private function syncOptions(
        AppointmentQuestion $question,
        array $data,
        OrganizationContext $context,
        MoneyService $money,
        PercentageService $percent,
    ): array {
        $existing = $question->options()
            ->with([
                'visibilityConditions',
                'matchingVisibilityConditions',
                'triggeredResourceRequirementRule',
                'unavailableDefaultResourceRequirementRule',
            ])
            ->get()
            ->keyBy('uuid');

        if (! $question->type->hasOptions()) {
            if ($existing->contains(fn ($option): bool => $this->optionIsUsedByVisibilityCondition($option))) {
                throw new InvalidArgumentException('This question type cannot change while one of its answers is used by a dependency or conditional resource rule.');
            }
            $question->options()->delete();

            return [];
        }

        $usedValues = [];
        $usedUuids = [];
        $normalized = [];

        foreach ((array) ($data['options'] ?? []) as $index => $option) {
            $label = trim($option['label']);
            $base = Str::slug($option['value'] ?? $label, '_') ?: 'option_'.($index + 1);
            $value = $base;
            $suffix = 2;
            while (in_array($value, $usedValues, true)) {
                $value = $base.'_'.$suffix++;
            }
            $usedValues[] = $value;
            $pricing = PricingAdjustmentType::tryFrom($option['pricing_adjustment_type'] ?? 'none')
                ?? PricingAdjustmentType::None;
            $uuid = isset($option['uuid']) && $option['uuid'] !== '' ? (string) $option['uuid'] : null;
            $model = $uuid === null ? null : $existing->get($uuid);
            if ($uuid !== null && $model === null) {
                throw new InvalidArgumentException('A submitted answer option does not belong to this question.');
            }
            if ($uuid !== null && isset($usedUuids[$uuid])) {
                throw new InvalidArgumentException('The same answer option cannot be submitted twice.');
            }
            if ($uuid !== null) {
                $usedUuids[$uuid] = true;
            }

            $normalized[] = ['input_index' => (int) $index, 'model' => $model, 'data' => [
                'label' => $label,
                'value' => $value,
                'position' => (int) ($option['position'] ?? 0),
                'is_active' => true,
                'pricing_adjustment_type' => $pricing->value,
                'pricing_amount_minor' => $pricing === PricingAdjustmentType::Fixed
                    ? $money->parse($option['pricing_amount'] ?? '0', $context->organization()->currency)
                    : null,
                'pricing_percentage_bps' => $pricing === PricingAdjustmentType::Percentage
                    ? $percent->parseToBasisPoints($option['pricing_percentage'] ?? '0')
                    : null,
                'pricing_percentage_basis' => $option['pricing_percentage_basis'] ?? PricingPercentageBasis::BasePrice->value,
            ]];
        }

        $removed = $existing->reject(fn ($option, string $uuid): bool => isset($usedUuids[$uuid]));
        if ($removed->contains(fn ($option): bool => $this->optionIsUsedByVisibilityCondition($option))) {
            throw new InvalidArgumentException('An answer used by a dependency or conditional resource rule cannot be removed. Update that rule first.');
        }

        foreach ($normalized as $row) {
            if ($row['model'] !== null) {
                $row['model']->update(['value' => '__editing_'.str_replace('-', '', $row['model']->uuid)]);
            }
        }
        foreach ($removed as $option) {
            $option->delete();
        }
        $optionsByInputIndex = [];
        foreach ($normalized as $row) {
            if ($row['model'] === null) {
                $model = $question->options()->create($row['data']);
            } else {
                $row['model']->update($row['data']);
                $model = $row['model'];
            }
            $optionsByInputIndex[$row['input_index']] = $model;
        }

        $question->unsetRelation('options');

        return $optionsByInputIndex;
    }

    private function optionIsUsedByVisibilityCondition(QuestionOption $option): bool
    {
        return $option->visibilityConditions->isNotEmpty()
            || $option->matchingVisibilityConditions->isNotEmpty()
            || $option->triggeredResourceRequirementRule !== null
            || $option->unavailableDefaultResourceRequirementRule !== null;
    }

    private function formData(
        AppointmentType $appointmentType,
        ?AppointmentQuestion $question,
        OrganizationContext $context,
        PhoneValidationService $phones,
    ): array {
        $appointmentType->loadMissing(['organization', 'resources']);
        $requirements = app(ResourceRequirementService::class);
        $conditionalResources = $appointmentType->resources
            ->reject(fn ($resource): bool => $requirements->isRequired($resource, $appointmentType))
            ->values();
        $dependencyQuestions = $appointmentType->questions()
            ->where('is_active', true)
            ->with('options')
            ->get()
            ->filter(fn (AppointmentQuestion $candidate): bool => $candidate->type->hasOptions()
                && ($question === null || ($candidate->position < $question->position
                    && ! hash_equals($candidate->getKey(), $question->getKey()))))
            ->values();

        return [
            'appointmentType' => $appointmentType,
            'question' => $question,
            'questionTypes' => QuestionType::cases(),
            'pricingTypes' => PricingAdjustmentType::cases(),
            'optionPricingTypes' => [
                PricingAdjustmentType::None,
                PricingAdjustmentType::Fixed,
                PricingAdjustmentType::Percentage,
            ],
            'pricingModes' => PricingApplicationMode::cases(),
            'percentageBases' => PricingPercentageBasis::cases(),
            'organization' => $context->organization(),
            'phoneRegions' => $phones->supportedRegions(),
            'dependencyQuestions' => $dependencyQuestions,
            'numericSourceQuestions' => $appointmentType->questions()
                ->where('is_active', true)
                ->where('type', QuestionType::Number->value)
                ->get()
                ->filter(fn (AppointmentQuestion $candidate): bool => $question === null
                    || ($candidate->position < $question->position && ! hash_equals($candidate->getKey(), $question->getKey())))
                ->values(),
            'conditionalResources' => $conditionalResources,
            'conditionalResourceFulfillmentModes' => ConditionalResourceFulfillmentMode::cases(),
        ];
    }

    private function libraryData(AppointmentType $appointmentType, OrganizationContext $context): array
    {
        return [
            'reusableQuestions' => $context->organization()
                ->reusableQuestions()
                ->where('is_active', true)
                ->withCount('options')
                ->orderBy('label')
                ->get(),
            'attachedReusableQuestionIds' => $appointmentType->questions()
                ->whereNotNull('reusable_question_id')
                ->pluck('reusable_question_id'),
        ];
    }

    private function guard(AppointmentType $appointmentType, OrganizationContext $context): void
    {
        abort_unless(hash_equals($appointmentType->organization_id, $context->organization()->getKey()), 404);
        $this->authorize('manage', $appointmentType);
    }

    private function guardQuestion(
        AppointmentType $appointmentType,
        AppointmentQuestion $question,
        OrganizationContext $context,
    ): void {
        $this->guard($appointmentType, $context);
        abort_unless(hash_equals($question->appointment_type_id, $appointmentType->getKey()), 404);
    }
}
