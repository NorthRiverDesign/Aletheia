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
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary');
            $table->text('rationale');
            $table->unsignedTinyInteger('risk_score')->index();
            $table->string('confidence', 10);
            $table->json('entities');
            $table->json('evidence');
            $table->json('next_steps');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
