<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The yearly rate of return that brings dated cash flows to zero (money in negative, money out positive),
 * so investments held for different lengths of time can be compared.
 */
final class Xirr
{
    /**
     * @param  list<array{date: CarbonImmutable, amount: float}>  $flows
     */
    public static function rate(array $flows): ?float
    {
        $hasIn = $hasOut = false;

        foreach ($flows as $flow) {
            $hasIn = $hasIn || $flow['amount'] < 0;
            $hasOut = $hasOut || $flow['amount'] > 0;
        }

        if (! $hasIn || ! $hasOut) {
            return null;
        }

        $start = $flows[0]['date'];
        $years = array_map(fn (array $flow) => $start->diffInDays($flow['date']) / 365, $flows);

        if (max($years) < 30 / 365) {
            return null;
        }

        $value = function (float $rate) use ($flows, $years): float {
            $total = 0.0;

            foreach ($flows as $index => $flow) {
                $total += $flow['amount'] / (1 + $rate) ** $years[$index];
            }

            return $total;
        };

        $low = -0.9999;
        $high = 10.0;

        if ($value($low) * $value($high) > 0) {
            return null;
        }

        for ($i = 0; $i < 200; $i++) {
            $mid = ($low + $high) / 2;

            if ($value($low) * $value($mid) <= 0) {
                $high = $mid;
            } else {
                $low = $mid;
            }
        }

        return ($low + $high) / 2 * 100;
    }
}
