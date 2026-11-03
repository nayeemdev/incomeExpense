<?php

namespace App\Enums;

enum LedgerRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canEdit(): bool
    {
        return $this !== self::Viewer;
    }

    public function canManage(): bool
    {
        return $this === self::Owner;
    }
}
