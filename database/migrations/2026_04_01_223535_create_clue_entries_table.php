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
        Schema::create('clue_entries', function (Blueprint $table) {
            $table->id();
            $table->string('answer')->index();
            $table->string('clue');
            $table->foreignId('crossword_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->unsignedSmallInteger('clue_number');
            $table->timestamps();

            $table->unique(['crossword_id', 'direction', 'clue_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clue_entries');
    }
};
