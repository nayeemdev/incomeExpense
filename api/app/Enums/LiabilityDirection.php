<?php

namespace App\Enums;

enum LiabilityDirection: string
{
    case Owed = 'owed';
    case Lent = 'lent';
}
