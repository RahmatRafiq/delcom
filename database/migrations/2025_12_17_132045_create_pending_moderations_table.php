<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_moderations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_platform_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_content_id')->nullable()->constrained('user_contents')->onDelete('cascade');
            $table->string('platform_comment_id', 255);
            $table->string('content_id', 255)->nullable();
            $table->enum('content_type', ['video', 'post', 'reel', 'story', 'short', 'other'])->default('video');
            $table->string('content_title', 500)->nullable();
            $table->string('commenter_username', 255)->nullable();
            $table->string('commenter_id', 255)->nullable();
            $table->string('commenter_profile_url', 500)->nullable();
            $table->text('comment_text')->nullable();
            $table->foreignId('matched_filter_id')->nullable()->constrained('filters')->onDelete('set null');
            $table->string('matched_pattern', 500)->nullable();
            $table->decimal('confidence_score', 5, 2)->default(100);
            $table->enum('status', ['pending', 'approved', 'dismissed', 'deleted', 'failed'])->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();

            $table->unique(['user_platform_id', 'platform_comment_id'], 'unique_pending_comment');
            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index(['user_id', 'detected_at'], 'idx_user_detected');
            $table->index('status', 'idx_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_moderations');
    }
};
