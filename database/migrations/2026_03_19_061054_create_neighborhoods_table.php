<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('commune_id')
                ->constrained('communes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name', 120);
            $table->string('code', 20);
            $table->timestamps();

            $table->unique(['commune_id', 'code']);
            $table->unique(['commune_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
    }
};