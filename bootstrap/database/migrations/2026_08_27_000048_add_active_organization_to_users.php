<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->binary('active_organization_id', 16, true)->nullable()->after('person_id');
            $table->index('active_organization_id', 'users_active_org_idx');
            $table->foreign('active_organization_id', 'users_active_org_fk')
                ->references('id')->on('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign('users_active_org_fk');
            $table->dropIndex('users_active_org_idx');
            $table->dropColumn('active_organization_id');
        });
    }
};
