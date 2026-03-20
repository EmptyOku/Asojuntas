<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polling_tables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('election_id')
                ->constrained('elections')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('name', 100);
            $table->string('code', 30);
            $table->string('location', 150)->nullable();
            $table->integer('capacity')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['election_id', 'code']);
            $table->unique(['election_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_tables');
    }
};