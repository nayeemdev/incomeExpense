<?php

namespace App\Reports;

use Carbon\CarbonImmutable;

final readonly class Period
{
    public function __construct(public CarbonImmutable $start, public CarbonImmutable $end) {}

    /**
     * Splits the period into buckets of a day, week, month or year.
     *
     * @return list<self>
     */
    public function buckets(string $interval): array
    {
        $buckets = [];
        $cursor = match ($interval) {
            'day' => $this->start,
            'week' => $this->start->startOfWeek(),
            'year' => $this->start->startOfYear(),
            default => $this->start->startOfMonth(),
        };

        while ($cursor->lessThanOrEqualTo($this->end)) {
            $next = match ($interval) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'year' => $cursor->addYear(),
                default => $cursor->addMonth(),
            };
            $buckets[] = new self($cursor->max($this->start), $next->subDay()->min($this->end));
            $cursor = $next;
        }

        return $buckets;
    }

    /**
     * Picks a sensible interval so charts have a readable number of points.
     */
    public function defaultInterval(): string
    {
        $days = $this->start->diffInDays($this->end);

        return match (true) {
            $days <= 45 => 'day',
            $days <= 120 => 'week',
            $days <= 1100 => 'month',
            default => 'year',
        };
    }

    public function key(): string
    {
        return $this->start->toDateString();
    }
}
