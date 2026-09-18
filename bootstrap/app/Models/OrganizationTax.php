<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationTax extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id',
        'name',
        'rate_millionths',
        'position',
    ];

    protected $hidden = ['id', 'organization_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'rate_millionths' => 'integer',
            'position' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
