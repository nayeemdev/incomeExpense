<?php

namespace App\Enums;

enum LiabilityType: string
{
    case Loan = 'loan';
    case Debt = 'debt';
    case Mortgage = 'mortgage';
}
