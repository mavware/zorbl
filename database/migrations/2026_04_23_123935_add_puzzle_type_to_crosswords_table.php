<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crosswords', function (Blueprint $table) {
            $table->string('puzzle_type')->default('standard')->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('crosswords', function (Blueprint $table) {
            $table->dropColumn('puzzle_type');
        });
    }
};
