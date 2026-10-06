<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Copia de seguridad de toda la base de datos, como un archivo para descargar.
 *
 * Usa la mejor opción disponible según el motor:
 *  - SQLite: una copia exacta del archivo (.sqlite), tomada con VACUUM INTO,
 *    que es consistente aunque haya gente usando el sistema.
 *  - PostgreSQL: pg_dump (.sql con estructura y datos), si está instalado en
 *    el servidor.
 *  - Si lo anterior no se puede, un .sql generado desde PHP con los DATOS de
 *    todas las tablas (la estructura la recrean las migraciones).
 *
 * Las tablas temporales (sesiones, caché, colas, tokens de acceso) van sin
 * datos: no sirven para restaurar y los tokens y sesiones abiertas son
 * credenciales que no deben viajar en un archivo.
 *
 * El archivo SÍ lleva datos personales y las contraseñas cifradas: por eso
 * tiene permiso propio (database.backup) y cada descarga queda en la bitácora.
 */
class DatabaseBackup
{
    /** Tablas que se copian vacías. */
    public const EPHEMERAL_TABLES = [
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'password_reset_tokens', 'personal_access_tokens',
    ];

    /**
     * Crea el archivo en una carpeta temporal.
     *
     * @return array{path: string, filename: string, format: string}
     */
    public function create(): array
    {
        $driver = DB::connection()->getDriverName();
        $stamp = now()->format('Ymd-His');
        $directory = storage_path('app/private/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $base = $directory.DIRECTORY_SEPARATOR.'backup-'.$stamp.'-'.bin2hex(random_bytes(4));

        try {
            if ($driver === 'sqlite' && $this->sqliteFileCopy($base.'.sqlite')) {
                return ['path' => $base.'.sqlite', 'filename' => "asojuntas-backup-{$stamp}.sqlite", 'format' => 'sqlite'];
            }

            if ($driver === 'pgsql' && $this->pgDump($base.'.sql')) {
                return ['path' => $base.'.sql', 'filename' => "asojuntas-backup-{$stamp}.sql", 'format' => 'pg_dump'];
            }
        } catch (Throwable $exception) {
            // Se intenta la opción universal; el motivo queda en el log.
            report($exception);
        }

        @unlink($base.'.sqlite');
        @unlink($base.'.sql');
        $this->sqlDataDump($base.'.sql', $driver);

        return ['path' => $base.'.sql', 'filename' => "asojuntas-backup-datos-{$stamp}.sql", 'format' => 'sql_data'];
    }

    /** Copia exacta del archivo SQLite, con las tablas temporales vaciadas. */
    private function sqliteFileCopy(string $target): bool
    {
        // VACUUM no corre dentro de una transacción (ni tiene sentido para una base en memoria).
        if (DB::transactionLevel() > 0 || DB::connection()->getDatabaseName() === ':memory:') {
            return false;
        }

        DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($target));

        $copy = new PDO('sqlite:'.$target);
        $copy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $existing = $copy->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        foreach (array_intersect(self::EPHEMERAL_TABLES, $existing) as $table) {
            $copy->exec('DELETE FROM "'.$table.'"');
        }
        $copy->exec('VACUUM');
        $copy = null;

        return true;
    }

    /** pg_dump, si el servidor lo tiene. Devuelve false si no se pudo usar. */
    private function pgDump(string $target): bool
    {
        $config = DB::connection()->getConfig();

        $command = [
            (string) config('database.backup.pg_dump_path', 'pg_dump'),
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 5432),
            '--username='.($config['username'] ?? ''),
            '--dbname='.($config['database'] ?? ''),
            '--format=plain', '--no-owner', '--no-privileges',
            '--file='.$target,
        ];
        foreach (self::EPHEMERAL_TABLES as $table) {
            $command[] = '--exclude-table-data='.$table;
        }

        // La contraseña va por variable de entorno, nunca en la línea de comandos.
        $process = new Process($command, null, ['PGPASSWORD' => (string) ($config['password'] ?? '')]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('pg_dump no disponible o falló: '.mb_strimwidth($process->getErrorOutput(), 0, 300));
        }

        return true;
    }

    /**
     * Opción universal: un .sql con los datos de cada tabla, escrito por
     * partes para no cargar toda la base en memoria.
     */
    private function sqlDataDump(string $target, string $driver): void
    {
        $pdo = DB::connection()->getPdo();
        $quoteName = fn (string $name) => '"'.str_replace('"', '""', $name).'"';
        $handle = fopen($target, 'wb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo crear el archivo de la copia.');
        }

        $tables = collect(Schema::getTableListing())
            ->map(fn (string $table) => preg_replace('/^(main|public)\./', '', $table))
            ->reject(fn (string $table) => str_starts_with($table, 'sqlite_'))
            ->values();

        fwrite($handle, "-- Copia de seguridad de Asojuntas Girardot (solo datos)\n");
        fwrite($handle, '-- Generada: '.now()->toDateTimeString()." | Motor: {$driver}\n");
        fwrite($handle, "--\n-- Para restaurar:\n");
        fwrite($handle, "--   1. Crea una base vacía y ejecuta: php artisan migrate\n");
        fwrite($handle, "--   2. Ejecuta este archivo sobre esa base.\n");
        fwrite($handle, "-- Las tablas temporales (sesiones, caché, colas, tokens) van vacías.\n\n");

        // Durante la carga no se revisan las llaves foráneas: las tablas no van en orden de dependencia.
        fwrite($handle, $driver === 'pgsql' ? "SET session_replication_role = replica;\n" : "PRAGMA foreign_keys = OFF;\n");
        fwrite($handle, "BEGIN;\n\n");

        foreach ($tables as $table) {
            $name = $quoteName($table);
            fwrite($handle, "-- {$table}\nDELETE FROM {$name};\n");

            if (in_array($table, self::EPHEMERAL_TABLES, true)) {
                fwrite($handle, "\n");

                continue;
            }

            $columns = Schema::getColumnListing($table);
            $columnList = implode(', ', array_map($quoteName, $columns));
            $orderBy = in_array('id', $columns, true) ? 'id' : $columns[0];

            DB::table($table)->orderBy($orderBy)->chunk(500, function ($rows) use ($handle, $name, $columnList, $pdo): void {
                foreach ($rows as $row) {
                    $values = array_map(fn ($value) => match (true) {
                        $value === null => 'NULL',
                        is_bool($value) => $value ? 'TRUE' : 'FALSE',
                        is_int($value), is_float($value) => (string) $value,
                        default => $pdo->quote((string) $value),
                    }, array_values((array) $row));

                    fwrite($handle, "INSERT INTO {$name} ({$columnList}) VALUES (".implode(', ', $values).");\n");
                }
            });

            // PostgreSQL: el contador de ids debe seguir después del mayor id cargado.
            if ($driver === 'pgsql' && in_array('id', $columns, true)) {
                fwrite($handle, "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$name}), 1));\n");
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "COMMIT;\n");
        fwrite($handle, $driver === 'pgsql' ? "SET session_replication_role = DEFAULT;\n" : "PRAGMA foreign_keys = ON;\n");
        fclose($handle);
    }
}
