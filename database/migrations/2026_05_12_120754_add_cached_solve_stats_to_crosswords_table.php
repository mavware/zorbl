<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crosswords', function (Blueprint $table) {
            $table->unsignedInteger('cached_attempts_count')->default(0)->after('difficulty_label');
            $table->unsignedInteger('cached_completed_count')->default(0)->after('cached_attempts_count');
            $table->unsignedInteger('cached_avg_solve_time')->nullable()->after('cached_completed_count');
        });
    }

    public function down(): void
    {
        Schema::table('crosswords', function (Blueprint $table) {
            $table->dropColumn(['cached_attempts_count', 'cached_completed_count', 'cached_avg_solve_time']);
        });
    }
};
