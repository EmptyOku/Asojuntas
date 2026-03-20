<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('person_id')
                ->references('id')
                ->on('persons')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->unique('person_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['person_id']);
            $table->dropForeign(['person_id']);
        });
    }
};