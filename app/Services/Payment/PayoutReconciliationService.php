<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\LedgerStatus;
use App\Enums\PayoutStatus;
use App\Models\LedgerEntry;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutReconciliationService
{
    public function __construct(public PaymentGatewayInterface $paymentGateway)
    {
        //
    }

    public function reconcile(Payout $payout)
    {
        DB::transaction(function () use ($payout) {
            $payout = Payout::query()
                ->where('id', $payout->id)
                ->lockForUpdate()
                ->first();

            if (! $payout || $payout->status !== PayoutStatus::IN_DOUBT) {
                return;
            }

            $result = $this->paymentGateway->checkStatus($payout->idempotency_key);

            if ($result->status === 'settled') {
                $payout->update([
                    'status'             => PayoutStatus::PAID,
                    'external_reference' => $result->transactionId,
                    'reconciled_at'      => now(),
                    'failure_reason'     => null,
                    'paid_at'            => now(),
                ]);

                LedgerEntry::query()
                    ->where('payout_id', $payout->id)
                    ->where('status', LedgerStatus::LOCKED)
                    ->update(['status' => LedgerStatus::SETTLED]);

                Log::info("Payout #{$payout->id} reconciled to PAID via gateway verification.");
            } elseif (in_array($result->status, ['not_found', 'failed'])) {
                $payout->update([
                    'status'         => PayoutStatus::FAILED,
                    'reconciled_at'  => now(),
                    'failure_reason' => 'Gateway confirmed transfer was never settled: ' . ($result->errorMessage ?? 'Not found'),
                ]);

                LedgerEntry::query()
                    ->where('payout_id', $payout->id)
                    ->where('status', LedgerStatus::LOCKED)
                    ->update([
                        'status'    => LedgerStatus::PAYABLE,
                        'payout_id' => null,
                    ]);

                Log::warning("Payout #{$payout->id} marked FAILED and funds unlocked after gateway check.");
            }
        });
    }
}
