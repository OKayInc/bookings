<?php

namespace App\Domain\Questionnaires;

use App\Enums\QuestionType;
use App\Models\AppointmentType;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class QuestionnaireSubmissionService
{
    public function __construct(
        private QuestionnairePricingService $pricing,
        private QuestionVisibilityService $visibility,
        private EmailDomainValidator $emails,
        private PhoneValidationService $phones,
        private AddressValidationService $addresses,
        private DrivingDistanceService $drivingDistances,
        private DrivingDistancePricingService $drivingDistancePricing,
    ) {}

    public function quote(
        AppointmentType $type,
        ?int $duration,
        array $answers,
        ?CarbonImmutable $startsAtUtc = null,
        ?CarbonImmutable $nowUtc = null,
    ): QuestionnaireQuote {
        $visibleQuestions = $this->visibility->visibleQuestions($type, $answers);
        $visibleAnswers = $this->visibleAnswers($visibleQuestions, $answers);
        $distanceMeters = [];

        foreach ($visibleQuestions as $question) {
            if ($question->type !== QuestionType::Address
                || ! data_get($question->configuration, 'distance_pricing.enabled', false)) {
                continue;
            }
            $value = $visibleAnswers[$question->uuid] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if (! is_string($value) || strlen($value) > 1000) {
                throw new \InvalidArgumentException('Enter a valid address for '.$question->label.'.');
            }

            try {
                $distanceMeters[$question->uuid] = $this->drivingDistances->between(
                    (string) data_get($question->configuration, 'distance_pricing.origin_address', ''),
                    trim($value),
                );
            } catch (RuntimeException $exception) {
                throw new \InvalidArgumentException($exception->getMessage(), 0, $exception);
            }
        }

        return $this->pricing->quote($type, $duration, $visibleAnswers, $startsAtUtc, $nowUtc, $distanceMeters);
    }

    public function validateForBooking(
        Request $request,
        AppointmentType $type,
        ?int $duration,
        ?CarbonImmutable $startsAtUtc = null,
        ?CarbonImmutable $nowUtc = null,
    ): QuestionnaireSubmission {
        $submittedAnswers = (array) $request->input('answers', []);
        $visibleQuestions = $this->visibility->visibleQuestions($type, $submittedAnswers);
        $rules = [];

        foreach ($visibleQuestions as $question) {
            $key = 'answers.'.$question->uuid;
            $required = $question->is_required ? 'required' : 'nullable';
            $rules[$key] = match ($question->type) {
                QuestionType::Text, QuestionType::Textarea => [$required, 'string', 'max:'.config('questionnaire.max_text_length', 20000)],
                QuestionType::Email => [$required, 'email:rfc', 'max:254'],
                QuestionType::Telephone, QuestionType::Address => [$required, 'string', 'max:1000'],
                QuestionType::Date => [$required, 'date_format:Y-m-d'],
                QuestionType::Time => [$required, 'date_format:H:i'],
                QuestionType::DateTime => [$required, 'date_format:Y-m-d\\TH:i'],
                QuestionType::Number => array_values(array_filter([
                    $required,
                    $question->pricing_adjustment_type->value !== 'none' ? 'integer' : 'numeric',
                    isset($question->configuration['min']) ? 'min:'.$question->configuration['min'] : null,
                    isset($question->configuration['max']) ? 'max:'.$question->configuration['max'] : null,
                ])),
                QuestionType::Radio, QuestionType::Select => [
                    $required,
                    Rule::in($question->options->where('is_active', true)->pluck('uuid')->all()),
                ],
                QuestionType::Checkboxes => [$required, 'array', ...($question->is_required ? ['min:1'] : [])],
                QuestionType::File => ['nullable'],
            };

            if ($question->type === QuestionType::Checkboxes) {
                $rules[$key.'.*'] = [
                    'string',
                    'distinct',
                    Rule::in($question->options->where('is_active', true)->pluck('uuid')->all()),
                ];
            }
            if ($question->type === QuestionType::File) {
                $fileKey = 'answer_files.'.$question->uuid;
                $max = (int) data_get($question->configuration, 'max_count', config('questionnaire.max_files_per_question', 20));
                $extensions = (array) data_get($question->configuration, 'extensions', config('questionnaire.file_extensions'));
                $kilobytes = (int) data_get($question->configuration, 'max_kilobytes', config('questionnaire.max_file_kilobytes'));
                $rules[$fileKey] = [$question->is_required ? 'required' : 'nullable', 'array', 'max:'.$max, ...($question->is_required ? ['min:1'] : [])];
                $rules[$fileKey.'.*'] = ['file', 'mimes:'.implode(',', $extensions), 'max:'.$kilobytes];
            }
        }

        $validated = Validator::make($request->all(), $rules)->validate();
        $raw = (array) ($validated['answers'] ?? []);
        $fileBag = $request->file('answer_files', []);
        $answers = [];
        $distanceMeters = [];

        foreach ($visibleQuestions as $question) {
            $value = $raw[$question->uuid] ?? null;
            $normalized = null;
            $files = [];

            if ($question->type->hasOptions()) {
                $ids = $question->type === QuestionType::Checkboxes ? (array) $value : ($value ? [(string) $value] : []);
                $selected = $question->options
                    ->where('is_active', true)
                    ->filter(fn ($option) => in_array($option->uuid, $ids, true))
                    ->sortBy('position');
                $value = $question->type === QuestionType::Checkboxes
                    ? $selected->map(fn ($option) => ['uuid' => $option->uuid, 'value' => $option->value, 'label' => $option->label])->values()->all()
                    : ($selected->first() ? ['uuid' => $selected->first()->uuid, 'value' => $selected->first()->value, 'label' => $selected->first()->label] : null);
            } elseif ($question->type === QuestionType::Email && $value) {
                if (! $this->emails->exists($value)) {
                    throw ValidationException::withMessages(['answers.'.$question->uuid => 'The email domain does not appear to exist.']);
                }
                $normalized = ['email' => strtolower(trim($value))];
            } elseif ($question->type === QuestionType::Telephone && $value) {
                try {
                    $normalized = ['e164' => $this->phones->validateAndNormalize($value, data_get($question->configuration, 'region'))];
                } catch (RuntimeException $exception) {
                    throw ValidationException::withMessages(['answers.'.$question->uuid => $exception->getMessage()]);
                }
            } elseif ($question->type === QuestionType::Address && $value) {
                try {
                    $normalized = $this->addresses->validate($value, data_get($question->configuration, 'region'));
                    if (data_get($question->configuration, 'distance_pricing.enabled', false)) {
                        $meters = $this->drivingDistances->between(
                            (string) data_get($question->configuration, 'distance_pricing.origin_address', ''),
                            trim((string) $value),
                        );
                        $distanceMeters[$question->uuid] = $meters;
                        $unit = (string) data_get($question->configuration, 'distance_pricing.unit', 'kilometer');
                        $normalized['driving_distance'] = $this->drivingDistancePricing->measurement($meters, $unit);
                    }
                } catch (RuntimeException $exception) {
                    throw ValidationException::withMessages(['answers.'.$question->uuid => $exception->getMessage()]);
                }
            } elseif ($question->type === QuestionType::File) {
                $files = array_values((array) ($fileBag[$question->uuid] ?? []));
                $value = array_map(fn (UploadedFile $file) => $file->getClientOriginalName(), $files);
            }

            $answers[] = ['question' => $question, 'value' => $value, 'normalized' => $normalized, 'files' => $files];
        }

        try {
            $quote = $this->pricing->quote($type, $duration, $raw, $startsAtUtc, $nowUtc, $distanceMeters);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['questionnaire' => $exception->getMessage()]);
        }

        return new QuestionnaireSubmission($answers, $quote);
    }

    private function visibleAnswers(iterable $visibleQuestions, array $answers): array
    {
        $visible = [];
        foreach ($visibleQuestions as $question) {
            if (array_key_exists($question->uuid, $answers)) {
                $visible[$question->uuid] = $answers[$question->uuid];
            }
        }

        return $visible;
    }
}
