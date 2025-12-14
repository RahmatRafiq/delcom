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
            $table->string('platform_comment_id', 255)->comment('Comment ID on the platform');
            $table->string('video_id', 255)->nullable()->comment('Video/Post ID where comment was found');
            $table->string('post_id', 255)->nullable()->comment('Alternative post identifier');
            $table->string('commenter_username', 255)->nullable();
            $table->string('commenter_id', 255)->nullable();
            $table->text('comment_text')->nullable()->comment('Original comment text for reference');
            $table->foreignId('matched_filter_id')->nullable()->constrained('filters')->onDelete('set null');
            $table->string('matched_pattern', 500)->nullable()->comment('The pattern that matched');
            $table->enum('action_taken', [
                'deleted',
                'hidden',
                'flagged',
                'reported',
                'failed'
            ]);
            $table->enum('action_source', [
                'background_job',   // Tier 1: API-based deletion
                'extension',        // Tier 2: Extension-based deletion
                'manual'            // User manually deleted via dashboard
            ]);
            $table->text('failure_reason')->nullable()->comment('Error message if action failed');
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['user_id', 'processed_at'], 'idx_user_date');
            $table->index(['user_platform_id', 'processed_at'], 'idx_platform_date');
            $table->index('action_taken', 'idx_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
