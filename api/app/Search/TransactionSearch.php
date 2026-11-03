<?php

namespace App\Search;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Filters transactions with a small query language, for example:
 * coffee category:"Eating out" amount_more:5 -tag:work date_after:2026-01-01 has:attachments
 */
class TransactionSearch
{
    public const array KEYS = [
        'description', 'description_is', 'description_starts', 'description_ends',
        'amount', 'amount_more', 'amount_less',
        'date', 'date_before', 'date_after',
        'account', 'source', 'destination',
        'category', 'budget', 'bill', 'tag',
        'type', 'notes', 'currency', 'has',
    ];

    public const array HAS = ['attachments', 'category', 'budget', 'bill', 'tags', 'notes'];

    /**
     * @return list<SearchToken>
     */
    public function parse(string $query): array
    {
        preg_match_all('/(-?)(?:([a-z_]+):)?(?:"([^"]*)"|(\S+))/i', $query, $matches, PREG_SET_ORDER);
        $tokens = [];

        foreach ($matches as $match) {
            $key = strtolower($match[2] ?? '');
            $value = ($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? '');

            if ($key !== '' && ! in_array($key, self::KEYS, true)) {
                $value = "{$key}:{$value}";
                $key = '';
            }

            if (trim($value) === '') {
                continue;
            }

            $tokens[] = new SearchToken($key === '' ? 'description' : $key, trim($value), $match[1] === '-');
        }

        return $tokens;
    }

    /**
     * @param  Builder<Transaction>  $query
     * @param  list<SearchToken>  $tokens
     */
    public function apply(Builder $query, array $tokens): void
    {
        foreach ($tokens as $token) {
            $constraint = fn (Builder $query) => $this->constrain($query, $token);
            $token->negated ? $query->whereNot($constraint) : $query->where($constraint);
        }
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    private function constrain(Builder $query, SearchToken $token): void
    {
        $value = $token->value;
        $like = '%'.addcslashes($value, '%_\\').'%';
        $named = fn (Builder $query) => $query->whereLike('name', $like);

        match ($token->key) {
            'description' => $query->whereLike('transactions.description', $like),
            'description_is' => $query->where('transactions.description', $value),
            'description_starts' => $query->whereLike('transactions.description', addcslashes($value, '%_\\').'%'),
            'description_ends' => $query->whereLike('transactions.description', '%'.addcslashes($value, '%_\\')),
            'amount' => $query->where('transactions.amount', $this->number($value)),
            'amount_more' => $query->where('transactions.amount', '>', $this->number($value)),
            'amount_less' => $query->where('transactions.amount', '<', $this->number($value)),
            'date' => $this->date($query, $value),
            'date_before' => $query->where('transactions.date', '<', $this->day($value)),
            'date_after' => $query->where('transactions.date', '>', $this->day($value)),
            'account' => $query->where(fn (Builder $query) => $query
                ->whereHas('sourceAccount', $named)
                ->orWhereHas('destinationAccount', $named)),
            'source' => $query->whereHas('sourceAccount', $named),
            'destination' => $query->whereHas('destinationAccount', $named),
            'category' => $query->whereHas('category', $named),
            'budget' => $query->whereHas('budget', $named),
            'bill' => $query->whereHas('bill', $named),
            'tag' => $query->whereHas('tags', $named),
            'type' => $query->where('transactions.type', TransactionType::tryFrom(strtolower($value))->value ?? ''),
            'notes' => $query->whereLike('transactions.notes', $like),
            'currency' => $query->whereHas('currency', fn (Builder $query) => $query->where('code', strtoupper($value))),
            'has' => $this->has($query, strtolower($value)),
            default => null,
        };
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    private function has(Builder $query, string $what): void
    {
        match ($what) {
            'attachments' => $query->whereHas('group', fn (Builder $query) => $query->whereHas('attachments')),
            'category' => $query->whereNotNull('transactions.category_id'),
            'budget' => $query->whereNotNull('transactions.budget_id'),
            'bill' => $query->whereNotNull('transactions.bill_id'),
            'tags' => $query->whereHas('tags'),
            'notes' => $query->whereNotNull('transactions.notes')->where('transactions.notes', '!=', ''),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Accepts a day (2026-03-15), a month (2026-03) or a year (2026).
     *
     * @param  Builder<Transaction>  $query
     */
    private function date(Builder $query, string $value): void
    {
        if (preg_match('/^\d{4}$/', $value)) {
            $query->whereYear('transactions.date', (int) $value);
        } elseif (preg_match('/^(\d{4})-(\d{2})$/', $value, $parts)) {
            $query->whereYear('transactions.date', (int) $parts[1])->whereMonth('transactions.date', (int) $parts[2]);
        } else {
            $query->where('transactions.date', $this->day($value));
        }
    }

    private function day(string $value): string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return '0000-00-00';
        }
    }

    private function number(string $value): string
    {
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? $value : '-1';
    }
}
