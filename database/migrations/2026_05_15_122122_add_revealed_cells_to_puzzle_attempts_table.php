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
            $table->json('revealed_cells')->nullable()->after('pencil_cells');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('puzzle_attempts', function (Blueprint $table) {
            $table->dropColumn('revealed_cells');
        });
    }
};
