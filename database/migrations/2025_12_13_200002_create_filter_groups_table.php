<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filter_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('applies_to_platforms')->nullable()->comment('Array of platform names this group applies to');
            $table->timestamps();

            $table->index(['user_id', 'is_active'], 'idx_user_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filter_groups');
    }
};
