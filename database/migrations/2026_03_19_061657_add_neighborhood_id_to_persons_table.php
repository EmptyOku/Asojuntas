<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->foreignId('neighborhood_id')
                ->nullable()
                ->after('document_type_id')
                ->constrained('neighborhoods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropForeign(['neighborhood_id']);
            $table->dropColumn('neighborhood_id');
        });
    }
};