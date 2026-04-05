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
        Schema::table('crosswords', function (Blueprint $table) {
            $table->index('is_published');
            $table->index(['user_id', 'is_published']);
        });

        Schema::table('puzzle_attempts', function (Blueprint $table) {
            $table->index('is_completed');
        });

        Schema::table('contests', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('crossword_likes', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->index('following_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crosswords', function (Blueprint $table) {
            $table->dropIndex(['is_published']);
            $table->dropIndex(['user_id', 'is_published']);
        });

        Schema::table('puzzle_attempts', function (Blueprint $table) {
            $table->dropIndex(['is_completed']);
        });

        Schema::table('contests', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('crossword_likes', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->dropIndex(['following_id']);
        });
    }
};
