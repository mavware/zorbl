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
            $table->string('meta_answer', 500)->nullable()->after('revealed_cells');
        });
    }

    public function down(): void
    {
        Schema::table('puzzle_attempts', function (Blueprint $table) {
            $table->dropColumn('meta_answer');
        });
    }
};
