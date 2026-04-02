<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->string('type', 30)->default('barrio')->after('code');
            $table->string('source_name', 180)->nullable()->after('type');
            $table->boolean('is_verified')->default(true)->after('source_name');
            $table->text('notes')->nullable()->after('is_verified');

            $table->index('type');
            $table->index('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['is_verified']);

            $table->dropColumn([
                'type',
                'source_name',
                'is_verified',
                'notes',
            ]);
        });
    }
};
