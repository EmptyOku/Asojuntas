<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('neighborhood_id')
                ->constrained('neighborhoods')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('name', 150);
            $table->string('code', 100)->unique();
            $table->date('election_date');
            $table->year('period_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections');
    }
};
