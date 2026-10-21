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
        Schema::create('reference_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('url', 500);
            $table->string('url_key', 500);
            $table->text('description')->nullable();
            $table->string('origin', 20);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('analysis_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['target_id', 'url_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reference_sources');
    }
};
