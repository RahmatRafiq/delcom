<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_platform_id')->constrained()->onDelete('cascade');
            $table->string('video_id', 255)->comment('YouTube video ID');
            $table->string('title', 500)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('scan_enabled')->default(true)->comment('Whether to include in auto-scan');
            $table->string('last_scanned_comment_id', 255)->nullable()->comment('Checkpoint: last comment ID scanned');
            $table->timestamp('last_scanned_at')->nullable();
            $table->unsignedInteger('total_comments_scanned')->default(0);
            $table->unsignedInteger('total_spam_found')->default(0);
            $table->timestamps();

            $table->unique(['user_platform_id', 'video_id'], 'unique_user_video');
            $table->index(['user_platform_id', 'scan_enabled'], 'idx_scannable_videos');
            $table->index('last_scanned_at', 'idx_video_last_scanned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_videos');
    }
};
