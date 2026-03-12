<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedSmallInteger('vcpu')->nullable()->after('php_version');
            $table->unsignedInteger('ram_mb')->nullable()->after('vcpu');
            $table->unsignedInteger('disk_gb')->nullable()->after('ram_mb');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['vcpu', 'ram_mb', 'disk_gb']);
        });
    }
};
