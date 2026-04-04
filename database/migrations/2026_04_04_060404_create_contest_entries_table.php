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
        Schema::create('contest_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('registered_at');
            $table->unsignedInteger('total_solve_time_seconds')->nullable();
            $table->unsignedSmallInteger('puzzles_completed')->default(0);
            $table->string('meta_answer')->nullable();
            $table->boolean('meta_solved')->default(false);
            $table->dateTime('meta_submitted_at')->nullable();
            $table->unsignedInteger('meta_attempts_count')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->timestamps();

            $table->unique(['contest_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contest_entries');
    }
};
