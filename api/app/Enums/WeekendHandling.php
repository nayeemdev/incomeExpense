<?php

namespace App\Enums;

enum WeekendHandling: string
{
    case Keep = 'keep';
    case Skip = 'skip';
    case Before = 'before';
    case After = 'after';
}
