<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('github_id')->nullable()->after('email');
            $table->text('github_token')->nullable()->after('github_id');
            $table->text('github_refresh_token')->nullable()->after('github_token');
            $table->string('github_username')->nullable()->after('github_refresh_token');
            $table->string('github_avatar')->nullable()->after('github_username');
            $table->text('two_factor_secret')->nullable()->after('github_avatar');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'github_id',
                'github_token',
                'github_refresh_token',
                'github_username',
                'github_avatar',
                'two_factor_secret',
                'two_factor_enabled',
            ]);
        });
    }
};
