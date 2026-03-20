<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrutiny_elected_people', function (Blueprint $table) {
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
                ->nullable()
                ->constrained('election_blocks')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('election_block_position_id')
                ->nullable()
                ->constrained('election_block_positions')
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

            $table->string('signature_path', 255)->nullable();

            $table->string('source_type', 20)->default('ocr');
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('review_status', 20)->default('pending');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['scrutiny_record_id'], 'scrutiny_elected_people_record_idx');
            $table->index(['election_id', 'review_status'], 'scrutiny_elected_people_election_status_idx');
            $table->index(['document_type_id', 'document_number'], 'scrutiny_elected_people_document_idx');
            $table->index(['person_id'], 'scrutiny_elected_people_person_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrutiny_elected_people');
    }
};