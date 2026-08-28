<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstUnsafeTestDatabase();
    }

    private function guardAgainstUnsafeTestDatabase(): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");
        $normalized = strtolower($database);

        if ($driver !== 'mariadb') {
            throw new LogicException(
                "The Appointment Software test suite must run against MariaDB; '{$driver}' is configured."
            );
        }

        if (! str_contains($normalized, 'test')) {
            throw new LogicException(
                "Refusing to run destructive tests against database '{$database}'. " .
                'Configure a dedicated test database whose name contains the word test.'
            );
        }
    }
}
