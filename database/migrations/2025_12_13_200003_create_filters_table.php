<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filter_group_id')->constrained()->onDelete('cascade');
            $table->enum('type', [
                'keyword',      // Simple word matching
                'phrase',       // Multi-word matching
                'regex',        // Regular expression
                'username',     // Username pattern
                'url',          // URL pattern
                'emoji_spam',   // Too many emojis
                'repeat_char'   // Repeated characters
            ]);
            $table->string('pattern', 500)->comment('The pattern to match');
            $table->enum('match_type', [
                'exact',
                'contains',
                'starts_with',
                'ends_with',
                'regex'
            ])->default('contains');
            $table->boolean('case_sensitive')->default(false);
            $table->enum('action', [
                'delete',   // Delete the comment
                'hide',     // Hide the comment (platform-specific)
                'flag',     // Flag for manual review
                'report'    // Report to platform
            ])->default('delete');
            $table->integer('priority')->default(0)->comment('Higher priority filters are checked first');
            $table->unsignedInteger('hit_count')->default(0)->comment('Number of times this filter matched');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'priority'], 'idx_active_priority');
            $table->index('type', 'idx_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filters');
    }
};
