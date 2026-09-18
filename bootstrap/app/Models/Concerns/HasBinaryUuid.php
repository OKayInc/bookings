<?php

namespace App\Models\Concerns;

use App\Support\Uuid\UuidBinary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasBinaryUuid
{
    public function initializeHasBinaryUuid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    public static function bootHasBinaryUuid(): void
    {
        static::creating(function (Model $model): void {
            $key = $model->getKeyName();

            if (! array_key_exists($key, $model->getAttributes()) || $model->getAttribute($key) === null) {
                $model->setAttribute($key, UuidBinary::toBytes((string) Str::uuid7()));
            }
        });
    }

    public function getUuidAttribute(): ?string
    {
        $key = $this->getKeyName();
        $bytes = $this->getRawOriginal($key) ?? ($this->getAttributes()[$key] ?? null);

        return UuidBinary::fromBytes($bytes);
    }

    public function getRouteKey(): mixed
    {
        return $this->uuid;
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field === $this->getKeyName()) {
            return $query->where($this->qualifyColumn($field), UuidBinary::toBytes((string) $value));
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    public function scopeWhereUuid(Builder $query, string $uuid): Builder
    {
        return $query->where($this->qualifyColumn($this->getKeyName()), UuidBinary::toBytes($uuid));
    }
}
