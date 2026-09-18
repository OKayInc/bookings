<?php

namespace App\Console\Commands;

use App\Domain\Bookings\BookingReminderService;
use Illuminate\Console\Command;

class SendAppointmentRemindersCommand extends Command
{
    protected $signature = 'appointments:send-reminders';
    protected $description = 'Send due client and resource appointment reminders.';

    public function handle(BookingReminderService $reminders): int
    {
        $count = $reminders->sendDue();
        $this->info("Sent {$count} reminder delivery/deliveries.");

        return self::SUCCESS;
    }
}
