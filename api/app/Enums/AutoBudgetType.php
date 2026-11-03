<?php

namespace App\Enums;

enum AutoBudgetType: string
{
    case Reset = 'reset';
    case Rollover = 'rollover';
    case Adjusted = 'adjusted';
}
