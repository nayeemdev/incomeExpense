<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Expense = 'expense';
    case Revenue = 'revenue';
    case Liability = 'liability';
    case InitialBalance = 'initial_balance';
    case Reconciliation = 'reconciliation';

    public function isOwn(): bool
    {
        return $this === self::Asset || $this === self::Liability;
    }

    public function isSystem(): bool
    {
        return $this === self::InitialBalance || $this === self::Reconciliation;
    }

    /**
     * @return list<self>
     */
    public static function userManaged(): array
    {
        return [self::Asset, self::Expense, self::Revenue, self::Liability];
    }
}
