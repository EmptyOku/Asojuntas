<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_blocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('election_id')
                ->constrained('elections')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('block_id')
                ->constrained('blocks')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['election_id', 'block_id'], 'election_blocks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_blocks');
    }
};