<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX templates_grid_unique ON templates (md5(grid::text))');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE templates ADD COLUMN grid_hash CHAR(32) GENERATED ALWAYS AS (MD5(grid)) STORED');
            DB::statement('CREATE UNIQUE INDEX templates_grid_unique ON templates (grid_hash)');
        } else {
            Schema::table('templates', function (Blueprint $table) {
                $table->unique(['grid'], 'templates_grid_unique');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE templates DROP INDEX templates_grid_unique');
            DB::statement('ALTER TABLE templates DROP COLUMN IF EXISTS grid_hash');
        } else {
            DB::statement('DROP INDEX IF EXISTS templates_grid_unique');
        }
    }
};
