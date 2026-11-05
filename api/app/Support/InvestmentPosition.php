<?php

namespace App\Support;

use App\Enums\InvestmentEventType;
use App\Models\Investment;
use Carbon\CarbonImmutable;

/**
 * What an investment holds on a date, worked out by replaying its events in order.
 */
final class InvestmentPosition
{
    public string $units = '0';

    public string $cost = '0';

    public string $invested = '0';

    public string $income = '0';

    public string $realized = '0';

    public ?string $price = null;

    public ?string $valuation = null;

    public ?CarbonImmutable $valuedOn = null;

    private string $accrued = '0';

    private ?CarbonImmutable $accruedTo = null;

    private function __construct(private Investment $investment) {}

    public static function of(Investment $investment, CarbonImmutable $asOf): self
    {
        $position = new self($investment);

        foreach ($investment->events as $event) {
            if ($event->date->greaterThan($asOf)) {
                break;
            }

            $position->accrue($event->date);

            match ($event->type) {
                InvestmentEventType::Buy => $position->buy($event->amount, $event->units, $event->date),
                InvestmentEventType::Sell => $position->sell($event->amount, $event->units, (string) $event->cost),
                InvestmentEventType::Income => $position->income = Money::sum($position->income, $event->amount),
                InvestmentEventType::Valuation => $position->revalue($event->amount, $event->price, $event->date),
            };
        }

        $position->accrue($asOf);

        return $position;
    }

    public function isOpen(): bool
    {
        return Money::of($this->cost) > Money::of(0) || Money::of($this->units) > Money::of(0);
    }

    /**
     * Unit holdings are worth units times the latest price. Anything else is worth its latest
     * valuation, or what was put in plus the interest it has earned so far.
     */
    public function value(): string
    {
        if ($this->investment->kind->tracksUnits()) {
            return $this->price === null ? $this->cost : Money::of($this->units)->mul(Money::of($this->price), Money::SCALE)->value;
        }

        return $this->valuation ?? Money::sum($this->cost, $this->accrued);
    }

    public function gain(): string
    {
        return Money::of($this->value())->sub(Money::of($this->cost))->value;
    }

    /**
     * The cost basis that leaves the investment when selling the given units or, for holdings
     * without units, taking out the given amount.
     */
    public function costOf(string $amount, ?string $units, bool $close): string
    {
        if ($close) {
            return $this->cost;
        }

        if ($this->investment->kind->tracksUnits()) {
            return Money::isZero($this->units) ? '0' : Money::of($this->cost)->mul(Money::of((string) $units), Money::SCALE)->div(Money::of($this->units), Money::SCALE)->value;
        }

        $value = Money::of($this->value());

        return $value <= Money::of($amount) ? $this->cost : Money::of($this->cost)->mul(Money::of($amount), Money::SCALE)->div($value, Money::SCALE)->value;
    }

    /**
     * What one periodic payout is expected to be: the yearly rate on what is invested, split over the periods.
     */
    public function payoutAmount(): ?string
    {
        $investment = $this->investment;

        if ($investment->payout_frequency === null || $investment->interest_rate === null) {
            return null;
        }

        return Money::of($this->cost)
            ->mul(Money::of($investment->interest_rate), Money::SCALE)
            ->div(Money::of(100 * $investment->payout_frequency->perYear()), Money::SCALE)
            ->value;
    }

    private function buy(string $amount, ?string $units, CarbonImmutable $date): void
    {
        $this->cost = Money::sum($this->cost, $amount);
        $this->invested = Money::sum($this->invested, $amount);

        if ($units !== null && ! Money::isZero($units)) {
            $this->units = Money::sum($this->units, $units);
            $this->price = Money::of($amount)->div(Money::of($units), Money::SCALE)->value;
        }

        if ($this->valuation !== null) {
            $this->valuation = Money::sum($this->valuation, $amount);
        }

        $this->accruedTo ??= $date;
    }

    private function sell(string $amount, ?string $units, string $cost): void
    {
        $this->cost = Money::of($this->cost)->sub(Money::of($cost))->value;
        $this->realized = Money::sum($this->realized, Money::of($amount)->sub(Money::of($cost))->value);

        if ($units !== null && ! Money::isZero($units)) {
            $this->units = Money::of($this->units)->sub(Money::of($units))->value;
            $this->price = Money::of($amount)->div(Money::of($units), Money::SCALE)->value;
        }

        if ($this->valuation !== null) {
            $left = Money::of($this->valuation)->sub(Money::of($amount))->value;
            $this->valuation = Money::isNegative($left) ? '0' : $left;
        }

        if (Money::isZero($this->cost) || Money::isNegative($this->cost)) {
            $this->cost = '0';
            $this->accrued = '0';
            $this->valuation = null;
        } else {
            $this->accrued = Money::of($this->accrued)->mul(Money::of($this->cost), Money::SCALE)->div(Money::of(Money::sum($this->cost, $cost)), Money::SCALE)->value;
        }
    }

    private function revalue(string $amount, ?string $price, CarbonImmutable $date): void
    {
        $this->valuedOn = $date;

        if ($price !== null) {
            $this->price = $price;

            return;
        }

        $this->valuation = $amount;
        $this->accrued = '0';
    }

    /**
     * Interest paid out at maturity (a fixed deposit, for one) builds up as simple interest on what is invested.
     */
    private function accrue(CarbonImmutable $until): void
    {
        $investment = $this->investment;

        if ($investment->interest_rate === null || $investment->payout_frequency !== null || $this->accruedTo === null || $this->valuation !== null) {
            return;
        }

        $until = $investment->maturity_date !== null && $investment->maturity_date->lessThan($until) ? $investment->maturity_date : $until;
        $days = (int) $this->accruedTo->diffInDays($until, absolute: false);

        if ($days <= 0) {
            return;
        }

        $this->accrued = Money::sum($this->accrued, Money::of($this->cost)
            ->mul(Money::of($investment->interest_rate), Money::SCALE)
            ->mul(Money::of($days), Money::SCALE)
            ->div(Money::of(36500), Money::SCALE)
            ->value);
        $this->accruedTo = $until;
    }
}
