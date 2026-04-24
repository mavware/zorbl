<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crosswords', function (Blueprint $table) {
            $table->boolean('freestyle_locked')->default(false)->after('puzzle_type');
        });
    }

    public function down(): void
    {
        Schema::table('crosswords', function (Blueprint $table) {
            $table->dropColumn('freestyle_locked');
        });
    }
};
