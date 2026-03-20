<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('election_id')
                ->constrained('elections')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('person_id')
                ->constrained('persons')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('slate_block_id')
                ->constrained('slate_blocks')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('election_block_position_id')
                ->constrained('election_block_positions')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('ballot_number', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['election_id', 'person_id'],
                'candidates_election_person_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};