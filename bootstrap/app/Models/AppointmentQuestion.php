<?php

namespace App\Models;

use App\Enums\PricingAdjustmentType;
use App\Enums\PricingApplicationMode;
use App\Enums\PricingPercentageBasis;
use App\Enums\QuestionType;
use App\Models\Concerns\HasBinaryUuid;
use App\Support\Html\RichTextSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AppointmentQuestion extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'appointment_type_id','reusable_question_id','type','label','description','placeholder','is_required','is_active','position','configuration',
        'pricing_adjustment_type','pricing_application_mode','pricing_amount_minor','pricing_percentage_bps','pricing_percentage_basis','pricing_included_units',
    ];
    protected $hidden = ['id','appointment_type_id','reusable_question_id'];
    protected $appends = ['uuid'];
    protected function casts(): array { return [
        'type'=>QuestionType::class,'is_required'=>'boolean','is_active'=>'boolean','position'=>'integer','configuration'=>'array',
        'pricing_adjustment_type'=>PricingAdjustmentType::class,'pricing_application_mode'=>PricingApplicationMode::class,
        'pricing_amount_minor'=>'integer','pricing_percentage_bps'=>'integer','pricing_percentage_basis'=>PricingPercentageBasis::class,'pricing_included_units'=>'integer',
    ]; }
    protected function description(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): ?string => is_string($value)
                ? app(RichTextSanitizer::class)->sanitize($value)
                : null,
        );
    }
    public function safeDescriptionHtml(): ?string { return app(RichTextSanitizer::class)->sanitize($this->description); }
    public function descriptionPlainText(): string { return app(RichTextSanitizer::class)->toPlainText($this->description); }
    public function appointmentType(): BelongsTo { return $this->belongsTo(AppointmentType::class); }
    public function reusableQuestion(): BelongsTo { return $this->belongsTo(ReusableQuestion::class); }
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)
            ->orderBy('position')
            ->orderByRaw('LOWER(label)')
            ->orderBy('label');
    }
    public function answers(): HasMany { return $this->hasMany(BookingAnswer::class); }
    public function visibilityConditions(): HasMany { return $this->hasMany(AppointmentQuestionVisibilityCondition::class)->orderBy('position'); }
    public function dependentVisibilityConditions(): HasMany { return $this->hasMany(AppointmentQuestionVisibilityCondition::class, 'source_question_id'); }
    public function numericConstraints(): HasMany { return $this->hasMany(AppointmentQuestionNumericConstraint::class)->orderBy('position'); }
    public function dependentNumericConstraints(): HasMany { return $this->hasMany(AppointmentQuestionNumericConstraint::class, 'source_question_id'); }
    public function resourceRequirementRule(): HasOne { return $this->hasOne(AppointmentQuestionResourceRule::class); }
}
