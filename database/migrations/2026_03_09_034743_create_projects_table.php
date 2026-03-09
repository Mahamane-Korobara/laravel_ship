<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('github_repo');
            $table->string('github_branch')->default('main');
            $table->string('github_webhook_id')->nullable();
            $table->text('github_webhook_secret')->nullable();
            $table->string('domain')->nullable();
            $table->string('deploy_path', 500);
            $table->string('php_version')->default('8.2');
            $table->boolean('run_migrations')->default(true);
            $table->boolean('run_seeders')->default(false);
            $table->boolean('run_npm_build')->default(false);
            $table->boolean('has_queue_worker')->default(false);
            $table->enum('status', ['idle', 'deploying', 'deployed', 'failed'])->default('idle');
            $table->string('current_release')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
