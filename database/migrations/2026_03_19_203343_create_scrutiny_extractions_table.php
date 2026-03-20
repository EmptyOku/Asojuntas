<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrutiny_extractions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('scrutiny_record_id')
                ->constrained('scrutiny_records')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('scrutiny_record_file_id')
                ->nullable()
                ->constrained('scrutiny_record_files')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('based_on_extraction_id')
                ->nullable()
                ->constrained('scrutiny_extractions')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('source_type', 20)->default('ocr');
            $table->string('engine_name', 50)->nullable();
            $table->string('engine_version', 30)->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();

            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('round_number')->default(1);

            $table->json('raw_payload')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['scrutiny_record_id', 'status'], 'scrutiny_extractions_record_status_idx');
            $table->index(['scrutiny_record_file_id'], 'scrutiny_extractions_file_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrutiny_extractions');
    }
};