<?php

namespace Tests\Feature;

use App\Enums\LedgerDirection;
use App\Enums\LedgerStatus;
use App\Enums\LedgerType;
use App\Enums\Role;
use App\Enums\SubscriptionPeriodStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TermPlan;
use App\Models\LedgerEntry;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\User;
use App\Services\Subscription\SubscriptionRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRefundTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionRefundService $refundService;
    private User $student;
    private User $instructor;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refundService = new SubscriptionRefundService();

        $this->student = User::create([
            'name' => 'Alice Student',
            'email' => 'alice.refund@test.com',
            'role' => Role::USER,
        ]);

        $this->instructor = User::create([
            'name' => 'Bob Instructor',
            'email' => 'bob.refund@test.com',
            'role' => Role::INSTRUCTOR,
            'iban_account' => 'IBAN_BOB',
        ]);

        $this->plan = Plan::create([
            'name' => 'Annual Plan',
            'term' => TermPlan::ANNUAL,
            'price_in_cents' => 12000, // $120.00
            'is_active' => true,
        ]);
    }

    public function test_it_cancels_future_unearned_periods_without_clawing_back_instructors(): void
    {
        $subscription = Subscription::create([
            'student_id' => $this->student->id,
            'plan_id' => $this->plan->id,
            'price_paid_in_cents' => 12000,
            'platform_fee_percent' => 30,
            'status' => SubscriptionStatus::ACTIVE,
            'start_at' => now(),
            'end_at' => now()->addYear(),
        ]);

        // Period 1: Already allocated to instructor ($7.00 = 700 cents earned)
        $period1 = SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'period_number' => 1,
            'start_at' => now(),
            'end_at' => now()->addMonth(),
            'gross_amount_in_cents' => 1000,
            'platform_amount_in_cents' => 300,
            'instructor_pool_in_cents' => 700,
            'status' => SubscriptionPeriodStatus::ALLOCATED,
            'allocated_at' => now(),
        ]);

        LedgerEntry::create([
            'instructor_id'   => $this->instructor->id,
            'source_type'     => SubscriptionPeriod::class,
            'source_id'       => $period1->id,
            'amount_in_cents' => 700,
            'direction'       => LedgerDirection::CREDIT,
            'type'            => LedgerType::EARNING,
            'status'          => LedgerStatus::PAYABLE,
            'idempotency_key' => 'earning_period_1_refund_test',
        ]);

        // Periods 2-12: Pending future months ($10.00 each)
        for ($i = 2; $i <= 12; $i++) {
            SubscriptionPeriod::create([
                'subscription_id' => $subscription->id,
                'period_number' => $i,
                'start_at' => now()->addMonths($i - 1),
                'end_at' => now()->addMonths($i),
                'gross_amount_in_cents' => 1000,
                'platform_amount_in_cents' => 300,
                'instructor_pool_in_cents' => 700,
                'status' => SubscriptionPeriodStatus::PENDING,
            ]);
        }

        // Student leaves at Month 2 -> prorated refund of unearned months
        $refundedCents = $this->refundService->refund($subscription);

        // 1. Student receives remaining 11 months refunded (11 * $10.00 = 11,000 cents)
        $this->assertEquals(11000, $refundedCents);

        // 2. Periods 2-12 are CANCELLED
        $this->assertEquals(
            11,
            $subscription->periods()->where('status', SubscriptionPeriodStatus::CANCELLED)->count()
        );

        // 3. Period 1 remains ALLOCATED
        $this->assertEquals(SubscriptionPeriodStatus::ALLOCATED, $period1->fresh()->status);

        // 4. Zero clawbacks issued (Instructor earned month 1 fairly)
        $this->assertEquals(0, LedgerEntry::where('type', LedgerType::REFUND_CLAWBACK)->count());

        // 5. Subscription marked REFUNDED
        $this->assertEquals(SubscriptionStatus::REFUNDED, $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->cancelled_at);
    }

    public function test_it_issues_clawbacks_if_settled_period_is_refunded_under_guarantee(): void
    {
        $subscription = Subscription::create([
            'student_id' => $this->student->id,
            'plan_id' => $this->plan->id,
            'price_paid_in_cents' => 12000,
            'platform_fee_percent' => 30,
            'status' => SubscriptionStatus::ACTIVE,
            'start_at' => now(),
            'end_at' => now()->addYear(),
        ]);

        // Period 1 was allocated
        $period1 = SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'period_number' => 1,
            'start_at' => now(),
            'end_at' => now()->addMonth(),
            'gross_amount_in_cents' => 1000,
            'platform_amount_in_cents' => 300,
            'instructor_pool_in_cents' => 700,
            'status' => SubscriptionPeriodStatus::ALLOCATED,
            'allocated_at' => now(),
        ]);

        LedgerEntry::create([
            'instructor_id'   => $this->instructor->id,
            'source_type'     => SubscriptionPeriod::class,
            'source_id'       => $period1->id,
            'amount_in_cents' => 700,
            'direction'       => LedgerDirection::CREDIT,
            'type'            => LedgerType::EARNING,
            'status'          => LedgerStatus::PAYABLE,
            'idempotency_key' => 'earning_period_1_guarantee_test',
        ]);

        // Periods 2-12 PENDING
        for ($i = 2; $i <= 12; $i++) {
            SubscriptionPeriod::create([
                'subscription_id' => $subscription->id,
                'period_number' => $i,
                'start_at' => now()->addMonths($i - 1),
                'end_at' => now()->addMonths($i),
                'gross_amount_in_cents' => 1000,
                'platform_amount_in_cents' => 300,
                'instructor_pool_in_cents' => 700,
                'status' => SubscriptionPeriodStatus::PENDING,
            ]);
        }

        // Full money-back guarantee refund (clawbackAllocated = true)
        $refundedCents = $this->refundService->refund($subscription, clawbackAllocated: true);

        // 1. All 12,000 cents refunded
        $this->assertEquals(12000, $refundedCents);

        // 2. All 12 periods are now CANCELLED
        $this->assertEquals(12, $subscription->periods()->where('status', SubscriptionPeriodStatus::CANCELLED)->count());

        // 3. Instructor received a DEBIT clawback of 700 cents
        $this->assertDatabaseHas('ledger_entries', [
            'instructor_id'   => $this->instructor->id,
            'amount_in_cents' => 700,
            'direction'       => LedgerDirection::DEBIT,
            'type'            => LedgerType::REFUND_CLAWBACK,
            'status'          => LedgerStatus::SETTLED,
        ]);
    }
}
