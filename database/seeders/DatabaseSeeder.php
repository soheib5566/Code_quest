<?php

namespace Database\Seeders;

use App\Enums\LedgerDirection;
use App\Enums\LedgerStatus;
use App\Enums\LedgerType;
use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Enums\SubscriptionPeriodStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Course;
use App\Models\CourseEngagement;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Default Admin User for Filament Dashboard Access
        User::firstOrCreate(
            ['email' => 'admin@lms.test'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'role' => Role::USER,
                'iban_account' => null,
            ]
        );

        // 2. Seed Subscription Plans & Instructors with Courses
        $this->call([
            PlanSeeder::class,
            InstructorSeeder::class,
        ]);

        $plans = Plan::all();
        $instructors = User::where('role', Role::INSTRUCTOR)->get();
        $courses = Course::all();

        // 3. Seed Students & Subscriptions with Accounting Periods
        for ($i = 1; $i <= 5; $i++) {
            $student = User::firstOrCreate(
                ['email' => "student{$i}@lms.test"],
                [
                    'name' => "Student {$i}",
                    'password' => Hash::make('password'),
                    'role' => Role::USER,
                ]
            );

            $plan = $plans[$i % $plans->count()];
            $duration = $plan->term->durationInMonths();

            $subscription = Subscription::create([
                'student_id' => $student->id,
                'plan_id' => $plan->id,
                'price_paid_in_cents' => $plan->price_in_cents,
                'platform_fee_percent' => 30,
                'status' => SubscriptionStatus::ACTIVE,
                'start_at' => now()->startOfMonth(),
                'end_at' => now()->startOfMonth()->addMonths($duration),
                'payment_reference' => 'sub_seed_' . Str::random(10),
            ]);

            // Create monthly periods for this subscription
            $monthlyGross = (int) round($plan->price_in_cents / $duration);
            $monthlyPlatform = (int) round($monthlyGross * 0.30);
            $monthlyPool = $monthlyGross - $monthlyPlatform;

            for ($p = 1; $p <= $duration; $p++) {
                $period = SubscriptionPeriod::create([
                    'subscription_id' => $subscription->id,
                    'period_number' => $p,
                    'start_at' => now()->startOfMonth()->addMonths($p - 1),
                    'end_at' => now()->startOfMonth()->addMonths($p),
                    'gross_amount_in_cents' => $monthlyGross,
                    'platform_amount_in_cents' => $monthlyPlatform,
                    'instructor_pool_in_cents' => $monthlyPool,
                    'status' => $p === 1 ? SubscriptionPeriodStatus::ALLOCATED : SubscriptionPeriodStatus::PENDING,
                    'allocated_at' => $p === 1 ? now() : null,
                ]);

                // Create course engagements for period 1
                if ($p === 1) {
                    foreach ($instructors as $instructor) {
                        $instructorCourse = $courses->firstWhere('instructor_id', $instructor->id);
                        if ($instructorCourse) {
                            CourseEngagement::create([
                                'subscription_period_id' => $period->id,
                                'student_id' => $student->id,
                                'instructor_id' => $instructor->id,
                                'course_id' => $instructorCourse->id,
                                'seconds_watched' => rand(300, 3600),
                            ]);
                        }
                    }
                }
            }
        }

        // 4. Seed Realistic Immutable Ledger Entries & Historical Payouts for Instructors
        foreach ($instructors as $index => $instructor) {
            // A. Settled Historical Earnings & Completed Payout
            $historicalPayout = Payout::create([
                'instructor_id' => $instructor->id,
                'amount_in_cents' => 15000 + ($index * 2500),
                'status' => PayoutStatus::PAID,
                'idempotency_key' => 'payout_' . $instructor->id . '_2026-01',
                'external_reference' => 'gw_settled_' . Str::random(12),
                'attempts' => 1,
                'paid_at' => now()->subMonths(1),
                'reconciled_at' => now()->subMonths(1),
            ]);

            LedgerEntry::create([
                'instructor_id' => $instructor->id,
                'type' => LedgerType::EARNING,
                'direction' => LedgerDirection::CREDIT,
                'amount_in_cents' => $historicalPayout->amount_in_cents,
                'status' => LedgerStatus::SETTLED,
                'source_type' => SubscriptionPeriod::class,
                'source_id' => 1,
                'payout_id' => $historicalPayout->id,
                'idempotency_key' => 'alloc_' . Str::random(16),
                'description' => 'Allocated earnings for January 2026',
            ]);

            // B. Outstanding PAYABLE earnings (ready for payouts:process)
            LedgerEntry::create([
                'instructor_id' => $instructor->id,
                'type' => LedgerType::EARNING,
                'direction' => LedgerDirection::CREDIT,
                'amount_in_cents' => 8500 + ($index * 1200),
                'status' => LedgerStatus::PAYABLE,
                'source_type' => SubscriptionPeriod::class,
                'source_id' => 1,
                'payout_id' => null,
                'idempotency_key' => 'alloc_' . Str::random(16),
                'description' => 'Allocated earnings for current accounting period',
            ]);

            // C. Special Cases for testing: Instructor 1 has an IN_DOUBT payout, Instructor 2 has a FAILED payout
            if ($index === 0) {
                // In-Doubt payout for reconciliation testing
                $inDoubtPayout = Payout::create([
                    'instructor_id' => $instructor->id,
                    'amount_in_cents' => 4500,
                    'status' => PayoutStatus::IN_DOUBT,
                    'idempotency_key' => 'payout_' . $instructor->id . '_2026-02_timeout',
                    'external_reference' => null,
                    'failure_reason' => 'Connection timed out waiting for gateway response',
                    'attempts' => 1,
                ]);

                LedgerEntry::create([
                    'instructor_id' => $instructor->id,
                    'type' => LedgerType::EARNING,
                    'direction' => LedgerDirection::CREDIT,
                    'amount_in_cents' => 4500,
                    'status' => LedgerStatus::LOCKED,
                    'source_type' => SubscriptionPeriod::class,
                    'source_id' => 1,
                    'payout_id' => $inDoubtPayout->id,
                    'idempotency_key' => 'alloc_' . Str::random(16),
                    'description' => 'Earnings locked in pending reconciliation payout',
                ]);
            }

            if ($index === 1) {
                // Failed payout
                Payout::create([
                    'instructor_id' => $instructor->id,
                    'amount_in_cents' => 3000,
                    'status' => PayoutStatus::FAILED,
                    'idempotency_key' => 'payout_' . $instructor->id . '_2026-02_failed',
                    'external_reference' => null,
                    'failure_reason' => 'Payment gateway rejected transfer: Invalid recipient IBAN format',
                    'attempts' => 1,
                ]);
            }
        }
    }
}
