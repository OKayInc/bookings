<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table): void {
            $table->boolean('quantity_enabled')->default(false)->after('inventory_quantity');
        });

        // Preserve every M9-R1 resource already configured with multi-piece stock,
        // while legacy equipment that inherited stock 1 remains binary.
        DB::table('resources')
            ->where('type', 'equipment')
            ->where('inventory_quantity', '>', 1)
            ->update(['quantity_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table): void {
            $table->dropColumn('quantity_enabled');
        });
    }
};
