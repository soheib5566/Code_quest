<?php

namespace App\Enums;

enum LedgerStatus: string
{
    case PAYABLE = 'payable';
    case LOCKED = 'locked';
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';
}
