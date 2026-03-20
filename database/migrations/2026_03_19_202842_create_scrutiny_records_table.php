<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrutiny_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('election_id')
                ->constrained('elections')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('polling_table_id')
                ->nullable()
                ->constrained('polling_tables')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('record_number', 50)->nullable();
            $table->date('record_date')->nullable();
            $table->time('record_time')->nullable();

            $table->string('source_type', 20)->default('manual');
            $table->string('status', 20)->default('draft');

            $table->integer('quorum_attendees')->nullable();
            $table->integer('total_attendees')->nullable();

            $table->text('observations')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['election_id', 'status'], 'scrutiny_records_election_status_idx');
            $table->index(['polling_table_id'], 'scrutiny_records_polling_table_idx');
            $table->unique(['election_id', 'record_number'], 'scrutiny_records_election_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrutiny_records');
    }
};