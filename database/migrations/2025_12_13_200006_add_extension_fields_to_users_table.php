<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('extension_auth_token', 64)->nullable()->unique()->after('remember_token')
                ->comment('Hashed token for Chrome extension authentication');
            $table->timestamp('extension_token_expires_at')->nullable()->after('extension_auth_token');
            $table->enum('subscription_tier', ['free', 'pro', 'enterprise'])->default('free')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['extension_auth_token', 'extension_token_expires_at', 'subscription_tier']);
        });
    }
};
