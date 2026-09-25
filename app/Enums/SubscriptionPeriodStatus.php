<?php

namespace App\Enums;

enum SubscriptionPeriodStatus: string
{
    case PENDING = 'pending';
    case OPEN = 'open';
    case ALLOCATED = 'allocated';
    case CANCELLED = 'cancelled';
}
