<?php

namespace App\Console\Commands;

use App\Domain\Organizations\OrganizationDeletionService;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationPlanSubscription;
use App\Models\Person;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DeleteUserCommand extends Command
{
    protected $signature = 'users:delete {user : Email or user UUID}
        {--with-organizations : Delete organizations for which this user is an owner}
        {--dry-run : Preview the affected records}
        {--force : Skip the interactive email confirmation}';

    protected $description = 'Permanently delete a user and, optionally, their owned organizations';

    public function handle(OrganizationDeletionService $deletion): int
    {
        $identifier = (string) $this->argument('user');
        $user = Str::isUuid($identifier)
            ? User::query()->whereUuid($identifier)->first()
            : User::query()->where('email', $identifier)->first();
        if (! $user) {
            $this->error('User not found.');
            return self::FAILURE;
        }

        $owned = Organization::query()->whereHas('memberships', fn ($query) => $query
            ->where('person_id', $user->person_id)->where('role', 'owner'))->get();
        $joined = OrganizationMembership::query()->where('person_id', $user->person_id)->count();
        $this->info("User: {$user->email} ({$user->uuid}); memberships: {$joined}");
        $this->table(['Owned organization', 'UUID'], $owned->map(fn ($org) => [$org->name, $org->uuid])->all());

        if ($owned->isNotEmpty() && ! $this->option('with-organizations')) {
            $this->error('This user owns organizations. Re-run with --with-organizations to delete them.');
            return self::FAILURE;
        }

        // A person may also work at a surviving tenant. Do not silently remove
        // their account when a shared resource belongs to another organization.
        $ownedIds = $owned->pluck('id')->all();
        $externalResources = Resource::query()->where('person_id', $user->person_id)
            ->when($ownedIds !== [], fn ($query) => $query->whereNotIn('organization_id', $ownedIds))
            ->exists();
        if ($externalResources) {
            $this->error('This person is assigned to a resource in a surviving organization. Reassign it first.');
            return self::FAILURE;
        }

        foreach ($owned as $org) {
            if (OrganizationMembership::query()->where('organization_id', $org->getKey())
                ->where('person_id', '!=', $user->person_id)->where('role', 'owner')->exists()) {
                $this->error("{$org->name} has another owner. Transfer or remove ownership before deleting this user.");
                return self::FAILURE;
            }
            if (OrganizationPlanSubscription::query()->where('organization_id', $org->getKey())
                ->whereIn('status', ['active', 'trialing', 'past_due'])->exists()) {
                $this->error("{$org->name} has a live billing subscription. Cancel it at the provider and in the app first.");
                return self::FAILURE;
            }
            if (DB::table('organization_resources')->whereIn('resource_id', Resource::query()
                ->where('organization_id', $org->getKey())->select('id'))
                ->whereNotIn('organization_id', $ownedIds)->exists()) {
                $this->error("{$org->name} owns resources shared with a surviving organization. Unshare them first.");
                return self::FAILURE;
            }
        }

        $this->warn('Permanent deletion includes bookings and uploaded files. No refunds, emails or remote calendar deletions are sent.');
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        if (! app()->isDownForMaintenance()) {
            $this->error('Run php artisan down and stop workers/scheduled tasks before deleting.');
            return self::FAILURE;
        }
        foreach (['jobs', 'failed_jobs'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                $this->error('Drain/review queued and failed jobs before deleting.');
                return self::FAILURE;
            }
        }
        if (! $this->option('force') && $this->ask('Type the user email to confirm permanent deletion') !== $user->email) {
            $this->error('Deletion cancelled.');
            return self::FAILURE;
        }

        try {
            foreach ($owned as $org) {
                $deletion->delete($org);
                $this->info("Deleted organization {$org->uuid}.");
            }
            DB::transaction(function () use ($user): void {
                $person = Person::query()->whereKey($user->person_id)->lockForUpdate()->firstOrFail();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->getKey())->delete();
                }
                $user->delete();
                $person->delete();
            });
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Deletion stopped: '.$exception->getMessage().' Review the records above before retrying.');
            return self::FAILURE;
        }
        $this->info('User deleted.');
        return self::SUCCESS;
    }
}
