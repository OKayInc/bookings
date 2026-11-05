<?php

namespace App\Support;

use ArrayObject;
use Illuminate\Support\Facades\DB;

/** Read reuse for a single GET/HEAD response, never shared between requests or jobs. */
class PageReadCache
{
    public const ATTRIBUTE = '_appointment_page_reads';

    public function enabled(): bool
    {
        return $this->values() !== null && DB::transactionLevel() === 0;
    }

    public function remember(string $key, callable $resolve): mixed
    {
        $values = $this->values();
        if ($values === null || DB::transactionLevel() > 0) {
            return $resolve();
        }

        if (! $values->offsetExists($key)) {
            $values[$key] = [$resolve()];
        }

        return $values[$key][0];
    }

    public function clear(): void
    {
        $this->values()?->exchangeArray([]);
    }

    private function values(): ?ArrayObject
    {
        if (! app()->bound('request')) {
            return null;
        }

        $values = request()->attributes->get(self::ATTRIBUTE);

        return $values instanceof ArrayObject ? $values : null;
    }
}
