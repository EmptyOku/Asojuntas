<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->string('document_number', 30);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('second_last_name', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['document_type_id', 'document_number'], 'persons_document_unique');
            $table->index(['last_name', 'first_name'], 'persons_full_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};