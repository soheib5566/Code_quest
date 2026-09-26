<?php

namespace App\Jobs;

use App\Services\Payout\PayoutProcessorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInstructorPayoutJob implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $instructorId, public string $cyclePeriod) {}

    /**
     * Execute the job.
     */
    public function handle(PayoutProcessorService $service): void
    {
        $service->process($this->instructorId, $this->cyclePeriod);
    }
}
