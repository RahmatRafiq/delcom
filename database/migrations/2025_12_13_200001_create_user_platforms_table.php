<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->enum('connection_method', ['api', 'extension'])->default('api')->comment('Connection method: api or extension');
            $table->string('platform_user_id', 255)->nullable()->comment('User ID on the platform');
            $table->string('platform_username', 255)->nullable()->comment('Username on the platform');
            $table->string('platform_channel_id', 255)->nullable()->comment('Channel/Page ID if applicable');
            $table->text('access_token')->nullable()->comment('Encrypted OAuth access token');
            $table->text('refresh_token')->nullable()->comment('Encrypted OAuth refresh token');
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable()->comment('OAuth scopes granted');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_moderation_enabled')->default(false);
            $table->integer('scan_frequency_minutes')->default(60);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform_id', 'platform_user_id'], 'unique_user_platform');
            $table->index(['is_active', 'auto_moderation_enabled'], 'idx_auto_moderation');
            $table->index('last_scanned_at', 'idx_last_scanned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_platforms');
    }
};
