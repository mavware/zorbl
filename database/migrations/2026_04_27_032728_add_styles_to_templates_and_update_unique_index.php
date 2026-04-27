<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->json('styles')->nullable()->after('grid');
        });

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS templates_grid_unique');
            DB::statement("CREATE UNIQUE INDEX templates_grid_unique ON templates (md5(grid::text || coalesce(styles::text, '')))");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE templates DROP INDEX templates_grid_unique');
            DB::statement('ALTER TABLE templates DROP COLUMN grid_hash');
            DB::statement("ALTER TABLE templates ADD COLUMN grid_hash CHAR(32) GENERATED ALWAYS AS (MD5(CONCAT(grid, COALESCE(styles, '')))) STORED");
            DB::statement('CREATE UNIQUE INDEX templates_grid_unique ON templates (grid_hash)');
        } else {
            Schema::table('templates', function (Blueprint $table) {
                $table->dropUnique('templates_grid_unique');
                $table->unique(['grid', 'styles'], 'templates_grid_unique');
            });

            // SQLite occasionally leaves the recreated index missing entries for
            // pre-existing rows (PRAGMA integrity_check reports "row N missing
            // from index"). REINDEX rebuilds it deterministically from the
            // current rows.
            if ($driver === 'sqlite') {
                DB::statement('REINDEX templates');
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS templates_grid_unique');
            DB::statement('CREATE UNIQUE INDEX templates_grid_unique ON templates (md5(grid::text))');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE templates DROP INDEX templates_grid_unique');
            DB::statement('ALTER TABLE templates DROP COLUMN grid_hash');
            DB::statement('ALTER TABLE templates ADD COLUMN grid_hash CHAR(32) GENERATED ALWAYS AS (MD5(grid)) STORED');
            DB::statement('CREATE UNIQUE INDEX templates_grid_unique ON templates (grid_hash)');
        } else {
            Schema::table('templates', function (Blueprint $table) {
                $table->dropUnique('templates_grid_unique');
                $table->unique(['grid'], 'templates_grid_unique');
            });
        }

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('styles');
        });
    }
};
