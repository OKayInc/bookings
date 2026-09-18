<?php

namespace App\Console\Commands;

use App\Domain\Bookings\BookingScheduleProposalService;
use Illuminate\Console\Command;

class ExpireScheduleProposalsCommand extends Command
{
    protected $signature = 'appointments:expire-schedule-proposals';
    protected $description = 'Expire unanswered staff schedule-change proposals and release their held alternative times.';

    public function handle(BookingScheduleProposalService $proposals): int
    {
        $count = $proposals->expire();
        $this->info("Expired {$count} schedule-change proposal(s).");
        return self::SUCCESS;
    }
}
