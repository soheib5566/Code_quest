<?php

namespace App\Enums;

enum GatewayScenairo: string
{
    case SUCCESS = 'success';
    case HARD_FAILURE = 'hard_failure';
    case TIMEOUT_AFTER_SUCCESS = 'timeout_after_success';
}
