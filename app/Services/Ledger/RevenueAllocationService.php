<?php

namespace App\Services\Ledger;

use App\Enums\LedgerDirection;
use App\Enums\LedgerStatus;
use App\Enums\LedgerType;
use App\Enums\SubscriptionPeriodStatus;
use App\Models\CourseEngagement;
use App\Models\LedgerEntry;
use App\Models\SubscriptionPeriod;
use Illuminate\Support\Facades\DB;

class RevenueAllocationService
{
    public function __construct(public SplitCalculatorService $calculator) {}
    private const CUT_PLATORM = 30;
    public function allocate(SubscriptionPeriod $period): void
    {
        DB::transaction(function () use ($period) {
            $period = SubscriptionPeriod::query()
                ->where('id', $period->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($period->status === SubscriptionPeriodStatus::ALLOCATED) {
                return;
            }
            $totalCents = $period->gross_amount_in_cents;
            $platformCut = (int) round((self::CUT_PLATORM * $totalCents) / 100);
            $instructorCents = $totalCents - $platformCut;

            $engagements = CourseEngagement::query()
                ->where('subscription_period_id', $period->id)
                ->groupBy('instructor_id')
                ->selectRaw('instructor_id, SUM(seconds_watched) AS total_watch_time')
                ->pluck('total_watch_time', 'instructor_id')
                ->map(fn($val) => (int)$val)
                ->toArray();

            if (empty($engagements) || array_sum($engagements) == 0) {
                $this->PlatformLog($period, $totalCents, 'unconsumed_breakage');
                $period->update(['status' => SubscriptionPeriodStatus::ALLOCATED]);
                return;
            }

            $this->PlatformLog($period, $platformCut, 'platform_fee');

            $instructorAllocations = $this->calculator->calculator($instructorCents, $engagements);

            // 7. Write Immutable Ledger Entries for each Instructor
            foreach ($instructorAllocations as $instructorId => $earnedCents) {
                if ($earnedCents <= 0) {
                    continue;
                }

                LedgerEntry::create([
                    'instructor_id' => $instructorId,
                    'source_type' => SubscriptionPeriod::class,
                    'source_id'   => $period->id,
                    'amount_in_cents' => $earnedCents,
                    'direction' => LedgerDirection::CREDIT,
                    'type' => LedgerType::EARNING,
                    'status' => LedgerStatus::PAYABLE,
                    'description' => "Revenue share for period #{$period->id}",
                    'idempotency_key' => "earning_period_{$period->id}_inst_{$instructorId}",
                ]);
            }

            // 8. Finalize Period State
            $period->update([
                'status'       => SubscriptionPeriodStatus::ALLOCATED,
                'allocated_at' => now(),
            ]);
        });
    }

    private function PlatformLog(SubscriptionPeriod $period, int $amountInCents, string $reason)
    {
        if ($amountInCents <= 0) {
            return;
        }

        LedgerEntry::create([
            'instructor_id' => null,
            'source_type' => SubscriptionPeriod::class,
            'source_id' => $period->id,
            'amount_in_cents' => $amountInCents,
            'direction' => LedgerDirection::CREDIT,
            'type' => LedgerType::EARNING,
            'status' => LedgerStatus::SETTLED,
            'description' => "Platform {$reason} for period #{$period->id}",
            'idempotency_key' => "platform_{$reason}_period_{$period->id}",
        ]);
    }
}
