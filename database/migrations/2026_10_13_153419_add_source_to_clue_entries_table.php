<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a standalone clue was scraped from: the publication key and the
     * date that puzzle ran. Null for user-submitted and backfilled clues.
     */
    public function up(): void
    {
        Schema::table('clue_entries', function (Blueprint $table) {
            $table->string('source', 32)->nullable()->index()->after('clue_number');
            $table->date('source_date')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('clue_entries', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'source_date']);
        });
    }
};
