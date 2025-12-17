<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_platform_id')->constrained()->onDelete('cascade');
            $table->string('platform_comment_id', 255);
            $table->string('content_id', 255)->nullable();
            $table->enum('content_type', ['video', 'post', 'reel', 'story', 'short', 'other'])->default('video');
            $table->string('commenter_username', 255)->nullable();
            $table->string('commenter_id', 255)->nullable();
            $table->text('comment_text')->nullable();
            $table->foreignId('matched_filter_id')->nullable()->constrained('filters')->onDelete('set null');
            $table->string('matched_pattern', 500)->nullable();
            $table->enum('action_taken', [
                'deleted',
                'hidden',
                'flagged',
                'reported',
                'failed',
            ]);
            $table->enum('action_source', [
                'background_job',
                'extension',
                'manual',
            ]);
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['user_id', 'processed_at'], 'idx_user_date');
            $table->index(['user_platform_id', 'processed_at'], 'idx_platform_date');
            $table->index('action_taken', 'idx_action');
            $table->index('platform_comment_id', 'idx_platform_comment');
            $table->index('content_type', 'idx_content_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
