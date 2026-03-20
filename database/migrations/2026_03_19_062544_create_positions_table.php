<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('block_id')
                ->constrained('blocks')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('name', 100);
            $table->string('code', 30);
            $table->integer('order_number')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['block_id', 'code']);
            $table->unique(['block_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};