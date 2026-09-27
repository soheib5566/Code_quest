<?php

namespace App\Console\Commands;

use App\Enums\LedgerDirection;
use App\Enums\LedgerStatus;
use App\Jobs\ProcessInstructorPayoutJob;
use App\Models\LedgerEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payouts:process {--period= : The billing cycle period (format YYYY-MM)}')]
#[Description('Dispatch payout jobs in chunks for instructors with payable balances')]
class ProcessPayoutsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $period = $this->option('period') ?? now()->format('Y-m');

        LedgerEntry::query()
            ->where('status', LedgerStatus::PAYABLE)
            ->where('direction', LedgerDirection::CREDIT)
            ->whereNotNull('instructor_id')
            ->distinct()
            ->pluck('instructor_id')
            ->chunk(250)
            ->each(function ($instructorIds) use ($period) {
                foreach ($instructorIds as $instructorId) {
                    ProcessInstructorPayoutJob::dispatch($instructorId, $period);
                }
            });

        $this->info('Successfully dispatched payout jobs.');

        return self::SUCCESS;
    }
}
