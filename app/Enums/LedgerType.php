<?php

namespace App\Enums;

enum LedgerType: string
{
    case EARNING = 'earning';
    case PAYOUT = 'payout';
    case REFUND = 'refund';
}
