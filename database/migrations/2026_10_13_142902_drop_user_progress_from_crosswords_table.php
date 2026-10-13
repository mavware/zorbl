<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solver progress moved to puzzle_attempts the same day this column was
     * added; nothing has written to it since, so the per-puzzle JSON blob is
     * dead weight.
     */
    public function up(): void
    {
        Schema::table('crosswords', function (Blueprint $table): void {
            $table->dropColumn('user_progress');
        });
    }

    public function down(): void
    {
        Schema::table('crosswords', function (Blueprint $table): void {
            $table->json('user_progress')->nullable()->after('solution');
        });
    }
};
