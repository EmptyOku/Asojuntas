<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el contorno geografico de cada comuna como un objeto GeoJSON
 * (Polygon) para poder pintarlas e interactuar con ellas en el mapa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communes', function (Blueprint $table): void {
            $table->json('boundary')->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('communes', function (Blueprint $table): void {
            $table->dropColumn('boundary');
        });
    }
};
