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

class ReusableQuestion extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'type', 'label', 'description', 'placeholder', 'default_is_required', 'is_active', 'configuration',
        'pricing_adjustment_type', 'pricing_application_mode', 'pricing_amount_minor', 'pricing_percentage_bps',
        'pricing_percentage_basis', 'pricing_included_units',
    ];

    protected $hidden = ['id', 'organization_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'default_is_required' => 'boolean',
            'is_active' => 'boolean',
            'configuration' => 'array',
            'pricing_adjustment_type' => PricingAdjustmentType::class,
            'pricing_application_mode' => PricingApplicationMode::class,
            'pricing_amount_minor' => 'integer',
            'pricing_percentage_bps' => 'integer',
            'pricing_percentage_basis' => PricingPercentageBasis::class,
            'pricing_included_units' => 'integer',
        ];
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): ?string => is_string($value)
                ? app(RichTextSanitizer::class)->sanitize($value)
                : null,
        );
    }

    public function safeDescriptionHtml(): ?string
    {
        return app(RichTextSanitizer::class)->sanitize($this->description);
    }

    public function descriptionPlainText(): string
    {
        return app(RichTextSanitizer::class)->toPlainText($this->description);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ReusableQuestionOption::class)
            ->orderBy('position')
            ->orderByRaw('LOWER(label)')
            ->orderBy('label');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AppointmentQuestion::class);
    }
}
