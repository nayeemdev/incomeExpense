<?php

namespace App\Enums;

enum TransactionType: string
{
    case Withdrawal = 'withdrawal';
    case Deposit = 'deposit';
    case Transfer = 'transfer';
    case OpeningBalance = 'opening_balance';
    case Reconciliation = 'reconciliation';

    /**
     * @return list<self>
     */
    public static function userManaged(): array
    {
        return [self::Withdrawal, self::Deposit, self::Transfer];
    }

    /**
     * @return list<AccountType>
     */
    public function allowedSourceTypes(): array
    {
        return match ($this) {
            self::Withdrawal => [AccountType::Asset, AccountType::Liability],
            self::Deposit => [AccountType::Revenue, AccountType::Liability],
            self::Transfer => [AccountType::Asset],
            self::OpeningBalance => [AccountType::InitialBalance, AccountType::Asset, AccountType::Liability],
            self::Reconciliation => [AccountType::Reconciliation, AccountType::Asset, AccountType::Liability],
        };
    }

    /**
     * @return list<AccountType>
     */
    public function allowedDestinationTypes(): array
    {
        return match ($this) {
            self::Withdrawal => [AccountType::Expense, AccountType::Liability],
            self::Deposit => [AccountType::Asset, AccountType::Liability],
            self::Transfer => [AccountType::Asset],
            self::OpeningBalance => [AccountType::InitialBalance, AccountType::Asset, AccountType::Liability],
            self::Reconciliation => [AccountType::Reconciliation, AccountType::Asset, AccountType::Liability],
        };
    }
}
