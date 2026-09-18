<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentContractTemplate extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id',
        'appointment_type_id',
        'uploaded_by_person_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'sha256',
        'is_active',
        'active_slot',
        'superseded_at',
    ];

    protected $hidden = ['id', 'organization_id', 'appointment_type_id', 'uploaded_by_person_id', 'path'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_active' => 'boolean',
            'active_slot' => 'integer',
            'superseded_at' => 'datetime',
        ];
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'uploaded_by_person_id');
    }
}
