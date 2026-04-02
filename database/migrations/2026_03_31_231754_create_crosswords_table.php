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
        Schema::create('crosswords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('author')->nullable();
            $table->string('copyright')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('width')->default(15);
            $table->unsignedSmallInteger('height')->default(15);
            $table->string('kind')->default('http://ipuz.org/crossword#1');
            $table->json('grid');
            $table->json('solution');
            $table->json('clues_across');
            $table->json('clues_down');
            $table->json('styles')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crosswords');
    }
};
