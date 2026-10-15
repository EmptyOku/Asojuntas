<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * En los textos que ve el usuario se escribe "Junta de Acción Comunal" completo
 * en vez de la sigla JAC. Aquí se corrigen los nombres ya guardados; los
 * códigos (JAC-…) son identificadores y no se tocan.
 */
return new class extends Migration
{
    /** tabla => [columna => longitud máxima] */
    private const COLUMNS = [
        'elections' => ['name' => 150],
        'blocks' => ['description' => null],
        'positions' => ['description' => null],
        'permissions' => ['display_name' => null, 'description' => null],
    ];

    private const REPLACEMENTS = [
        'Eleccion JAC ' => 'Elección Junta de Acción Comunal ',
        'Elección JAC ' => 'Elección Junta de Acción Comunal ',
        'directorio JAC' => 'directorio de Juntas de Acción Comunal',
        'de la JAC' => 'de la Junta de Acción Comunal',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column => $max) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)->where($column, 'like', '%JAC%')->orderBy('id')->select(['id', $column])
                    ->chunkById(500, function ($rows) use ($table, $column, $max): void {
                        foreach ($rows as $row) {
                            $text = strtr((string) $row->{$column}, self::REPLACEMENTS);
                            if ($text === $row->{$column}) {
                                continue;
                            }

                            DB::table($table)->where('id', $row->id)->update([
                                $column => $max ? mb_substr($text, 0, $max) : $text,
                            ]);
                        }
                    });
            }
        }
    }

    public function down(): void
    {
        // Solo cambia textos: no hay nada que deshacer.
    }
};
