<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('event', 50);
            $table->string('delivery_id')->nullable();
            $table->string('ref')->nullable();
            $table->string('commit_sha', 64)->nullable();
            $table->text('commit_message')->nullable();
            $table->string('author')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_webhook_events');
    }
};
