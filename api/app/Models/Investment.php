<?php

namespace App\Models;

use App\Enums\InvestmentEventType;
use App\Enums\InvestmentKind;
use App\Enums\RepeatFrequency;
use App\Models\Concerns\BelongsToLedger;
use App\Support\InvestmentPosition;
use App\Support\Xirr;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property string $account_id
 * @property InvestmentKind $kind
 * @property ?string $symbol
 * @property ?string $interest_rate
 * @property ?RepeatFrequency $payout_frequency
 * @property ?string $tax_rate
 * @property ?string $contribution_amount
 * @property ?RepeatFrequency $contribution_frequency
 * @property ?string $contribution_account_id
 * @property ?CarbonImmutable $next_contribution_date
 * @property ?CarbonImmutable $start_date
 * @property ?CarbonImmutable $maturity_date
 * @property ?CarbonImmutable $closed_at
 * @property ?string $notes
 * @property-read Account $account
 * @property-read Collection<int, InvestmentEvent> $events
 */
#[Fillable([
    'account_id', 'kind', 'symbol', 'interest_rate', 'payout_frequency', 'tax_rate', 'start_date', 'maturity_date', 'closed_at', 'notes',
    'contribution_amount', 'contribution_frequency', 'contribution_account_id', 'next_contribution_date',
])]
class Investment extends Model
{
    use BelongsToLedger, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => InvestmentKind::class,
            'interest_rate' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'contribution_frequency' => RepeatFrequency::class,
            'next_contribution_date' => 'immutable_date',
            'payout_frequency' => RepeatFrequency::class,
            'start_date' => 'immutable_date',
            'maturity_date' => 'immutable_date',
            'closed_at' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<InvestmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(InvestmentEvent::class)->orderBy('date')->orderBy('id');
    }

    public function currency(): Currency
    {
        return $this->account->currency ?? throw new LogicException('An investment account always has a currency.');
    }

    public function position(?CarbonImmutable $asOf = null): InvestmentPosition
    {
        return InvestmentPosition::of($this, $asOf ?? CarbonImmutable::today());
    }

    /**
     * The dates a periodic payout is expected on, from the first one after the start date up to
     * the given date, the maturity date or the day it was closed, whichever comes first.
     *
     * @return list<CarbonImmutable>
     */
    public function payoutDates(CarbonImmutable $until): array
    {
        if ($this->payout_frequency === null || $this->start_date === null || $this->interest_rate === null) {
            return [];
        }

        $end = $until;

        foreach ([$this->maturity_date, $this->closed_at] as $limit) {
            if ($limit !== null && $limit->lessThan($end)) {
                $end = $limit;
            }
        }
        $dates = [];

        for ($i = 1; ($date = $this->payout_frequency->add($this->start_date, $i))->lessThanOrEqualTo($end); $i++) {
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * Expected payouts that have not been matched by a recorded payout yet, oldest first. Payouts
     * are matched in order, so the first recorded one covers the first expected date.
     *
     * @return list<CarbonImmutable>
     */
    public function pendingPayoutDates(CarbonImmutable $until): array
    {
        $received = $this->events
            ->filter(fn (InvestmentEvent $event) => $event->type === InvestmentEventType::Income && ! $event->reinvested
                && ($this->start_date === null || $event->date->greaterThanOrEqualTo($this->start_date)))
            ->count();

        return array_slice($this->payoutDates($until), $received);
    }

    /**
     * Installment dates still to come, from the next one up to the given date or maturity.
     *
     * @return list<CarbonImmutable>
     */
    public function contributionDates(CarbonImmutable $until): array
    {
        if ($this->contribution_frequency === null || $this->contribution_amount === null || $this->next_contribution_date === null || $this->closed_at !== null) {
            return [];
        }

        $end = $this->maturity_date !== null && $this->maturity_date->lessThan($until) ? $this->maturity_date : $until;
        $dates = [];

        for ($date = $this->next_contribution_date; $date->lessThanOrEqualTo($end); $date = $this->contribution_frequency->add($date)) {
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function contributionAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'contribution_account_id');
    }

    /**
     * The yearly rate of return so far, counting money put in, taken out, paid out and what is held today.
     */
    public function annualReturn(CarbonImmutable $asOf): ?float
    {
        return Xirr::rate($this->cashFlows($asOf));
    }

    /**
     * Money in (negative) and out (positive) up to the given date, ending with what is held then.
     *
     * @return list<array{date: CarbonImmutable, amount: float}>
     */
    public function cashFlows(CarbonImmutable $asOf): array
    {
        $flows = [];

        foreach ($this->events as $event) {
            if ($event->date->greaterThan($asOf)) {
                break;
            }

            $amount = (float) $event->amount;
            $flow = match ($event->type) {
                InvestmentEventType::Buy => -$amount,
                InvestmentEventType::Sell => $amount,
                InvestmentEventType::Income => $event->reinvested ? 0.0 : $amount,
                InvestmentEventType::Valuation => 0.0,
            };

            if ($flow !== 0.0) {
                $flows[] = ['date' => $event->date, 'amount' => $flow];
            }
        }

        $position = $this->position($asOf);

        if ($position->isOpen()) {
            $flows[] = ['date' => $asOf, 'amount' => (float) $position->value()];
        }

        return $flows;
    }
}
