<?php

namespace Tests\Unit;

use App\Enums\GatewayScenairo;
use App\Exceptions\PaymentGatewayHardFailureException;
use App\Exceptions\PaymentGatewayTimeoutException;
use App\Services\Payment\MockPaymentGateway;
use Tests\TestCase;

class MockPaymentGatewayTest extends TestCase
{
    private MockPaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new MockPaymentGateway();
    }

    public function test_it_disburses_successfully_in_happy_path(): void
    {
        $this->gateway->forceScenario(GatewayScenairo::SUCCESS);

        $result = $this->gateway->transfer('idem_001', 5000, 'IBAN_VALID');

        $this->assertEquals('settled', $result->status);
        $this->assertStringStartsWith('bank_tx_', $result->transactionId);
    }

    public function test_it_throws_hard_failure_for_invalid_account(): void
    {
        $this->gateway->forceScenario(GatewayScenairo::HARD_FAILURE);

        $this->expectException(PaymentGatewayHardFailureException::class);
        $this->gateway->transfer('idem_002', 5000,  'IBAN_INVALID');
    }

    public function test_it_moves_money_even_when_timing_out(): void
    {
        $this->gateway->forceScenario(GatewayScenairo::TIMEOUT_AFTER_SUCCESS);
        $idempotencyKey = 'idem_zombie_999';

        try {
            $this->gateway->transfer($idempotencyKey, 10000, 'IBAN_VALID');
            $this->fail('Expected PaymentGatewayTimeoutException was not thrown.');
        } catch (PaymentGatewayTimeoutException $e) {
            $this->assertStringContainsString('timed out', $e->getMessage());
        }

        $statusCheck = $this->gateway->checkStatus($idempotencyKey);

        $this->assertEquals('settled', $statusCheck->status);
        $this->assertEquals(10000, $statusCheck->amountInCents);
        $this->assertNotEmpty($statusCheck->transactionId);
    }

    public function test_transfers_with_same_idempotency_key_return_cached_record(): void
    {
        $this->gateway->forceScenario(GatewayScenairo::SUCCESS);
        $first = $this->gateway->transfer('idem_repeat', 2500,  'IBAN_VALID');

        $second = $this->gateway->transfer('idem_repeat', 2500,  'IBAN_VALID');

        $this->assertEquals($first->transactionId, $second->transactionId);
    }
}
