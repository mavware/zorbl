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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('manual_pro_started_at')->nullable()->after('grandfathered_at');
            $table->timestamp('manual_pro_ended_at')->nullable()->after('manual_pro_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['manual_pro_started_at', 'manual_pro_ended_at']);
        });
    }
};
