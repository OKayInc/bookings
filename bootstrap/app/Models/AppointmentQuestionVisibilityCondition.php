<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class AppointmentQuestionVisibilityCondition extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'appointment_question_id',
        'source_question_id',
        'question_option_id',
        'boolean_operator',
        'position',
    ];

    protected $hidden = [
        'id',
        'appointment_question_id',
        'source_question_id',
        'question_option_id',
    ];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AppointmentQuestion::class, 'appointment_question_id');
    }

    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(AppointmentQuestion::class, 'source_question_id');
    }

    public function expectedOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'question_option_id');
    }

    public function expectedOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            QuestionOption::class,
            'appointment_question_visibility_condition_options',
            'visibility_condition_id',
            'question_option_id',
        )->orderBy('question_options.position');
    }

    /**
     * @return Collection<int, QuestionOption>
     */
    public function optionsForMatching(): Collection
    {
        $options = $this->relationLoaded('expectedOptions')
            ? $this->expectedOptions
            : $this->expectedOptions()->get();

        if ($options->isNotEmpty()) {
            return $options->values();
        }

        $fallback = $this->relationLoaded('expectedOption')
            ? $this->expectedOption
            : $this->expectedOption()->first();

        return $fallback === null ? collect() : collect([$fallback]);
    }

    /**
     * @return array<int, string>
     */
    public function expectedOptionUuids(): array
    {
        return $this->optionsForMatching()->pluck('uuid')->values()->all();
    }
}
