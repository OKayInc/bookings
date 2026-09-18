<?php

namespace App\Console\Commands;

use App\Domain\Organizations\OrganizationPurgeService;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PurgeOrganizationCommand extends Command
{
    protected $signature = 'organizations:purge {organization : Organization UUID}
        {--level=configuration : all, members, resources, types, configuration}
        {--dry-run : Preview without deleting anything}
        {--force : Skip the interactive organization UUID confirmation}
        {--retry-files : Retry previously queued file deletions only}';
    protected $description = 'Purge one organization while preserving its identity, API keys, global users and other tenants';

    public function handle(OrganizationPurgeService $purge): int
    {
        $uuid = (string) $this->argument('organization');
        $level = (string) $this->option('level');
        if (! Str::isUuid($uuid) || ! in_array($level, OrganizationPurgeService::LEVELS, true)) {
            $this->error('Supply a valid organization UUID and purge level: '.implode(', ', OrganizationPurgeService::LEVELS));
            return self::FAILURE;
        }
        $org = Organization::whereUuid($uuid)->first();
        if (! $org) { $this->error('Organization not found.'); return self::FAILURE; }
        $this->info($org->name.' ('.$org->uuid.') — level: '.$level);
        $this->table(['History/root records to delete', 'Count'], collect($purge->preview($org, $level))->map(fn ($n, $name) => [$name, $n])->values()->all());
        $this->warn('Dependent records/files are also removed. No refunds, emails or remote calendar deletions are sent.');
        if ($this->option('dry-run')) { return self::SUCCESS; }
        if (! app()->isDownForMaintenance()) {
            $this->error('Run php artisan down and stop workers/scheduled tasks before purging.');
            return self::FAILURE;
        }
        foreach (['jobs', 'failed_jobs'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                $this->error('Drain/review queued and failed jobs before purging; global jobs are never deleted by this command.');
                return self::FAILURE;
            }
        }
        if (! $this->option('force') && $this->ask('This is permanent. Type the organization UUID to continue') !== $org->uuid) {
            $this->error('Purge cancelled.'); return self::FAILURE;
        }
        try {
            if (! $this->option('retry-files')) { $purge->purge($org, $level); }
            $failed = $purge->cleanupFiles($org);
            if ($failed) {
                $this->error("Database purge committed; {$failed} files still need cleanup. Retry with --retry-files.");
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            report($e);
            $this->error('Purge failed: '.$e->getMessage());
            return self::FAILURE;
        }
        $this->info('Purge and file cleanup completed.');
        return self::SUCCESS;
    }
}
