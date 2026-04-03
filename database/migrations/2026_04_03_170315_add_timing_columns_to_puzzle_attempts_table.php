<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('puzzle_attempts', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('is_completed');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->unsignedInteger('solve_time_seconds')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('puzzle_attempts', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'completed_at', 'solve_time_seconds']);
        });
    }
};
