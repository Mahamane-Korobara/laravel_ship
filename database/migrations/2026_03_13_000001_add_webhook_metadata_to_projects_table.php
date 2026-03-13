<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('webhook_pending')->default(false)->after('github_webhook_secret');
            $table->string('webhook_last_commit_sha', 64)->nullable()->after('webhook_pending');
            $table->text('webhook_last_commit_message')->nullable()->after('webhook_last_commit_sha');
            $table->string('webhook_last_commit_author')->nullable()->after('webhook_last_commit_message');
            $table->string('webhook_last_event')->nullable()->after('webhook_last_commit_author');
            $table->string('webhook_last_delivery_id')->nullable()->after('webhook_last_event');
            $table->timestamp('webhook_last_event_at')->nullable()->after('webhook_last_delivery_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'webhook_pending',
                'webhook_last_commit_sha',
                'webhook_last_commit_message',
                'webhook_last_commit_author',
                'webhook_last_event',
                'webhook_last_delivery_id',
                'webhook_last_event_at',
            ]);
        });
    }
};
