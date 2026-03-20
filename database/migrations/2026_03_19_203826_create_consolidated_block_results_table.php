<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consolidated_block_results', function (Blueprint $table) {
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

            $table->foreignId('slate_block_id')
                ->nullable()
                ->constrained('slate_blocks')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->integer('total_votes')->default(0);
            $table->decimal('vote_percentage', 7, 4)->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['consolidation_run_id', 'election_block_id', 'slate_block_id'],
                'consolidated_block_results_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidated_block_results');
    }
};