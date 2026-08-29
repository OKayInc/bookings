<?php

namespace App\Http\Controllers;

use App\Domain\Money\MoneyService;
use App\Domain\Questionnaires\PercentageService;
use App\Domain\Questionnaires\PhoneValidationService;
use App\Domain\Questionnaires\ReusableQuestionService;
use App\Enums\PricingAdjustmentType;
use App\Enums\PricingApplicationMode;
use App\Enums\PricingPercentageBasis;
use App\Enums\QuestionType;
use App\Http\Requests\StoreAppointmentQuestionRequest;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
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
        $appointmentType->load(['questions.options', 'questions.reusableQuestion']);

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
    ): RedirectResponse {
        $this->guard($appointmentType, $context);
        $data = $request->validated();

        try {
            DB::transaction(function () use ($appointmentType, $data, $request, $context, $money, $percent, $reusableQuestions): void {
                $question = $appointmentType->questions()->create(
                    $this->questionData($data, $request, $context, $money, $percent, $appointmentType),
                );
                $this->syncOptions($question, $data, $context, $money, $percent);
                $reusableQuestions->createFromAttachment($question, $context->organization());
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['pricing' => $exception->getMessage()]);
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
        $question->load(['options', 'reusableQuestion']);

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
    ): RedirectResponse {
        $this->guardQuestion($appointmentType, $question, $context);
        $data = $request->validated();

        try {
            DB::transaction(function () use ($question, $data, $request, $context, $money, $percent, $appointmentType, $reusableQuestions): void {
                $question->update($this->questionData($data, $request, $context, $money, $percent, $appointmentType));
                $this->syncOptions($question, $data, $context, $money, $percent);

                if ($request->boolean('update_reusable_question')) {
                    $reusableQuestions->updateFromAttachment($question);
                }
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['pricing' => $exception->getMessage()]);
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
            'pricing_application_mode' => $data['pricing_application_mode'] ?? PricingApplicationMode::PerUnit->value,
            'pricing_amount_minor' => $pricing === PricingAdjustmentType::Fixed
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
    ): void {
        if (! $question->type->hasOptions()) {
            $question->options()->delete();

            return;
        }

        $question->options()->delete();
        $usedValues = [];

        foreach (array_values($data['options'] ?? []) as $index => $option) {
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

            $question->options()->create([
                'label' => $label,
                'value' => $value,
                'position' => $index + 1,
                'is_active' => true,
                'pricing_adjustment_type' => $pricing->value,
                'pricing_amount_minor' => $pricing === PricingAdjustmentType::Fixed
                    ? $money->parse($option['pricing_amount'] ?? '0', $context->organization()->currency)
                    : null,
                'pricing_percentage_bps' => $pricing === PricingAdjustmentType::Percentage
                    ? $percent->parseToBasisPoints($option['pricing_percentage'] ?? '0')
                    : null,
                'pricing_percentage_basis' => $option['pricing_percentage_basis'] ?? PricingPercentageBasis::BasePrice->value,
            ]);
        }
    }

    private function formData(
        AppointmentType $appointmentType,
        ?AppointmentQuestion $question,
        OrganizationContext $context,
        PhoneValidationService $phones,
    ): array {
        return [
            'appointmentType' => $appointmentType,
            'question' => $question,
            'questionTypes' => QuestionType::cases(),
            'pricingTypes' => PricingAdjustmentType::cases(),
            'pricingModes' => PricingApplicationMode::cases(),
            'percentageBases' => PricingPercentageBasis::cases(),
            'organization' => $context->organization(),
            'phoneRegions' => $phones->supportedRegions(),
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
