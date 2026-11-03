<?php

namespace App\Enums;

enum RepetitionType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case NthWeekday = 'ndom';
    case Yearly = 'yearly';
}
