<?php

namespace App\Contracts;

use App\DTOs\PaymentResult;

interface PaymentGatewayInterface
{
    public function transfer(string $key, int $amountInCents, string $iban): PaymentResult;

    public function checkStatus(string $key): PaymentResult;
}
