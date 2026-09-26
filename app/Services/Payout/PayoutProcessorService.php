<?php

namespace App\Services\Payout;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\LedgerDirection;
use App\Enums\LedgerStatus;
use App\Enums\PayoutStatus;
use App\Exceptions\PaymentGatewayHardFailureException;
use App\Exceptions\PaymentGatewayTimeoutException;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayoutProcessorService
{
    public function __construct(public PaymentGatewayInterface $paymentGateway)
    {
        //
    }

    public function process(int $instructorId, string $cyclePeriod): ?Payout
    {
        $idempotencyKey = "payout_{$instructorId}_{$cyclePeriod}";

        $payout = DB::transaction(function () use ($instructorId, $idempotencyKey) {
            $existing = Payout::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing && in_array($existing->status, [
                PayoutStatus::PROCESSING,
                PayoutStatus::IN_DOUBT,
                PayoutStatus::PAID
            ])) {
                return null;
            }

            $entries = LedgerEntry::query()
                ->where('instructor_id', $instructorId)
                ->where('direction', LedgerDirection::CREDIT)
                ->where('status', LedgerStatus::PAYABLE)
                ->lockForUpdate()
                ->get();

            $totalAmount = $entries->sum('amount_in_cents');

            if ($totalAmount <= 0) {
                return null;
            }

            $payout = Payout::updateOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'instructor_id'   => $instructorId,
                    'amount_in_cents' => $totalAmount,
                    'status'          => PayoutStatus::PROCESSING,
                    'failure_reason'  => null,
                ]
            );

            LedgerEntry::query()
                ->whereIn('id', $entries->pluck('id'))
                ->update([
                    'status' => LedgerStatus::LOCKED,
                    'payout_id' => $payout->id,
                ]);

            return $payout;
        });

        if (!$payout) {
            return null;
        }

        $instructor = User::findOrFail($instructorId);

        try {
            $result = $this->paymentGateway->transfer(
                key: $idempotencyKey,
                amountInCents: $payout->amount_in_cents,
                iban: $instructor->iban_account
            );
            DB::transaction(function () use ($payout, $result) {
                $payout->update([
                    'status'             => PayoutStatus::PAID,
                    'external_reference' => $result->transactionId,
                    'paid_at'            => now(),
                ]);

                LedgerEntry::query()
                    ->where('payout_id', $payout->id)
                    ->where('status', LedgerStatus::LOCKED)
                    ->update(['status' => LedgerStatus::SETTLED]);
            });
        } catch (PaymentGatewayHardFailureException $e) {
            DB::transaction(function () use ($payout, $e) {
                $payout->update([
                    'status'         => PayoutStatus::FAILED,
                    'failure_reason' => $e->getMessage(),
                ]);

                LedgerEntry::query()
                    ->where('payout_id', $payout->id)
                    ->where('status', LedgerStatus::LOCKED)
                    ->update([
                        'status' => LedgerStatus::PAYABLE,
                        'payout_id' => null,
                    ]);
            });
        } catch (PaymentGatewayTimeoutException $e) {
            $payout->update([
                'status'         => PayoutStatus::IN_DOUBT,
                'failure_reason' => $e->getMessage(),
            ]);
        }

        return $payout;
    }
}
