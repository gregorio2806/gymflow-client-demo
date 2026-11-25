<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing'; // new gyms, until billing exists or the platform admin activates them
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
}
