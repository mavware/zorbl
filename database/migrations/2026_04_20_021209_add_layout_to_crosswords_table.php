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
            $table->unsignedTinyInteger('layout')->nullable()->after('secret_theme');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crosswords', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
