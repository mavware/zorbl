<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The partial unique index added in 2026_04_07_022103 only constrains
     * standalone (crossword_id IS NULL) rows. On SQLite, a later migration
     * (2026_05_14_134433) adds a foreign key column, which forces a table
     * rebuild — and the SQLite grammar replays indexes without preserving
     * their WHERE clause, turning the partial index into a full unique
     * constraint that incorrectly blocks duplicate (answer, clue, user_id)
     * tuples on crossword-attached rows. Re-create the index with its
     * predicate intact. MySQL (generated column) and PostgreSQL (partial
     * index survives ADD COLUMN) are unaffected, so no-op there.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS clue_entries_answer_clue_user_standalone_unique');
        DB::statement('CREATE UNIQUE INDEX clue_entries_answer_clue_user_standalone_unique ON clue_entries (answer, clue, user_id) WHERE crossword_id IS NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS clue_entries_answer_clue_user_standalone_unique');
        DB::statement('CREATE UNIQUE INDEX clue_entries_answer_clue_user_standalone_unique ON clue_entries (answer, clue, user_id)');
    }
};
