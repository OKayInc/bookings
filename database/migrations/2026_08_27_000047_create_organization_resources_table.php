<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // MariaDB DDL auto-commits. A previous failed attempt of this migration may
        // therefore have created this table even though Laravel did not record the
        // migration as completed. Keep the migration safe to rerun.
        if (! Schema::hasTable('organization_resources')) {
            Schema::create('organization_resources', function (Blueprint $table): void {
                $table->binary('organization_id', 16, true);
                $table->binary('resource_id', 16, true);
                $table->boolean('is_required_by_default')->default(true);
                $table->timestamps(6);

                $table->primary(['organization_id', 'resource_id'], 'org_res_pk');
                $table->index(['resource_id', 'organization_id'], 'org_res_resource_idx');
                $table->foreign('organization_id', 'org_res_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('resource_id', 'org_res_resource_fk')->references('id')->on('resources')->cascadeOnDelete();
            });
        }

        // cc_resource_provider_uq starts with resource_id and InnoDB therefore
        // uses it to support cc_resource_fk. Create a dedicated resource index
        // BEFORE dropping that unique index, otherwise MariaDB raises error 1553.
        if (! $this->indexExists('calendar_connections', 'cc_resource_fk_idx')) {
            Schema::table('calendar_connections', function (Blueprint $table): void {
                $table->index('resource_id', 'cc_resource_fk_idx');
            });
        }

        if ($this->indexExists('calendar_connections', 'cc_resource_provider_uq')) {
            Schema::table('calendar_connections', function (Blueprint $table): void {
                $table->dropUnique('cc_resource_provider_uq');
            });
        }

        if (! $this->indexExists('calendar_connections', 'cc_org_resource_provider_uq')) {
            Schema::table('calendar_connections', function (Blueprint $table): void {
                $table->unique(['organization_id', 'resource_id', 'provider'], 'cc_org_resource_provider_uq');
            });
        }

        // Always execute the backfill. insertOrIgnore makes this safe both for a
        // clean migration and for a rerun after MariaDB partially committed DDL.
        DB::table('resources')->orderBy('created_at')->chunk(250, function ($resources): void {
            $now = now();
            $rows = [];
            foreach ($resources as $resource) {
                $rows[] = [
                    'organization_id' => $resource->organization_id,
                    'resource_id' => $resource->id,
                    'is_required_by_default' => (bool) $resource->is_required_by_default,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                DB::table('organization_resources')->insertOrIgnore($rows);
            }
        });
    }

    public function down(): void
    {
        // Restore the old uniqueness first. This can fail if shared-resource
        // calendar connections now legitimately contain the same provider more
        // than once for one resource across different organizations.
        if (! $this->indexExists('calendar_connections', 'cc_resource_provider_uq')) {
            Schema::table('calendar_connections', function (Blueprint $table): void {
                $table->unique(['resource_id', 'provider'], 'cc_resource_provider_uq');
            });
        }

        if ($this->indexExists('calendar_connections', 'cc_org_resource_provider_uq')) {
            Schema::table('calendar_connections', function (Blueprint $table): void {
                $table->dropUnique('cc_org_resource_provider_uq');
            });
        }

        if ($this->indexExists('calendar_connections', 'cc_resource_fk_idx')) {
            Schema::table('calendar_connections', function (Blueprint $table): void {
                $table->dropIndex('cc_resource_fk_idx');
            });
        }

        Schema::dropIfExists('organization_resources');
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
