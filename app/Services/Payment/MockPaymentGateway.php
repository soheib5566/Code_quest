<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\DTOs\PaymentResult;
use App\Enums\GatewayScenairo;
use App\Enums\LedgerStatus;
use App\Enums\PayoutStatus;
use App\Exceptions\PaymentGatewayHardFailureException;
use App\Exceptions\PaymentGatewayTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MockPaymentGateway implements PaymentGatewayInterface
{
    private const CACHE_PREFIX = 'mock_bank_tx:';
    private ?GatewayScenairo $forcedScenario = null;


    public function forceScenario(GatewayScenairo $scenario): self
    {
        $this->forcedScenario = $scenario;
        return $this;
    }
    public function transfer(string $key, int $amountInCents, string $iban): PaymentResult
    {
        $exist = $this->findStoredTransaction($key);
        if ($exist != null) {
            return $exist;
        }

        $scenario = $this->pickupRandomScenario();

        return match ($scenario) {
            GatewayScenairo::SUCCESS => $this->handleSuccess(amountInCents: $amountInCents, key: $key),
            GatewayScenairo::HARD_FAILURE => $this->handleError($key),
            default => $this->handleTimeout($key, $amountInCents)
        };
    }

    public function checkStatus(string $key): PaymentResult
    {
        $existing = $this->findStoredTransaction($key);

        if ($existing !== null) {
            if ($existing->status == 'failed') {
                throw new PaymentGatewayHardFailureException($existing->errorMessage);
            }
            return $existing;
        }

        return new PaymentResult(
            transactionId: 'unknown',
            key: $key,
            amountInCents: 0,
            status: 'not_found',
            errorMessage: 'No transaction found for the provided idempotency key.'
        );
    }

    public function handleSuccess(int $amountInCents, string $key): PaymentResult
    {
        $tx = new PaymentResult(
            transactionId: 'bank_tx_' . Str::random(8),
            key: $key,
            amountInCents: $amountInCents,
            status: 'settled'
        );

        $this->storeTransaction(key: $key, tx: $tx);
        return $tx;
    }

    public function handleTimeout(string $key, int $amountInCents): PaymentResult
    {
        $tx = new PaymentResult(
            transactionId: 'bank_tx_' . Str::random(8),
            key: $key,
            amountInCents: $amountInCents,
            status: 'settled'
        );

        $this->storeTransaction(key: $key, tx: $tx);

        throw new PaymentGatewayTimeoutException("CURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received.");
    }

    public function handleError(string $key): never
    {
        $failed_tx = new PaymentResult(
            transactionId: 'bank_tx_' . Str::random(8),
            key: $key,
            amountInCents: 0,
            status: 'failed',
            errorMessage: "INVALID_IBAN: Destination bank rejected the routing number."
        );
        $this->storeTransaction(key: $key, tx: $failed_tx);

        throw new PaymentGatewayHardFailureException($failed_tx->errorMessage);
    }

    public function storeTransaction(string $key, PaymentResult $tx)
    {
        Cache::put(self::CACHE_PREFIX . $key, serialize($tx), now()->addDays(7));
    }

    public function findStoredTransaction(string $key): ?PaymentResult
    {
        $cached = Cache::get(self::CACHE_PREFIX . $key);
        return $cached ? unserialize($cached) : null;
    }

    private function pickupRandomScenario()
    {
        if ($this->forcedScenario != null) {
            return $this->forcedScenario;
        }

        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 70 => GatewayScenairo::SUCCESS,
            $roll <= 85 => GatewayScenairo::HARD_FAILURE,
            default => GatewayScenairo::TIMEOUT_AFTER_SUCCESS
        };
    }
}
