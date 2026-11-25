<?php

namespace App\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly'; // pay 10 months, get 12
}
