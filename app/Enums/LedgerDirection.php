<?php

namespace App\Enums;

enum LedgerDirection: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';
}
