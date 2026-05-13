<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('safe_search_enabled')->default(true)->after('notification_preferences');
        });

        Schema::table('crosswords', function (Blueprint $table) {
            $table->boolean('contains_profanity')->default(false)->after('is_published')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('safe_search_enabled');
        });

        Schema::table('crosswords', function (Blueprint $table) {
            $table->dropColumn('contains_profanity');
        });
    }
};
