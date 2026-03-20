<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrutiny_record_files', function (Blueprint $table) {
            $table->id();

            $table->foreignId('scrutiny_record_id')
                ->constrained('scrutiny_records')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('uploaded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('file_type', 20)->default('image');
            $table->string('original_name', 255)->nullable();
            $table->string('storage_path', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('hash', 128)->nullable();
            $table->integer('page_number')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['scrutiny_record_id'], 'scrutiny_record_files_record_idx');
            $table->index(['file_type'], 'scrutiny_record_files_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrutiny_record_files');
    }
};