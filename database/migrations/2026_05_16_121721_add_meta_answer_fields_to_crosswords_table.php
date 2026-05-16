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
            $table->string('meta_answer_prompt', 500)->nullable()->after('pdf_narrative');
            $table->json('meta_answers')->nullable()->after('meta_answer_prompt');
            $table->boolean('meta_answer_reveal')->default(true)->after('meta_answers');
        });
    }

    public function down(): void
    {
        Schema::table('crosswords', function (Blueprint $table) {
            $table->dropColumn(['meta_answer_prompt', 'meta_answers', 'meta_answer_reveal']);
        });
    }
};
