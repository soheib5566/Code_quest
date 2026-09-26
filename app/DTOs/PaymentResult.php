<?php

namespace App\DTOs;

final readonly class PaymentResult
{
    public function __construct(
        public ?string $transactionId,
        public string $key,
        public int $amountInCents,
        public string $status,
        public ?string $errorMessage = null
    ) {
        //
    }
}
