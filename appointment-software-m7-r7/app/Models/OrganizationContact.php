<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OrganizationContact extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id',
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'phone',
        'phone_normalized',
        'phone_verified_at',
        'address_text',
        'google_place_id',
        'address_metadata',
    ];

    protected $hidden = ['id', 'organization_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'address_metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $contact): void {
            $contact->email = trim((string) $contact->email);
            $contact->email_normalized = self::normalizeEmail((string) $contact->email);

            if ($contact->phone !== null) {
                $contact->phone = trim((string) $contact->phone);
            }
        });
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
