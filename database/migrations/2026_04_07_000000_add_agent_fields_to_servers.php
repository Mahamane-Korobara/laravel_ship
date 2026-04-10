<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('agent_url')->nullable()->after('labels');
            $table->string('agent_token')->nullable()->after('agent_url');
            $table->boolean('agent_enabled')->default(false)->after('agent_token');
            $table->timestamp('agent_last_seen_at')->nullable()->after('agent_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['agent_url', 'agent_token', 'agent_enabled', 'agent_last_seen_at']);
        });
    }
};

