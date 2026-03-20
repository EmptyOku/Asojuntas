<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_drafts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('election_id')
                ->constrained('elections')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('block_id')
                ->nullable()
                ->constrained('blocks')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('position_id')
                ->nullable()
                ->constrained('positions')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('slate_id')
                ->nullable()
                ->constrained('slates')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('slate_block_id')
                ->nullable()
                ->constrained('slate_blocks')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('document_type_id')
                ->nullable()
                ->constrained('document_types')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('person_id')
                ->nullable()
                ->constrained('persons')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('document_number', 30)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('second_last_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();

            $table->string('source_type', 20)->default('manual');
            $table->decimal('confidence_score', 5, 2)->nullable();

            $table->string('review_status', 20)->default('pending');
            $table->boolean('is_processed')->default(false);
            $table->timestamp('processed_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['election_id', 'review_status'], 'candidate_drafts_election_status_idx');
            $table->index(['document_type_id', 'document_number'], 'candidate_drafts_document_idx');
            $table->index(['person_id'], 'candidate_drafts_person_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_drafts');
    }
};