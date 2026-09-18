<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('resources')->where('type', 'person')->update(['deposit_amount_minor' => 0]);
        DB::table('appointment_question_resource_rule_resources')
            ->whereIn('resource_id', DB::table('resources')->select('id')->where('type', 'person'))
            ->update(['deposit_amount_minor' => 0]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE resources ADD CONSTRAINT resources_person_deposit_zero CHECK (type <> 'person' OR (deposit_amount_minor IS NOT NULL AND deposit_amount_minor = 0))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE resources DROP CONSTRAINT resources_person_deposit_zero');
        }
        // Intentionally retain corrected values; historical booking snapshots are untouched.
    }
};
