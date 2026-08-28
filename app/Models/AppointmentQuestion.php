<?php

namespace App\Models;

use App\Enums\PricingAdjustmentType;
use App\Enums\PricingApplicationMode;
use App\Enums\PricingPercentageBasis;
use App\Enums\QuestionType;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public function appointmentType(): BelongsTo { return $this->belongsTo(AppointmentType::class); }
    public function reusableQuestion(): BelongsTo { return $this->belongsTo(ReusableQuestion::class); }
    public function options(): HasMany { return $this->hasMany(QuestionOption::class)->orderBy('position'); }
    public function answers(): HasMany { return $this->hasMany(BookingAnswer::class); }
}
