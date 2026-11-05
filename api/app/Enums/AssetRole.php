<?php

namespace App\Enums;

enum AssetRole: string
{
    case Default = 'default';
    case Shared = 'shared';
    case Savings = 'savings';
    case CashWallet = 'cash_wallet';
    case CreditCard = 'credit_card';
    case Investment = 'investment';
}
