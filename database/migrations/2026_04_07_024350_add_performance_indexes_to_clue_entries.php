<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes to speed up the Clue Library page queries on large datasets.
     */
    public function up(): void
    {
        Schema::table('clue_entries', function (Blueprint $table) {
            // Default listing: ORDER BY created_at DESC with pagination
            $table->index('created_at');

            // "My Clues" filter: WHERE user_id = ? ORDER BY created_at DESC
            $table->index(['user_id', 'created_at']);

            // "Standalone" filter: WHERE crossword_id IS NULL ORDER BY created_at DESC
            $table->index(['crossword_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clue_entries', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['crossword_id', 'created_at']);
        });
    }
};
