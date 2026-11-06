<?php

namespace App\Support;

/** Numeric counters only; never retain SQL, bindings or request contents. */
final class RequestTimings
{
    public const ATTRIBUTE = '_appointment_request_timings';

    public int $queryCount = 0;

    public float $databaseMs = 0;

    public function recordQuery(float $milliseconds): void
    {
        $this->queryCount++;
        $this->databaseMs += $milliseconds;
    }
}
