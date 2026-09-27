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
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PayoutReconciliationTest extends TestCase
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

    public function test_in_doubt_payout_reconciles_to_paid_when_gateway_confirms_settlement(): void
    {
        // 1. Simulate Gateway Timeout after Success
        $this->gateway->forceScenario(GatewayScenairo::TIMEOUT_AFTER_SUCCESS);

        $entry = LedgerEntry::create([
            'instructor_id'   => $this->instructor->id,
            'source_type'     => User::class,
            'source_id'       => $this->instructor->id,
            'amount_in_cents' => 8000,
            'direction'       => LedgerDirection::CREDIT,
            'type'            => LedgerType::EARNING,
            'status'          => LedgerStatus::PAYABLE,
            'idempotency_key' => 'earning_reconcile_001',
        ]);

        // Run payout job - times out into IN_DOUBT
        (new ProcessInstructorPayoutJob($this->instructor->id, '2026-03'))->handle(app(PayoutProcessorService::class));

        $payout = Payout::first();
        $this->assertEquals(PayoutStatus::IN_DOUBT, $payout->status);
        $this->assertEquals(LedgerStatus::LOCKED, $entry->fresh()->status);

        // 2. Run reconciliation command
        $this->artisan('payouts:reconcile')
            ->expectsOutputToContain('Found 1 in-doubt payouts')
            ->expectsOutputToContain('Reconciliation completed.')
            ->assertSuccessful();

        // 3. Verify Payout resolved to PAID and Ledger resolved to SETTLED
        $payout->refresh();
        $this->assertEquals(PayoutStatus::PAID, $payout->status);
        $this->assertNotNull($payout->external_reference);
        $this->assertNotNull($payout->reconciled_at);
        $this->assertEquals(LedgerStatus::SETTLED, $entry->fresh()->status);
    }

    public function test_in_doubt_payout_reconciles_to_failed_when_gateway_transaction_not_found(): void
    {
        // Create an IN_DOUBT payout with a key unknown to the gateway
        $payout = Payout::create([
            'instructor_id'   => $this->instructor->id,
            'amount_in_cents' => 6000,
            'status'          => PayoutStatus::IN_DOUBT,
            'idempotency_key' => 'payout_ghost_unknown_key',
            'failure_reason'  => 'Gateway timeout',
        ]);

        $entry = LedgerEntry::create([
            'instructor_id'   => $this->instructor->id,
            'source_type'     => User::class,
            'source_id'       => $this->instructor->id,
            'amount_in_cents' => 6000,
            'direction'       => LedgerDirection::CREDIT,
            'type'            => LedgerType::EARNING,
            'status'          => LedgerStatus::LOCKED,
            'payout_id'       => $payout->id,
            'idempotency_key' => 'earning_ghost_001',
        ]);

        // Run reconciliation command
        $this->artisan('payouts:reconcile')
            ->assertSuccessful();

        // Payout should transition to FAILED
        $payout->refresh();
        $this->assertEquals(PayoutStatus::FAILED, $payout->status);
        $this->assertNotNull($payout->reconciled_at);

        // Ledger entry must safely revert to PAYABLE with payout_id cleared
        $entry->refresh();
        $this->assertEquals(LedgerStatus::PAYABLE, $entry->status);
        $this->assertNull($entry->payout_id);
    }

    public function test_process_payouts_command_dispatches_jobs_for_eligible_instructors(): void
    {
        Queue::fake();

        LedgerEntry::create([
            'instructor_id'   => $this->instructor->id,
            'source_type'     => User::class,
            'source_id'       => $this->instructor->id,
            'amount_in_cents' => 4500,
            'direction'       => LedgerDirection::CREDIT,
            'type'            => LedgerType::EARNING,
            'status'          => LedgerStatus::PAYABLE,
            'idempotency_key' => 'earning_dispatch_001',
        ]);

        $this->artisan('payouts:process', ['--period' => '2026-03'])
            ->expectsOutputToContain('Successfully dispatched payout jobs.')
            ->assertSuccessful();

        Queue::assertPushed(ProcessInstructorPayoutJob::class, function ($job) {
            return $job->instructorId === $this->instructor->id && $job->cyclePeriod === '2026-03';
        });
    }
}
