<?php

namespace App\RuleEngine;

use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

enum TriggerType: string
{
    case DescriptionIs = 'description_is';
    case DescriptionContains = 'description_contains';
    case DescriptionStarts = 'description_starts';
    case DescriptionEnds = 'description_ends';
    case AmountIs = 'amount_is';
    case AmountMore = 'amount_more';
    case AmountLess = 'amount_less';
    case SourceAccountIs = 'source_account_is';
    case SourceAccountContains = 'source_account_contains';
    case DestinationAccountIs = 'destination_account_is';
    case DestinationAccountContains = 'destination_account_contains';
    case CategoryIs = 'category_is';
    case BudgetIs = 'budget_is';
    case BillIs = 'bill_is';
    case TagIs = 'tag_is';
    case HasAnyTag = 'has_any_tag';
    case HasNoCategory = 'has_no_category';
    case HasNoBudget = 'has_no_budget';
    case NotesContain = 'notes_contain';
    case CurrencyIs = 'currency_is';
    case TransactionType = 'transaction_type';
    case DateBefore = 'date_before';
    case DateAfter = 'date_after';

    public function needsValue(): bool
    {
        return ! in_array($this, [self::HasAnyTag, self::HasNoCategory, self::HasNoBudget], true);
    }

    /**
     * Expects the transaction to have its accounts, category, budget, bill, currency and tags loaded.
     */
    public function matches(Transaction $transaction, ?string $value): bool
    {
        $value = (string) $value;
        $text = fn (?string $subject) => Str::lower(trim((string) $subject));
        $needle = $text($value);

        return match ($this) {
            self::DescriptionIs => $text($transaction->description) === $needle,
            self::DescriptionContains => str_contains($text($transaction->description), $needle),
            self::DescriptionStarts => str_starts_with($text($transaction->description), $needle),
            self::DescriptionEnds => str_ends_with($text($transaction->description), $needle),
            self::AmountIs => is_numeric($value) && Money::of($transaction->amount) == Money::of($value),
            self::AmountMore => is_numeric($value) && Money::of($transaction->amount) > Money::of($value),
            self::AmountLess => is_numeric($value) && Money::of($transaction->amount) < Money::of($value),
            self::SourceAccountIs => $text($transaction->sourceAccount->name) === $needle,
            self::SourceAccountContains => str_contains($text($transaction->sourceAccount->name), $needle),
            self::DestinationAccountIs => $text($transaction->destinationAccount->name) === $needle,
            self::DestinationAccountContains => str_contains($text($transaction->destinationAccount->name), $needle),
            self::CategoryIs => $text($transaction->category?->name) === $needle,
            self::BudgetIs => $text($transaction->budget?->name) === $needle,
            self::BillIs => $text($transaction->bill?->name) === $needle,
            self::TagIs => $transaction->tags->contains(fn ($tag) => $text($tag->name) === $needle),
            self::HasAnyTag => $transaction->tags->isNotEmpty(),
            self::HasNoCategory => $transaction->category_id === null,
            self::HasNoBudget => $transaction->budget_id === null,
            self::NotesContain => str_contains($text($transaction->notes), $needle),
            self::CurrencyIs => $text($transaction->currency->code) === $needle,
            self::TransactionType => $transaction->type->value === $needle,
            self::DateBefore => $this->date($value)?->greaterThan($transaction->date) ?? false,
            self::DateAfter => $this->date($value)?->lessThan($transaction->date) ?? false,
        };
    }

    private function date(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
