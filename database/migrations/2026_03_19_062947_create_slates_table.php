<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('election_id')
                ->constrained('elections')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('name', 120);
            $table->string('code', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['election_id', 'code'], 'slates_election_code_unique');
            $table->unique(['election_id', 'name'], 'slates_election_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slates');
    }
};
