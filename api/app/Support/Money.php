<?php

namespace App\Support;

use BcMath\Number;
use InvalidArgumentException;

final class Money
{
    public const int SCALE = 12;

    public static function of(string|int $value): Number
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Invalid amount [{$value}].");
        }

        return new Number($value);
    }

    public static function round(string|int $value, int $decimals): string
    {
        return self::of($value)->round($decimals)->value;
    }

    public static function negate(string|int $value): string
    {
        return self::of(0)->sub(self::of($value))->value;
    }

    public static function sum(string|int ...$values): string
    {
        $total = self::of(0);

        foreach ($values as $value) {
            $total = $total->add(self::of($value));
        }

        return $total->value;
    }

    public static function isNegative(string|int $value): bool
    {
        return self::of($value) < self::of(0);
    }

    public static function isZero(string|int $value): bool
    {
        return self::of($value) == self::of(0);
    }

    public static function abs(string|int $value): string
    {
        return self::isNegative($value) ? self::negate($value) : self::of($value)->value;
    }
}
