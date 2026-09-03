<?php

namespace App\Models;

use App\Enums\PricingAdjustmentType;
use App\Enums\PricingPercentageBasis;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QuestionOption extends Model
{
    use HasBinaryUuid;
    protected $fillable=['appointment_question_id','label','value','position','is_active','pricing_adjustment_type','pricing_amount_minor','pricing_percentage_bps','pricing_percentage_basis'];
    protected $hidden=['id','appointment_question_id']; protected $appends=['uuid'];
    protected function casts(): array { return ['position'=>'integer','is_active'=>'boolean','pricing_adjustment_type'=>PricingAdjustmentType::class,'pricing_amount_minor'=>'integer','pricing_percentage_bps'=>'integer','pricing_percentage_basis'=>PricingPercentageBasis::class]; }
    public function question(): BelongsTo { return $this->belongsTo(AppointmentQuestion::class,'appointment_question_id'); }
    public function visibilityConditions(): HasMany { return $this->hasMany(AppointmentQuestionVisibilityCondition::class); }
    public function matchingVisibilityConditions(): BelongsToMany
    {
        return $this->belongsToMany(
            AppointmentQuestionVisibilityCondition::class,
            'appointment_question_visibility_condition_options',
            'question_option_id',
            'visibility_condition_id',
        );
    }
    public function triggeredResourceRequirementRule(): HasOne
    {
        return $this->hasOne(AppointmentQuestionResourceRule::class, 'trigger_option_id');
    }
    public function unavailableDefaultResourceRequirementRule(): HasOne
    {
        return $this->hasOne(AppointmentQuestionResourceRule::class, 'unavailable_default_option_id');
    }
}
