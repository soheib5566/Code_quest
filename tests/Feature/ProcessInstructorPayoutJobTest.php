<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\GatewayScenairo;
use App\Enums\LedgerDirection;
use App\Enums\LedgerStatus;
use App\Enums\LedgerType;
use App\Enums\PayoutStatus;
use App\Jobs\ProcessInstructorPayoutJob;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\User;
use App\Services\Payment\MockPaymentGateway;
use App\Services\Payout\PayoutProcessorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessInstructorPayoutJobTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private MockPaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::factory()->create();

        $this->gateway = new MockPaymentGateway();
        $this->app->instance(PaymentGatewayInterface::class, $this->gateway);
    }

    public function test_payout_succeeds_and_marks_ledger_settled(): void
    {
        $this->gateway->forceScenario(GatewayScenairo::SUCCESS);

        LedgerEntry::create([
            'instructor_id'   => $this->instructor->id,
            'source_type'     => User::class,
            'source_id'       => $this->instructor->id,
            'amount_in_cents' => 5000,
            'direction'       => LedgerDirection::CREDIT,
            'type'            => LedgerType::EARNING,
            'status'          => LedgerStatus::PAYABLE,
            'idempotency_key' => 'earning_test_001',
            'description'     => 'Earnings for testing payout success',
        ]);

        (new ProcessInstructorPayoutJob($this->instructor->id, '2026-03'))->handle(app(PayoutProcessorService::class));

        $this->assertDatabaseHas('payouts', [
            'instructor_id'   => $this->instructor->id,
            'status'          => PayoutStatus::PAID,
            'amount_in_cents' => 5000,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'instructor_id' => $this->instructor->id,
            'status'        => LedgerStatus::SETTLED,
        ]);
    }

    public function test_payout_timeout_places_record_in_doubt_and_keeps_ledger_locked(): void
    {
        $this->gateway->forceScenario(GatewayScenairo::TIMEOUT_AFTER_SUCCESS);

        LedgerEntry::create([
            'instructor_id'   => $this->instructor->id,
            'source_type'     => User::class,
            'source_id'       => $this->instructor->id,
            'amount_in_cents' => 7500,
            'direction'       => LedgerDirection::CREDIT,
            'type'            => LedgerType::EARNING,
            'status'          => LedgerStatus::PAYABLE,
            'idempotency_key' => 'earning_test_002',
            'description'     => 'Earnings for testing payout timeout',
        ]);

        (new ProcessInstructorPayoutJob($this->instructor->id, '2026-03'))->handle(app(PayoutProcessorService::class));

        $this->assertDatabaseHas('payouts', [
            'instructor_id' => $this->instructor->id,
            'status'        => PayoutStatus::IN_DOUBT,
        ]);

        // Ledger must NOT revert to payable, preventing duplicate payment runs
        $this->assertDatabaseHas('ledger_entries', [
            'instructor_id' => $this->instructor->id,
            'status'        => LedgerStatus::LOCKED,
        ]);
    }

    public function test_running_same_job_twice_never_double_pays(): void
    {
        $this->gateway->forceScenario(GatewayScenairo::SUCCESS);

        LedgerEntry::create([
            'instructor_id'   => $this->instructor->id,
            'source_type'     => User::class,
            'source_id'       => $this->instructor->id,
            'amount_in_cents' => 10000,
            'direction'       => LedgerDirection::CREDIT,
            'type'            => LedgerType::EARNING,
            'status'          => LedgerStatus::PAYABLE,
            'idempotency_key' => 'earning_test_003',
            'description'     => 'Earnings for testing duplicate payout prevention',
        ]);

        $service = app(PayoutProcessorService::class);

        // Run 1: Normal execution
        (new ProcessInstructorPayoutJob($this->instructor->id, '2026-03'))->handle($service);

        // Run 2: Duplicate queued attempt
        (new ProcessInstructorPayoutJob($this->instructor->id, '2026-03'))->handle($service);

        // Assert only 1 Payout record exists
        $this->assertCount(1, Payout::all());
        $this->assertEquals(PayoutStatus::PAID, Payout::first()->status);
    }
}
