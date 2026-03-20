<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('state_id')
                ->constrained('states')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name', 120);
            $table->string('code', 20);
            $table->timestamps();

            $table->unique(['state_id', 'code']);
            $table->unique(['state_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};