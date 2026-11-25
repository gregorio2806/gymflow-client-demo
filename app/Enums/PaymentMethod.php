<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case GCash = 'gcash';
    case Maya = 'maya';
    case BankTransfer = 'bank-transfer';
}
