<?php

namespace Tests\Unit;

use App\Support\Uuid\UuidBinary;
use PHPUnit\Framework\TestCase;

class UuidBinaryTest extends TestCase
{
    public function test_uuid_round_trip_is_lossless(): void
    {
        $uuid = '019c80da-6cb5-7e72-bb83-f17ca278cf30';
        $bytes = UuidBinary::toBytes($uuid);

        $this->assertSame(16, strlen($bytes));
        $this->assertSame($uuid, UuidBinary::fromBytes($bytes));
    }
}
