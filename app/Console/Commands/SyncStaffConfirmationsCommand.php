<?php

namespace App\Console\Commands;

use App\Domain\Bookings\BookingWorkflowService;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Command;

class SyncStaffConfirmationsCommand extends Command
{
    protected $signature = 'appointments:sync-staff-confirmations';
    protected $description = 'Create missing per-resource staff confirmation records for eligible bookings.';

    public function handle(BookingWorkflowService $workflow): int
    {
        $count = 0;
        Booking::query()
            ->whereIn('status', [
                BookingStatus::PendingContractReview->value,
                BookingStatus::PendingStaffConfirmation->value,
                BookingStatus::PendingPayment->value,
                BookingStatus::Confirmed->value,
            ])
            ->where('requires_resource_confirmation', true)
            ->orderBy('id')
            ->chunk(100, function ($bookings) use ($workflow, &$count): void {
                foreach ($bookings as $booking) {
                    $workflow->refreshStatus($booking->load(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));
                    $count++;
                }
            });
        $this->info("Processed {$count} booking(s).");

        return self::SUCCESS;
    }
}
