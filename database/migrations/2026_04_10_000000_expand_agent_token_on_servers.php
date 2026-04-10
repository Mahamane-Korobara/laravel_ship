<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `servers` MODIFY `agent_token` TEXT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "servers" ALTER COLUMN "agent_token" TYPE TEXT');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `servers` MODIFY `agent_token` VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "servers" ALTER COLUMN "agent_token" TYPE VARCHAR(255)');
        }
    }
};
