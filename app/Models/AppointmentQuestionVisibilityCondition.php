<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
