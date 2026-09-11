<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los índices únicos del escrutinio dejaban tres huecos de integridad:
 *
 * 1. `slate_block_id` es nullable y en PostgreSQL los NULL son distintos entre sí,
 *    así que las filas de votos sin plancha asignada podían duplicarse e inflar
 *    el conteo sin que la base de datos lo impidiera.
 * 2. `scrutiny_records` recibió SoftDeletes sin adaptar su índice único, de modo
 *    que borrar un acta quemaba su `record_number` para siempre.
 * 3. `scrutiny_record_files.page_number` era nullable pese a que la aplicación lo
 *    exige siempre, dejando pasar varias páginas NULL por acta.
 *
 * Se corrigen con índices parciales, igual que se hizo con `neighborhoods`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->guardAgainstExistingDuplicates();

        // 1. Una página por acta: `page_number` pasa a obligatorio, con lo que la unicidad
        //    ya existente (scrutiny_record_id, page_number) cubre todas las filas.
        Schema::table('scrutiny_record_files', function (Blueprint $table): void {
            $table->integer('page_number')->nullable(false)->change();
        });

        // 2. El número de acta solo debe ser único entre las actas vivas y numeradas.
        $this->dropUniqueIfExists('scrutiny_records', 'scrutiny_records_election_number_unique');
        $this->createUnique(
            'scrutiny_records',
            'scrutiny_records_election_number_active_unique',
            ['election_id', 'record_number'],
            'deleted_at IS NULL AND record_number IS NOT NULL'
        );

        // 3. Un resultado por bloque y plancha, tratando explícitamente el caso sin plancha.
        $this->dropUniqueIfExists('scrutiny_block_results', 'scrutiny_block_results_unique');
        $this->createUnique(
            'scrutiny_block_results',
            'scrutiny_block_results_slate_unique',
            ['scrutiny_record_id', 'election_block_id', 'slate_block_id'],
            'slate_block_id IS NOT NULL'
        );
        $this->createUnique(
            'scrutiny_block_results',
            'scrutiny_block_results_no_slate_unique',
            ['scrutiny_record_id', 'election_block_id'],
            'slate_block_id IS NULL'
        );

        $this->dropUniqueIfExists('consolidated_block_results', 'consolidated_block_results_unique');
        $this->createUnique(
            'consolidated_block_results',
            'consolidated_block_results_slate_unique',
            ['consolidation_run_id', 'election_block_id', 'slate_block_id'],
            'slate_block_id IS NOT NULL'
        );
        $this->createUnique(
            'consolidated_block_results',
            'consolidated_block_results_no_slate_unique',
            ['consolidation_run_id', 'election_block_id'],
            'slate_block_id IS NULL'
        );

        // 4. `users.person_id` ya es único; el índice suelto de la migración base sobra.
        $this->dropPlainIndexIfExists('users', 'users_person_id_index');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index('person_id');
        });

        foreach ([
            ['consolidated_block_results', 'consolidated_block_results_no_slate_unique'],
            ['consolidated_block_results', 'consolidated_block_results_slate_unique'],
            ['scrutiny_block_results', 'scrutiny_block_results_no_slate_unique'],
            ['scrutiny_block_results', 'scrutiny_block_results_slate_unique'],
            ['scrutiny_records', 'scrutiny_records_election_number_active_unique'],
        ] as [$table, $index]) {
            $this->dropUniqueIfExists($table, $index);
        }

        Schema::table('consolidated_block_results', function (Blueprint $table): void {
            $table->unique(
                ['consolidation_run_id', 'election_block_id', 'slate_block_id'],
                'consolidated_block_results_unique'
            );
        });

        Schema::table('scrutiny_block_results', function (Blueprint $table): void {
            $table->unique(
                ['scrutiny_record_id', 'election_block_id', 'slate_block_id'],
                'scrutiny_block_results_unique'
            );
        });

        Schema::table('scrutiny_records', function (Blueprint $table): void {
            $table->unique(['election_id', 'record_number'], 'scrutiny_records_election_number_unique');
        });

        Schema::table('scrutiny_record_files', function (Blueprint $table): void {
            $table->integer('page_number')->nullable()->change();
        });
    }

    /**
     * Los votos son evidencia electoral: si ya hay duplicados no se borran en
     * silencio, se aborta con el detalle para que alguien los concilie a mano.
     */
    private function guardAgainstExistingDuplicates(): void
    {
        $conflicts = [];

        $nullPages = DB::table('scrutiny_record_files')->whereNull('page_number')->count();

        if ($nullPages > 0) {
            $conflicts[] = "scrutiny_record_files: {$nullPages} archivo(s) sin page_number";
        }

        $duplicateResults = [
            ['scrutiny_block_results', ['scrutiny_record_id', 'election_block_id']],
            ['consolidated_block_results', ['consolidation_run_id', 'election_block_id']],
        ];

        foreach ($duplicateResults as [$table, $columns]) {
            $duplicates = DB::table($table)
                ->select($columns)
                ->whereNull('slate_block_id')
                ->groupBy($columns)
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();

            if ($duplicates > 0) {
                $conflicts[] = "{$table}: {$duplicates} grupo(s) duplicados sin plancha asignada";
            }
        }

        $duplicateRecords = DB::table('scrutiny_records')
            ->select(['election_id', 'record_number'])
            ->whereNull('deleted_at')
            ->whereNotNull('record_number')
            ->groupBy(['election_id', 'record_number'])
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicateRecords > 0) {
            $conflicts[] = "scrutiny_records: {$duplicateRecords} número(s) de acta repetidos";
        }

        if ($conflicts !== []) {
            throw new RuntimeException(
                'No se pueden endurecer los índices del escrutinio, hay datos que los violan: '
                .implode('; ', $conflicts)
                .'. Concilie estas filas antes de volver a ejecutar la migración.'
            );
        }
    }

    private function supportsPartialIndexes(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true);
    }

    /**
     * @param  list<string>  $columns
     */
    private function createUnique(string $table, string $name, array $columns, string $where): void
    {
        if ($this->supportsPartialIndexes()) {
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s',
                $name,
                $table,
                implode(', ', $columns),
                $where
            ));

            return;
        }

        // Motores sin índices parciales: se conserva la unicidad completa.
        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->unique($columns, $name);
        });
    }

    private function dropUniqueIfExists(string $table, string $name): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // `unique()` genera un UNIQUE CONSTRAINT en PostgreSQL y su índice no se
            // puede borrar directamente; hay que soltar primero la constraint. El
            // DROP INDEX posterior cubre el caso del índice parcial.
            DB::statement('ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS '.$name);
            DB::statement('DROP INDEX IF EXISTS '.$name);

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS '.$name);

            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropUnique($name);
            });
        } catch (Throwable) {
            // El índice no existía con ese nombre.
        }
    }

    private function dropPlainIndexIfExists(string $table, string $name): void
    {
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS '.$name);

            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        } catch (Throwable) {
            // El índice no existía con ese nombre.
        }
    }
};
