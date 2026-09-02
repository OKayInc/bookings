<?php

namespace App\Models;

use App\Enums\PaymentRuleMatchType;
use App\Enums\PaymentRuleType;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentRule extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'rule_type', 'match_type', 'pattern', 'pattern_normalized', 'note', 'is_active',
    ];

    protected $hidden = ['id', 'organization_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'rule_type' => PaymentRuleType::class,
            'match_type' => PaymentRuleMatchType::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $rule): void {
            $pattern = Str::lower(trim((string) $rule->pattern));
            if ($rule->match_type === PaymentRuleMatchType::Domain) {
                $pattern = ltrim($pattern, '@');
            }
            $rule->pattern = $pattern;
            $rule->pattern_normalized = $pattern;
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function matches(string $email): bool
    {
        $normalized = Str::lower(trim($email));
        if ($this->match_type === PaymentRuleMatchType::Email) {
            return hash_equals($this->pattern_normalized, $normalized);
        }

        $domain = Str::afterLast($normalized, '@');

        return $domain !== '' && hash_equals($this->pattern_normalized, $domain);
    }
}
