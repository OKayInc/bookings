<?php

namespace App\Console\Commands;

use App\Domain\Availability\BookingHoldService;
use App\Domain\Bookings\AppointmentLifecycleService;
use Illuminate\Console\Command;

class ExpireBookingHoldsCommand extends Command
{
    protected $signature = 'appointments:expire-holds';
    protected $description = 'Mark expired temporary appointment booking holds as expired.';

    public function handle(BookingHoldService $holds, AppointmentLifecycleService $appointments): int
    {
        $count = $holds->expire();
        $orphaned = $appointments->cancelOrphanedAppointments();
        $this->info("Expired {$count} booking hold(s); cancelled {$orphaned} orphaned appointment session(s).");

        return self::SUCCESS;
    }
}
