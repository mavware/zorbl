<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cookie_consents');
    }

    public function down(): void
    {
        Schema::create('cookie_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('identifier_hash', 64)->nullable();
            $table->string('choice', 32);
            $table->string('version', 32);
            $table->string('region_country', 2)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'version']);
            $table->index(['identifier_hash', 'version']);
        });
    }
};
