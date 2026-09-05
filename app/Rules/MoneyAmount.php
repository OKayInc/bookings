<?php

namespace App\Rules;

use App\Domain\Money\MoneyService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class MoneyAmount implements ValidationRule
{
    public function __construct(
        private readonly string $currency,
        private readonly bool $allowZero = false,
    )
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        try {
            $minor = app(MoneyService::class)->parse((string) $value, $this->currency);
            if ($minor < 0 || (! $this->allowZero && $minor === 0)) {
                $fail($this->allowZero ? 'The amount cannot be negative.' : 'The amount must be greater than zero.');
            }
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
