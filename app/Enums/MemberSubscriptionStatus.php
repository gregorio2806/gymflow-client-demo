<?php

namespace App\Enums;

enum MemberSubscriptionStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
