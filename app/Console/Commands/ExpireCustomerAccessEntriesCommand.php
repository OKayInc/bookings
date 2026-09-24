<?php

namespace App\Console\Commands;

use App\Models\CustomerAccessEntry;
use App\Models\CustomerAccessEvent;
use Illuminate\Console\Command;

class ExpireCustomerAccessEntriesCommand extends Command
{
    protected $signature = 'customers:expire-access-entries';
    protected $description = 'Expire due policy-generated customer whitelist and blacklist entries.';

    public function handle(): int
    {
        $count = 0;

        CustomerAccessEntry::query()
            ->where('source', 'policy')
            ->whereIn('status', ['active', 'suggested'])
            ->whereNotNull('expires_at_utc')
            ->where('expires_at_utc', '<=', now('UTC'))
            ->orderBy('expires_at_utc')
            ->chunkById(100, function ($entries) use (&$count): void {
                foreach ($entries as $entry) {
                    $entry->update(['status' => 'resolved', 'resolved_at_utc' => now('UTC')]);
                    CustomerAccessEvent::create([
                        'organization_id' => $entry->organization_id,
                        'organization_contact_id' => $entry->organization_contact_id,
                        'customer_access_entry_id' => $entry->getKey(),
                        'event_type' => 'expired',
                        'source' => 'policy',
                        'reason' => 'Policy-generated entry expired.',
                        'metadata' => ['expired_at_utc' => $entry->expires_at_utc?->toISOString()],
                        'occurred_at_utc' => now('UTC'),
                    ]);
                    $count++;
                }
            });

        $this->info("Expired {$count} customer access entr".($count === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
