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
            $table->string('pdf_image')->nullable()->after('pdf_narrative');
        });
    }

    public function down(): void
    {
        Schema::table('crosswords', function (Blueprint $table) {
            $table->dropColumn('pdf_image');
        });
    }
};
