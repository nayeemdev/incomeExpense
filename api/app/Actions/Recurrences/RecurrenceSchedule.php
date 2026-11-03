<?php

namespace App\Actions\Recurrences;

use App\Enums\RepetitionType;
use App\Enums\WeekendHandling;
use App\Models\Recurrence;
use App\Models\RecurrenceRepetition;
use Carbon\CarbonImmutable;

class RecurrenceSchedule
{
    /**
     * All dates the recurrence fires on between $from and $to (inclusive), honouring its
     * end date and maximum number of repetitions.
     *
     * @return list<CarbonImmutable>
     */
    public function between(Recurrence $recurrence, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $end = $recurrence->repeat_until !== null && $recurrence->repeat_until->lessThan($to) ? $recurrence->repeat_until : $to;
        $dates = [];

        foreach ($recurrence->repetitions as $repetition) {
            foreach ($this->candidates($repetition, $recurrence->first_date, $end) as $date) {
                $adjusted = $this->applyWeekend($repetition->weekend, $date);

                if ($adjusted !== null) {
                    $dates[$adjusted->toDateString()] = $adjusted;
                }
            }
        }

        ksort($dates);
        $dates = array_values($dates);

        if ($recurrence->max_occurrences !== null) {
            $dates = array_slice($dates, 0, $recurrence->max_occurrences);
        }

        return array_values(array_filter(
            $dates,
            fn (CarbonImmutable $date) => $date->betweenIncluded($from, $to) && $date->greaterThanOrEqualTo($recurrence->first_date),
        ));
    }

    /**
     * @return list<CarbonImmutable>
     */
    public function upcoming(Recurrence $recurrence, CarbonImmutable $from, int $count): array
    {
        $horizon = $from->addYears(2);

        return array_slice($this->between($recurrence, $from, $horizon), 0, $count);
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function candidates(RecurrenceRepetition $repetition, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $step = $repetition->skip + 1;
        $dates = [];
        $date = $this->first($repetition, $start);

        for ($index = 0; $date !== null && $date->lessThanOrEqualTo($end); $index++) {
            if ($date->greaterThanOrEqualTo($start)) {
                $dates[] = $date;
            }

            $date = $this->nth($repetition, $start, $index + 1, $step);
        }

        return $dates;
    }

    private function first(RecurrenceRepetition $repetition, CarbonImmutable $start): ?CarbonImmutable
    {
        return $this->nth($repetition, $start, 0, 1);
    }

    /**
     * The occurrence number $index (counted in steps) on or after $start.
     */
    private function nth(RecurrenceRepetition $repetition, CarbonImmutable $start, int $index, int $step): ?CarbonImmutable
    {
        $offset = $index * $step;

        return match ($repetition->type) {
            RepetitionType::Daily => $start->addDays($offset),
            RepetitionType::Weekly => $this->firstWeekday($start, (int) $repetition->moment)->addWeeks($offset),
            RepetitionType::Monthly => $this->monthDay($start, (int) $repetition->moment, $offset),
            RepetitionType::NthWeekday => $this->nthWeekdayOfMonth($start, $repetition->moment, $offset),
            RepetitionType::Yearly => $this->yearDay($start, $repetition->moment, $offset),
        };
    }

    private function firstWeekday(CarbonImmutable $start, int $isoWeekday): CarbonImmutable
    {
        $difference = ($isoWeekday - $start->dayOfWeekIso + 7) % 7;

        return $start->addDays($difference);
    }

    private function monthDay(CarbonImmutable $start, int $day, int $offset): CarbonImmutable
    {
        $month = $start->startOfMonth();

        if ($start->day > min($day, $start->daysInMonth)) {
            $month = $month->addMonth();
        }

        $month = $month->addMonths($offset);

        return $month->setDay(min($day, $month->daysInMonth));
    }

    private function nthWeekdayOfMonth(CarbonImmutable $start, string $moment, int $offset): ?CarbonImmutable
    {
        [$nth, $weekday] = array_map('intval', explode(',', $moment)) + [1, 1];
        $find = function (CarbonImmutable $month) use ($nth, $weekday): ?CarbonImmutable {
            $date = $this->firstWeekday($month->startOfMonth(), $weekday)->addWeeks($nth - 1);

            return $date->month === $month->month ? $date : null;
        };

        $month = $start->startOfMonth();
        $first = $find($month);

        if ($first === null || $first->lessThan($start)) {
            $month = $month->addMonth();
        }

        return $find($month->addMonths($offset));
    }

    private function yearDay(CarbonImmutable $start, string $moment, int $offset): CarbonImmutable
    {
        [$month, $day] = array_map('intval', explode('-', $moment)) + [1, 1];
        $firstOfMonth = $start->setDate($start->year, $month, 1);
        $date = $firstOfMonth->setDay(min($day, $firstOfMonth->daysInMonth));

        if ($date->lessThan($start)) {
            $date = $date->addYearNoOverflow();
        }

        return $date->addYearsNoOverflow($offset);
    }

    private function applyWeekend(WeekendHandling $handling, CarbonImmutable $date): ?CarbonImmutable
    {
        if (! $date->isWeekend()) {
            return $date;
        }

        return match ($handling) {
            WeekendHandling::Keep => $date,
            WeekendHandling::Skip => null,
            WeekendHandling::Before => $date->previousWeekday(),
            WeekendHandling::After => $date->nextWeekday(),
        };
    }
}
