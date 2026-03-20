<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consolidation_run_id')
                ->constrained('consolidation_runs')
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

            $table->foreignId('election_block_position_id')
                ->nullable()
                ->constrained('election_block_positions')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('slate_block_id')
                ->nullable()
                ->constrained('slate_blocks')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('candidate_id')
                ->nullable()
                ->constrained('candidates')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->integer('allocated_seats')->default(1);
            $table->integer('allocation_order')->nullable();
            $table->string('allocation_method', 50)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['consolidation_run_id'], 'seat_allocations_run_idx');
            $table->index(['candidate_id'], 'seat_allocations_candidate_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_allocations');
    }
};