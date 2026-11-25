<?php

namespace App\Enums;

enum GymRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
    case Coach = 'coach';
    case BranchManager = 'branch-manager';

    public function usesStaffPlace(): bool
    {
        return in_array($this, [self::Staff, self::Coach], true);
    }
}
