<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            $nonPersonResources = DB::table('resources')
                ->select('id')
                ->where('type', '<>', 'person');

            DB::table('organization_resources')
                ->whereIn('resource_id', $nonPersonResources)
                ->update([
                    'enforce_holidays' => false,
                    'holiday_region' => null,
                    'updated_at' => now(),
                ]);

            DB::table('resources')
                ->where('type', '<>', 'person')
                ->update([
                    'person_id' => null,
                    'timezone' => null,
                ]);
        });
    }

    public function down(): void
    {
        // Intentionally retain corrected values; their prior values are not recoverable safely.
    }
};
