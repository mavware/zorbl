<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_seen_at')->nullable()->after('converted_at');

            $table->index(['is_anonymous', 'last_seen_at'], 'users_last_seen_idx');
        });

        // Existing guests have never been tracked; treat their creation as the
        // last sighting so the prune window starts from a known point.
        DB::table('users')
            ->where('is_anonymous', true)
            ->whereNull('last_seen_at')
            ->update(['last_seen_at' => DB::raw('anonymous_created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_last_seen_idx');
            $table->dropColumn('last_seen_at');
        });
    }
};
