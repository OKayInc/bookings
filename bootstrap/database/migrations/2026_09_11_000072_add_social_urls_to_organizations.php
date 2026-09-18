<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('facebook_url', 500)->nullable()->after('logo_path');
            $table->string('instagram_url', 500)->nullable()->after('facebook_url');
            $table->string('x_url', 500)->nullable()->after('instagram_url');
            $table->string('linkedin_url', 500)->nullable()->after('x_url');
            $table->string('tiktok_url', 500)->nullable()->after('linkedin_url');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'facebook_url',
                'instagram_url',
                'x_url',
                'linkedin_url',
                'tiktok_url',
            ]);
        });
    }
};
