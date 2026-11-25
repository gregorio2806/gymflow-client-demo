<?php

namespace App\Enums;

enum MembershipState: string
{
    case Active = 'active';
    case ExpiringSoon = 'expiring-soon';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::ExpiringSoon => 'Expiring soon',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
            self::Scheduled => 'Scheduled',
        };
    }
}
