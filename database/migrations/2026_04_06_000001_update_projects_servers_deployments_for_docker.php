<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update projects table
        Schema::table('projects', function (Blueprint $table) {
            $table->string('docker_image')->nullable()->after('github_branch');
            $table->string('registry')->nullable()->after('docker_image');
            $table->json('tags')->nullable()->after('registry');
        });

        // Update servers table
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('php_version');
            $table->json('labels')->nullable()->after('ip_address');
        });

        // Update deployments table
        Schema::table('deployments', function (Blueprint $table) {
            $table->longText('docker_logs')->nullable()->after('log');
            $table->string('container_status')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert changes to projects table
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['docker_image', 'registry', 'tags']);
        });

        // Revert changes to servers table
        Schema::table('servers', function (Blueprint $table) {
            $table->string('php_version')->default('8.2')->after('ssh_private_key');
            $table->dropColumn('labels');
        });

        // Revert changes to deployments table
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropColumn(['docker_logs', 'container_status']);
        });
    }
};
