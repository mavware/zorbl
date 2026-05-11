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
        Schema::create('wordplay_entries', function (Blueprint $table) {
            $table->id();
            $table->string('word');
            $table->string('type');
            $table->json('notes');
            $table->string('status')->default('saved');
            $table->timestamps();

            $table->unique(['word', 'type']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wordplay_entries');
    }
};
