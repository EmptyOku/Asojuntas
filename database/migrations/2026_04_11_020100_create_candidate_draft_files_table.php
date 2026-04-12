<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_draft_files', function (Blueprint $table): void {
            $table->id();

            $table->uuid('capture_batch_uuid');

            $table->foreignId('election_id')
                ->nullable()
                ->constrained('elections')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('uploaded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('original_name', 255);
            $table->string('storage_path', 400);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('hash', 64);
            $table->unsignedInteger('page_number')->default(1);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['capture_batch_uuid', 'page_number'], 'candidate_draft_files_batch_page_idx');
            $table->unique(['capture_batch_uuid', 'hash'], 'candidate_draft_files_batch_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_draft_files');
    }
};

