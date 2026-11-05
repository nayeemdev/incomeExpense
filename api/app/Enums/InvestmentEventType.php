<?php

namespace App\Enums;

enum InvestmentEventType: string
{
    case Buy = 'buy';
    case Sell = 'sell';
    case Income = 'income';
    case Valuation = 'valuation';
}
