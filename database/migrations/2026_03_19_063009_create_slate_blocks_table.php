<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slate_blocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('election_id')
                ->constrained('elections')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('slate_id')
                ->constrained('slates')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('election_block_id')
                ->constrained('election_blocks')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['slate_id', 'election_block_id'],
                'slate_blocks_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slate_blocks');
    }
};