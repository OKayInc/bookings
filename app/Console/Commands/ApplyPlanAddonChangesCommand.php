<?php

namespace App\Console\Commands;

use App\Domain\Plans\PlanAddonService;
use App\Models\Organization;
use App\Models\OrganizationPlanAddon;
use Illuminate\Console\Command;
use Throwable;

class ApplyPlanAddonChangesCommand extends Command
{
    protected $signature = 'plans:apply-addon-changes';

    protected $description = 'Apply Business add-on reductions whose paid period has ended';

    public function handle(PlanAddonService $addons): int
    {
        $applied = 0;
        $failed = 0;

        $organizationIds = OrganizationPlanAddon::query()
            ->whereNotNull('pending_quantity')
            ->where('pending_effective_at_utc', '<=', now('UTC'))
            ->distinct()
            ->pluck('organization_id');

        foreach ($organizationIds as $organizationId) {
            $organization = Organization::query()->whereKey($organizationId)->first();
            if ($organization === null) {
                continue;
            }

            try {
                if ($addons->applyDue($organization)) {
                    $applied++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $this->error($organization->name.': '.$exception->getMessage());
            }
        }

        $this->info("Applied {$applied} organization add-on change set(s); {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
