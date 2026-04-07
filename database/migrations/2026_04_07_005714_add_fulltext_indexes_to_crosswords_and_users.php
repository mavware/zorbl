<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fulltext indexes are only supported on MySQL/MariaDB and PostgreSQL.
     * SQLite does not support them, so we skip on that driver.
     */
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'])) {
            Schema::table('crosswords', function (Blueprint $table) {
                $table->fullText(['title', 'author'], 'crosswords_title_author_fulltext');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->fullText('name', 'users_name_fulltext');
            });

            Schema::table('clue_entries', function (Blueprint $table) {
                $table->fullText(['clue', 'answer'], 'clue_entries_clue_answer_fulltext');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'])) {
            Schema::table('crosswords', function (Blueprint $table) {
                $table->dropFullText('crosswords_title_author_fulltext');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropFullText('users_name_fulltext');
            });

            Schema::table('clue_entries', function (Blueprint $table) {
                $table->dropFullText('clue_entries_clue_answer_fulltext');
            });
        }
    }
};
