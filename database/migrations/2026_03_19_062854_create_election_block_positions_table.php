<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_block_positions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('election_block_id')
                ->constrained('election_blocks')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('block_id')
                ->constrained('blocks')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('position_id')
                ->constrained('positions')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->integer('vacancies')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['election_block_id', 'position_id'],
                'election_block_positions_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_block_positions');
    }
};