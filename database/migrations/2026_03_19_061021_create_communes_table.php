<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('city_id')
                ->constrained('cities')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name', 120);
            $table->string('code', 20);
            $table->timestamps();

            $table->unique(['city_id', 'code']);
            $table->unique(['city_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
    }
};