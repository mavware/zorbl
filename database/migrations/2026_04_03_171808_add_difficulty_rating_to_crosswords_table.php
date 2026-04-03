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
            $table->decimal('difficulty_score', 3, 1)->nullable()->after('is_published');
            $table->string('difficulty_label', 20)->nullable()->after('difficulty_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crosswords', function (Blueprint $table) {
            $table->dropColumn(['difficulty_score', 'difficulty_label']);
        });
    }
};
