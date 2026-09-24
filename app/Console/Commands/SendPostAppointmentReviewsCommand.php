<?php

namespace App\Console\Commands;

use App\Domain\Customers\PostAppointmentReviewService;
use Illuminate\Console\Command;

class SendPostAppointmentReviewsCommand extends Command
{
    protected $signature = 'appointments:send-outcome-reviews';
    protected $description = 'Send post-appointment attendance review requests to configured organization staff.';

    public function handle(PostAppointmentReviewService $service): int
    {
        $count = $service->sendDue();
        $this->info("Sent {$count} post-appointment review request(s).");

        return self::SUCCESS;
    }
}
