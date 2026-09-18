<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orden en que cada barrio fue entregado dentro del GeoJSON de su comuna
 * (indice del punto en el archivo). Permite listar los barrios de una
 * comuna en el mapa respetando ese mismo orden en vez del alfabetico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table): void {
            $table->unsignedInteger('map_order')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table): void {
            $table->dropColumn('map_order');
        });
    }
};
