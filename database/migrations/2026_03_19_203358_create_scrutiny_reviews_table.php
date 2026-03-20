<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrutiny_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('scrutiny_record_id')
                ->constrained('scrutiny_records')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('scrutiny_extraction_id')
                ->nullable()
                ->constrained('scrutiny_extractions')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('decision', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('comments')->nullable();
            $table->json('changes_payload')->nullable();

            $table->timestamps();

            $table->index(['scrutiny_record_id'], 'scrutiny_reviews_record_idx');
            $table->index(['scrutiny_extraction_id'], 'scrutiny_reviews_extraction_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrutiny_reviews');
    }
};