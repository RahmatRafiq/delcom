<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_platform_id')->constrained()->onDelete('cascade');
            $table->string('content_id', 255);
            $table->enum('content_type', ['video', 'post', 'reel', 'story', 'short', 'other'])->default('video');
            $table->string('title', 500)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('platform_url', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('scan_enabled')->default(true);
            $table->string('last_scanned_comment_id', 255)->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->unsignedInteger('total_comments_scanned')->default(0);
            $table->unsignedInteger('total_spam_found')->default(0);
            $table->timestamps();

            $table->unique(['user_platform_id', 'content_id'], 'unique_user_content');
            $table->index(['user_platform_id', 'scan_enabled'], 'idx_scannable_contents');
            $table->index('last_scanned_at', 'idx_content_last_scanned');
            $table->index('content_type', 'idx_user_contents_content_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_contents');
    }
};
