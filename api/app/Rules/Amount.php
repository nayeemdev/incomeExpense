<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Amount implements ValidationRule
{
    public function __construct(private bool $signed = false) {}

    public static function positive(): self
    {
        return new self;
    }

    public static function signed(): self
    {
        return new self(signed: true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = $this->signed ? '/^-?\d{1,20}(\.\d{1,12})?$/' : '/^\d{1,20}(\.\d{1,12})?$/';

        if (! is_scalar($value) || ! preg_match($pattern, (string) $value)) {
            $fail(__('The :attribute must be a valid amount.'));

            return;
        }

        if (! $this->signed && Money::isZero((string) $value)) {
            $fail(__('The :attribute must be greater than zero.'));
        }
    }
}
