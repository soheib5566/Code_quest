<?php

namespace App\Console\Commands;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Services\Payment\PayoutReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payouts:reconcile')]
#[Description('Reconcile payouts by checking their status with the payment gateway')]
class ReconcilePayoutsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PayoutReconciliationService $reconciler)
    {
        $inDoubtCount = Payout::query()->where('status', PayoutStatus::IN_DOUBT)->count();

        if ($inDoubtCount === 0) {
            $this->info('No in-doubt payouts found.');
            return self::SUCCESS;
        }

        $this->info("Found {$inDoubtCount} in-doubt payouts. Querying gateway...");

        Payout::query()
            ->where('status', PayoutStatus::IN_DOUBT)
            ->orderBy('id')
            ->chunkById(100, function ($payouts) use ($reconciler) {
                foreach ($payouts as $payout) {
                    $reconciler->reconcile($payout);
                }
            });

        $this->info('Reconciliation completed.');

        return self::SUCCESS;
    }
}
