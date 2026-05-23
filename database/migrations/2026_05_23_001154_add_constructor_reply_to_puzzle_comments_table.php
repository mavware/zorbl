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
        Schema::table('puzzle_comments', function (Blueprint $table) {
            $table->text('constructor_reply')->nullable()->after('rating');
            $table->timestamp('constructor_reply_at')->nullable()->after('constructor_reply');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('puzzle_comments', function (Blueprint $table) {
            $table->dropColumn(['constructor_reply', 'constructor_reply_at']);
        });
    }
};
