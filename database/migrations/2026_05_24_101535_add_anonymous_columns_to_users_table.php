<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_anonymous')->default(false)->index()->after('email_verified_at');
            $table->char('anonymous_token', 40)->nullable()->unique()->after('is_anonymous');
            $table->timestamp('anonymous_created_at')->nullable()->after('anonymous_token');
            $table->timestamp('converted_at')->nullable()->after('anonymous_created_at');

            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->index(['is_anonymous', 'anonymous_created_at'], 'users_anon_prune_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_anon_prune_idx');
            $table->dropUnique(['anonymous_token']);
            $table->dropIndex(['is_anonymous']);
            $table->dropColumn(['is_anonymous', 'anonymous_token', 'anonymous_created_at', 'converted_at']);

            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
