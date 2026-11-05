<?php

namespace App\Support;

use App\Enums\InvestmentEventType;
use App\Models\Investment;
use App\Models\InvestmentEvent;
use Carbon\CarbonImmutable;

/**
 * Where the return of an investment is heading: every payout from the start (recorded ones at what
 * arrived, later ones at the expected amount) with the running total, the date that total covers what
 * was put in, installments still to pay, and the value expected at maturity.
 */
final class InvestmentRoadmap
{
    /**
     * @return array<string, mixed>|null
     */
    public static function for(Investment $investment, CarbonImmutable $today): ?array
    {
        if ($investment->interest_rate === null || $investment->closed_at !== null) {
            return null;
        }

        $decimals = $investment->currency()->decimal_places;
        $position = $investment->position($today);
        $horizon = $today->addYears(config()->integer('ledgerfly.investments.roadmap_years'));
        $end = $investment->maturity_date ?? $horizon->addYears(config()->integer('ledgerfly.investments.break_even_years'));
        $received = $investment->events
            ->filter(fn (InvestmentEvent $event) => $event->type === InvestmentEventType::Income && ! $event->reinvested
                && ($investment->start_date === null || $event->date->greaterThanOrEqualTo($investment->start_date)))
            ->values();
        $expected = $position->payoutAmount();
        $cumulative = '0';
        $breakEven = null;
        $points = [];

        foreach ($investment->payoutDates($end) as $index => $date) {
            if ($investment->maturity_date === null && $date->greaterThan($horizon) && $breakEven !== null) {
                break;
            }

            $event = $received->get($index);
            $amount = $event !== null ? $event->amount : (string) $expected;
            $cumulative = Money::sum($cumulative, $amount);

            if ($breakEven === null && ! Money::isZero($position->invested) && Money::of($cumulative) >= Money::of($position->invested)) {
                $breakEven = $date->toDateString();
            }

            $points[] = [
                'date' => $date->toDateString(),
                'amount' => Money::round($amount, $decimals),
                'cumulative' => Money::round($cumulative, $decimals),
                'status' => $event !== null ? 'received' : ($date->lessThanOrEqualTo($today) ? 'due' : 'expected'),
            ];
        }

        $installments = $investment->contributionDates($investment->maturity_date ?? $horizon);
        $toPay = Money::of((string) ($investment->contribution_amount ?? '0'))->mul(count($installments), Money::SCALE)->value;
        $maturityValue = null;
        $maturityGain = null;

        if ($investment->maturity_date !== null) {
            $maturityValue = $investment->position($investment->maturity_date)->value();

            foreach ($installments as $date) {
                $days = (int) $date->diffInDays($investment->maturity_date);
                $maturityValue = Money::sum($maturityValue, Money::of((string) $investment->contribution_amount)
                    ->mul(Money::of(36500)->add(Money::of($position->netRate())->mul($days, Money::SCALE)), Money::SCALE)
                    ->div(Money::of(36500), Money::SCALE)
                    ->value);
            }

            $maturityGain = Money::of($maturityValue)->sub(Money::of(Money::sum($position->cost, $toPay)))->value;
        }

        return [
            'end' => ($points === [] ? ($investment->maturity_date ?? $horizon) : CarbonImmutable::parse(end($points)['date']))->toDateString(),
            'points' => $points,
            'total_return' => Money::round($cumulative, $decimals),
            'break_even' => $breakEven,
            'installments_left' => count($installments),
            'installments_total' => Money::round($toPay, $decimals),
            'maturity_value' => $maturityValue !== null ? Money::round($maturityValue, $decimals) : null,
            'maturity_gain' => $maturityGain !== null ? Money::round($maturityGain, $decimals) : null,
        ];
    }
}
