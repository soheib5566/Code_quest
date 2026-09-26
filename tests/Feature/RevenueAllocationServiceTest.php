<?php

namespace Tests\Feature;

use App\Enums\LedgerDirection;
use App\Enums\LedgerStatus;
use App\Enums\LedgerType;
use App\Enums\Role;
use App\Enums\SubscriptionPeriodStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TermPlan;
use App\Models\Course;
use App\Models\CourseEngagement;
use App\Models\LedgerEntry;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\User;
use App\Services\Ledger\RevenueAllocationService;
use App\Services\Ledger\SplitCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RevenueAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RevenueAllocationService(new SplitCalculatorService());
    }

    public function test_it_allocates_subscription_period_revenue_accurately(): void
    {
        $student = User::create([
            'name' => 'Alice Student',
            'email' => 'alice@test.com',
            'role' => Role::USER,
        ]);

        $instructor1 = User::create([
            'name' => 'Bob Instructor',
            'email' => 'bob@test.com',
            'role' => Role::INSTRUCTOR,
            'iban_account' => 'IBAN_BOB',
        ]);

        $instructor2 = User::create([
            'name' => 'Charlie Instructor',
            'email' => 'charlie@test.com',
            'role' => Role::INSTRUCTOR,
            'iban_account' => 'IBAN_CHARLIE',
        ]);

        $plan = Plan::create([
            'name' => 'Monthly Plan',
            'term' => TermPlan::MONTHLY,
            'price_in_cents' => 10000, // $100.00
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'price_paid_in_cents' => 10000,
            'platform_fee_percent' => 30,
            'status' => SubscriptionStatus::ACTIVE,
            'start_at' => now(),
            'end_at' => now()->addMonth(),
        ]);

        $period = SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'period_number' => 1,
            'start_at' => now(),
            'end_at' => now()->addMonth(),
            'gross_amount_in_cents' => 10000,
            'platform_amount_in_cents' => 3000,
            'instructor_pool_in_cents' => 7000,
            'status' => SubscriptionPeriodStatus::OPEN,
        ]);

        $course1 = Course::create([
            'instructor_id' => $instructor1->id,
            'title' => 'Laravel Masterclass',
        ]);

        $course2 = Course::create([
            'instructor_id' => $instructor2->id,
            'title' => 'Database Design',
        ]);

        // 70% watch time for instructor 1, 30% for instructor 2
        CourseEngagement::create([
            'subscription_period_id' => $period->id,
            'student_id' => $student->id,
            'instructor_id' => $instructor1->id,
            'course_id' => $course1->id,
            'seconds_watched' => 700,
        ]);

        CourseEngagement::create([
            'subscription_period_id' => $period->id,
            'student_id' => $student->id,
            'instructor_id' => $instructor2->id,
            'course_id' => $course2->id,
            'seconds_watched' => 300,
        ]);

        // Execute Allocation
        $this->service->allocate($period);

        // 1. Verify Period updated to ALLOCATED
        $this->assertEquals(SubscriptionPeriodStatus::ALLOCATED, $period->fresh()->status);
        $this->assertNotNull($period->fresh()->allocated_at);

        // 2. Verify Platform Fee Ledger Entry (30% of $100 = 3,000 cents)
        $platformEntry = LedgerEntry::where('instructor_id', null)
            ->where('source_id', $period->id)
            ->first();

        $this->assertNotNull($platformEntry);
        $this->assertEquals(3000, $platformEntry->amount_in_cents);
        $this->assertEquals(LedgerStatus::SETTLED, $platformEntry->status);

        // 3. Verify Instructor 1 got 70% of 7,000 = 4,900 cents
        $inst1Entry = LedgerEntry::where('instructor_id', $instructor1->id)->first();
        $this->assertNotNull($inst1Entry);
        $this->assertEquals(4900, $inst1Entry->amount_in_cents);
        $this->assertEquals(LedgerStatus::PAYABLE, $inst1Entry->status);
        $this->assertEquals(LedgerDirection::CREDIT, $inst1Entry->direction);

        // 4. Verify Instructor 2 got 30% of 7,000 = 2,100 cents
        $inst2Entry = LedgerEntry::where('instructor_id', $instructor2->id)->first();
        $this->assertNotNull($inst2Entry);
        $this->assertEquals(2100, $inst2Entry->amount_in_cents);
        $this->assertEquals(LedgerStatus::PAYABLE, $inst2Entry->status);

        // 5. Total cents allocated strictly equals gross amount (3000 + 4900 + 2100 = 10,000)
        $totalCentsAllocated = LedgerEntry::where('source_id', $period->id)->sum('amount_in_cents');
        $this->assertEquals(10000, $totalCentsAllocated);
    }

    public function test_it_handles_unconsumed_breakage_when_student_watches_zero_seconds(): void
    {
        $student = User::create([
            'name' => 'Idle Student',
            'email' => 'idle@test.com',
            'role' => Role::USER,
        ]);

        $plan = Plan::create([
            'name' => 'Monthly Plan',
            'term' => TermPlan::MONTHLY,
            'price_in_cents' => 5000,
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'price_paid_in_cents' => 5000,
            'platform_fee_percent' => 30,
            'status' => SubscriptionStatus::ACTIVE,
            'start_at' => now(),
            'end_at' => now()->addMonth(),
        ]);

        $period = SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'period_number' => 1,
            'start_at' => now(),
            'end_at' => now()->addMonth(),
            'gross_amount_in_cents' => 5000,
            'platform_amount_in_cents' => 1500,
            'instructor_pool_in_cents' => 3500,
            'status' => SubscriptionPeriodStatus::OPEN,
        ]);

        // Student watched 0 seconds (no engagements)
        $this->service->allocate($period);

        // Period should still be marked ALLOCATED
        $this->assertEquals(SubscriptionPeriodStatus::ALLOCATED, $period->fresh()->status);

        // All 5000 cents should be retained by platform as unconsumed breakage
        $breakage = LedgerEntry::where('source_id', $period->id)->first();
        $this->assertNotNull($breakage);
        $this->assertEquals(5000, $breakage->amount_in_cents);
        $this->assertNull($breakage->instructor_id);
        $this->assertStringContainsString('unconsumed_breakage', $breakage->description);

        // Zero instructor entries created
        $this->assertEquals(0, LedgerEntry::whereNotNull('instructor_id')->count());
    }

    public function test_it_is_idempotent_and_will_not_double_allocate_if_called_twice(): void
    {
        $student = User::create([
            'name' => 'Active Student',
            'email' => 'active@test.com',
            'role' => Role::USER,
        ]);

        $instructor = User::create([
            'name' => 'Solo Instructor',
            'email' => 'solo@test.com',
            'role' => Role::INSTRUCTOR,
            'iban_account' => 'IBAN_SOLO',
        ]);

        $plan = Plan::create([
            'name' => 'Monthly Plan',
            'term' => TermPlan::MONTHLY,
            'price_in_cents' => 6000,
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'price_paid_in_cents' => 6000,
            'platform_fee_percent' => 30,
            'status' => SubscriptionStatus::ACTIVE,
            'start_at' => now(),
            'end_at' => now()->addMonth(),
        ]);

        $period = SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'period_number' => 1,
            'start_at' => now(),
            'end_at' => now()->addMonth(),
            'gross_amount_in_cents' => 6000,
            'platform_amount_in_cents' => 1800,
            'instructor_pool_in_cents' => 4200,
            'status' => SubscriptionPeriodStatus::OPEN,
        ]);

        $course = Course::create([
            'instructor_id' => $instructor->id,
            'title' => 'Single Course',
        ]);

        CourseEngagement::create([
            'subscription_period_id' => $period->id,
            'student_id' => $student->id,
            'instructor_id' => $instructor->id,
            'course_id' => $course->id,
            'seconds_watched' => 500,
        ]);

        // First run
        $this->service->allocate($period);
        $countAfterFirstRun = LedgerEntry::count();

        // Second run (simulating overlapping cron, re-trigger, or retry)
        $this->service->allocate($period);
        $countAfterSecondRun = LedgerEntry::count();

        // Must NOT create any new entries on the second run
        $this->assertEquals($countAfterFirstRun, $countAfterSecondRun);
    }
}
