<?php

namespace App\Services\Subscription;

use App\Enums\LedgerDirection;
use App\Enums\LedgerStatus;
use App\Enums\LedgerType;
use App\Enums\SubscriptionPeriodStatus;
use App\Enums\SubscriptionStatus;
use App\Models\LedgerEntry;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Support\Facades\DB;

class SubscriptionRefundService
{
    public function refund(Subscription $subscription, bool $clawbackAllocated = false): int
    {
        return DB::transaction(function () use ($subscription, $clawbackAllocated) {
            $subscription = Subscription::where('id', $subscription->id)->lockForUpdate()->firstOrFail();

            if (in_array($subscription->status, [SubscriptionStatus::REFUNDED, SubscriptionStatus::CANCELLED])) {
                return 0;
            }

            $refundedCents = 0;

            // 1. Cancel all future unearned periods (Clean refund with zero instructor clawback)
            $unearnedPeriods = $subscription->periods()
                ->where('status', SubscriptionPeriodStatus::PENDING)
                ->lockForUpdate()
                ->get();

            if ($unearnedPeriods->isNotEmpty()) {
                $refundedCents += $unearnedPeriods->sum('gross_amount_in_cents');
                $subscription->periods()
                    ->whereIn('id', $unearnedPeriods->pluck('id'))
                    ->update(['status' => SubscriptionPeriodStatus::CANCELLED]);
            }

            // 2. If refunding already allocated periods (Chargeback / Money-Back Guarantee):
            if ($clawbackAllocated) {
                $allocatedPeriods = $subscription->periods()
                    ->where('status', SubscriptionPeriodStatus::ALLOCATED)
                    ->lockForUpdate()
                    ->get();

                if ($allocatedPeriods->isNotEmpty()) {
                    $periodIds = $allocatedPeriods->pluck('id');
                    $refundedCents += $allocatedPeriods->sum('gross_amount_in_cents');

                    // Single bulk query: Fetch ALL earnings across all refunded periods
                    $earnings = LedgerEntry::query()
                        ->where('source_type', SubscriptionPeriod::class)
                        ->whereIn('source_id', $periodIds)
                        ->where('type', LedgerType::EARNING)
                        ->whereNotNull('instructor_id')
                        ->get();

                    // Single flat loop: write immutable debit clawbacks
                    foreach ($earnings as $earning) {
                        LedgerEntry::create([
                            'instructor_id' => $earning->instructor_id,
                            'source_type' => SubscriptionPeriod::class,
                            'source_id' => $earning->source_id,
                            'amount_in_cents' => $earning->amount_in_cents,
                            'direction' => LedgerDirection::DEBIT,
                            'type' => LedgerType::REFUND_CLAWBACK,
                            'status' => LedgerStatus::SETTLED,
                            'idempotency_key' => "clawback_period_{$earning->source_id}_inst_{$earning->instructor_id}",
                            'description' => "Refund clawback for subscription #{$subscription->id}",
                        ]);
                    }

                    // Bulk update period statuses
                    $subscription->periods()
                        ->whereIn('id', $periodIds)
                        ->update(['status' => SubscriptionPeriodStatus::CANCELLED]);
                }
            }

            // 3. Mark subscription as refunded
            $subscription->update([
                'status'       => SubscriptionStatus::REFUNDED,
                'cancelled_at' => now(),
            ]);

            return $refundedCents;
        });
    }
}
