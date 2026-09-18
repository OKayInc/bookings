<?php
namespace App\Console\Commands;

use App\Domain\Webhooks\WebhookDispatcher;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class DispatchWebhooksCommand extends Command
{
    protected $signature = 'webhooks:dispatch {--limit=100 : Maximum deliveries per run}';
    protected $description = 'Deliver due outgoing webhooks with signed requests and bounded retries';
    public function handle(WebhookDispatcher $dispatcher): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) { $this->error('Limit must be between 1 and 1000.'); return self::FAILURE; }
        if (app()->isDownForMaintenance()) { $this->info('Maintenance mode: no deliveries sent.'); return self::SUCCESS; }
        $ids = WebhookDelivery::where(function ($q): void {
            $q->where(fn ($q) => $q->where('status', 'pending')->where('available_at', '<=', now('UTC')))
                ->orWhere(fn ($q) => $q->where('status', 'delivering')->where('claimed_at', '<=', now('UTC')->subMinutes(2)));
        })->orderBy('available_at')->limit($limit)->pluck('id');
        $start = microtime(true); $sent = 0;
        foreach ($ids as $id) {
            if (microtime(true) - $start >= 45 || app()->isDownForMaintenance()) { break; }
            if ($dispatcher->deliver($id)) { $sent++; }
        }
        $this->info("Processed {$sent} webhook deliveries.");
        return self::SUCCESS;
    }
}
