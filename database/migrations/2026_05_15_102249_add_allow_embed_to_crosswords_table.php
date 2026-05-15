<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crosswords', function (Blueprint $table): void {
            $table->boolean('allow_embed')->default(true)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('crosswords', function (Blueprint $table): void {
            $table->dropColumn('allow_embed');
        });
    }
};
