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
        Schema::table('clue_entries', function (Blueprint $table) {
            $table->json('quality_issues')->nullable()->after('clue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clue_entries', function (Blueprint $table) {
            $table->dropColumn('quality_issues');
        });
    }
};
