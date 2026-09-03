<?php

namespace App\Models;

use App\Enums\ConditionalResourceFulfillmentMode;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AppointmentQuestionResourceRule extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'appointment_question_id',
        'trigger_option_id',
        'unavailable_default_option_id',
        'group_name',
        'fulfillment_mode',
    ];

    protected $hidden = [
        'id',
        'appointment_question_id',
        'trigger_option_id',
        'unavailable_default_option_id',
    ];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'fulfillment_mode' => ConditionalResourceFulfillmentMode::class,
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AppointmentQuestion::class, 'appointment_question_id');
    }

    public function triggerOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'trigger_option_id');
    }

    public function unavailableDefaultOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'unavailable_default_option_id');
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(
            Resource::class,
            'appointment_question_resource_rule_resources',
            'resource_rule_id',
            'resource_id',
        );
    }
}
