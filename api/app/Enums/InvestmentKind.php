<?php

namespace App\Enums;

enum InvestmentKind: string
{
    case Stock = 'stock';
    case Etf = 'etf';
    case MutualFund = 'mutual_fund';
    case Deposit = 'deposit';
    case SavingsScheme = 'savings_scheme';
    case Private = 'private';
    case Gold = 'gold';
    case Property = 'property';
    case Other = 'other';

    /**
     * Holdings counted in units (shares, fund units, grams) and valued by price per unit.
     */
    public function tracksUnits(): bool
    {
        return in_array($this, [self::Stock, self::Etf, self::MutualFund, self::Gold], true);
    }
}
