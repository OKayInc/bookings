<?php

namespace App\Domain\Api;

use App\Models\Organization;
use App\Models\User;

class ApiKeyService
{
    public function regenerate(User|Organization $subject): string
    {
        $key = bin2hex(random_bytes(32));
        $subject->forceFill(['api_key_hash' => hash('sha256', $key), 'api_key_created_at' => now()])->save();
        return $key;
    }

    public function revoke(User|Organization $subject): void
    {
        $subject->forceFill(['api_key_hash' => null, 'api_key_created_at' => null])->save();
    }
}
