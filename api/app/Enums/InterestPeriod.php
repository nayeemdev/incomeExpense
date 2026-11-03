<?php

namespace App\Enums;

enum InterestPeriod: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
