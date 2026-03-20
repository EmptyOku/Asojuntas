<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrutiny_block_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('scrutiny_record_id')
                ->constrained('scrutiny_records')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('election_id')
                ->constrained('elections')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('election_block_id')
                ->constrained('election_blocks')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('slate_block_id')
                ->nullable()
                ->constrained('slate_blocks')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('scrutiny_extraction_id')
                ->nullable()
                ->constrained('scrutiny_extractions')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->integer('votes')->default(0);
            $table->string('source_type', 20)->default('ocr');
            $table->string('status', 20)->default('pending');
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['scrutiny_record_id', 'election_block_id', 'slate_block_id'],
                'scrutiny_block_results_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrutiny_block_results');
    }
};