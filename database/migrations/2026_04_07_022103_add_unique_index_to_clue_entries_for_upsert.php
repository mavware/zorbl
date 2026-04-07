<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a unique partial index for standalone clue entries (no crossword).
     * Scoped to user_id so different users can have the same answer+clue,
     * but the same user can't have duplicates. This enables safe re-runs
     * of seed:clues without duplicating rows.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX clue_entries_answer_clue_user_standalone_unique ON clue_entries (answer, clue, user_id) WHERE crossword_id IS NULL');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE clue_entries ADD COLUMN answer_clue_hash CHAR(32) GENERATED ALWAYS AS (CASE WHEN crossword_id IS NULL THEN MD5(CONCAT(answer, clue, user_id)) ELSE NULL END) STORED');
            DB::statement('CREATE UNIQUE INDEX clue_entries_answer_clue_user_standalone_unique ON clue_entries (answer_clue_hash)');
        } else {
            DB::statement('CREATE UNIQUE INDEX clue_entries_answer_clue_user_standalone_unique ON clue_entries (answer, clue, user_id) WHERE crossword_id IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('clue_entries', function () {
            DB::statement('DROP INDEX IF EXISTS clue_entries_answer_clue_user_standalone_unique');
        });

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE clue_entries DROP COLUMN IF EXISTS answer_clue_hash');
        }
    }
};
