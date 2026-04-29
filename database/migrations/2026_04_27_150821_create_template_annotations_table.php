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
        Schema::create('template_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('philosophy');
            $table->json('strengths')->nullable();
            $table->json('compromises')->nullable();
            $table->text('best_for')->nullable();
            $table->text('avoid_when')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_annotations');
    }
};
