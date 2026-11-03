<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum RepeatFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYear = 'half_year';
    case Yearly = 'yearly';

    public function add(CarbonImmutable $date, int $times = 1): CarbonImmutable
    {
        return match ($this) {
            self::Daily => $date->addDays($times),
            self::Weekly => $date->addWeeks($times),
            self::Monthly => $date->addMonthsNoOverflow($times),
            self::Quarterly => $date->addMonthsNoOverflow(3 * $times),
            self::HalfYear => $date->addMonthsNoOverflow(6 * $times),
            self::Yearly => $date->addYearsNoOverflow($times),
        };
    }

    public function startOf(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Daily => $date->startOfDay(),
            self::Weekly => $date->startOfWeek(),
            self::Monthly => $date->startOfMonth(),
            self::Quarterly => $date->startOfQuarter(),
            self::HalfYear => $date->month <= 6 ? $date->startOfYear() : $date->setDate($date->year, 7, 1)->startOfDay(),
            self::Yearly => $date->startOfYear(),
        };
    }

    public function endOf(CarbonImmutable $start): CarbonImmutable
    {
        return $this->add($this->startOf($start))->subDay();
    }
}
