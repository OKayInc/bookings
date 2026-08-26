<?php

namespace App\Models;

use App\Enums\ContractReviewStatus;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingContractSubmission extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'booking_id', 'contract_template_id', 'reviewed_by_person_id',
        'status', 'review_notes', 'submitted_at_utc', 'reviewed_at_utc',
    ];

    protected $hidden = ['id', 'organization_id', 'booking_id', 'contract_template_id', 'reviewed_by_person_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'status' => ContractReviewStatus::class,
            'submitted_at_utc' => 'immutable_datetime',
            'reviewed_at_utc' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(AppointmentContractTemplate::class, 'contract_template_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'reviewed_by_person_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(BookingContractFile::class)->orderBy('position');
    }
}
