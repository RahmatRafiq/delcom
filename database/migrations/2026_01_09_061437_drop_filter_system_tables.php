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
        // Drop foreign keys and columns only if they exist
        if (Schema::hasColumn('pending_moderations', 'matched_filter_id')) {
            Schema::table('pending_moderations', function (Blueprint $table) {
                $table->dropForeign(['matched_filter_id']);
                $table->dropColumn('matched_filter_id');
            });
        }

        if (Schema::hasColumn('moderation_logs', 'matched_filter_id')) {
            Schema::table('moderation_logs', function (Blueprint $table) {
                $table->dropForeign(['matched_filter_id']);
                $table->dropColumn('matched_filter_id');
            });
        }

        // Drop filter tables in reverse dependency order
        Schema::dropIfExists('preset_filters');
        Schema::dropIfExists('filters');
        Schema::dropIfExists('filter_groups');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate filter_groups table
        Schema::create('filter_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('applies_to_platforms')->nullable();
            $table->timestamps();
        });

        // Recreate filters table
        Schema::create('filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filter_group_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->text('pattern');
            $table->string('match_type');
            $table->boolean('case_sensitive')->default(false);
            $table->string('action')->default('flag');
            $table->integer('priority')->default(0);
            $table->integer('hit_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Recreate preset_filters table
        Schema::create('preset_filters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->json('filters_data');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Restore foreign keys
        Schema::table('moderation_logs', function (Blueprint $table) {
            $table->foreignId('matched_filter_id')->nullable()->constrained('filters')->onDelete('set null');
        });

        Schema::table('pending_moderations', function (Blueprint $table) {
            $table->foreignId('matched_filter_id')->nullable()->constrained('filters')->onDelete('set null');
        });
    }
};
