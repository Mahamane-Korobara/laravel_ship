<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('ip_address');
            $table->string('ssh_user')->default('deployer');
            $table->integer('ssh_port')->default(22);
            $table->longText('ssh_private_key');
            $table->string('php_version')->default('8.2');
            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive');
            $table->text('last_error')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
