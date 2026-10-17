<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Copia de seguridad de la base de datos desde el Panel: exige su propio
 * permiso, no incluye sesiones ni tokens y cada descarga queda en la bitácora.
 */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    #[Test]
    public function solo_quien_tiene_el_permiso_puede_descargar_la_copia(): void
    {
        // Ni siquiera con todos los permisos de usuarios y roles.
        $admin = $this->makeUser(['users.view', 'users.delete', 'roles.view', 'roles.update', 'persons.view']);
        $this->actingAs($admin)->get('/api/admin/panel/backup')->assertForbidden();

        $this->get('/api/logout');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/panel/backup')->assertUnauthorized();
    }

    #[Test]
    public function la_copia_trae_los_datos_pero_no_sesiones_ni_tokens_y_queda_en_la_bitacora(): void
    {
        $this->makeNeighborhood("Barrio O'Higgins"); // con comilla: debe quedar bien escapado
        $admin = $this->makeUser(['database.backup']);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\Models\User', 'tokenable_id' => $admin->id,
            'name' => 'secreto', 'token' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/api/admin/panel/backup')->assertOk();

        $this->assertStringContainsString('attachment; filename=asojuntas-backup', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));

        $path = $response->baseResponse->getFile()->getPathname();
        $sql = file_get_contents($path);
        @unlink($path);

        // Los datos están, con la comilla escapada.
        $this->assertStringContainsString('INSERT INTO "users"', $sql);
        $this->assertStringContainsString($admin->username, $sql);
        $this->assertStringContainsString("Barrio O''Higgins", $sql);

        // Las tablas temporales van vacías: se limpian al restaurar, pero no llevan datos.
        $this->assertStringContainsString('DELETE FROM "personal_access_tokens";', $sql);
        $this->assertStringNotContainsString('INSERT INTO "personal_access_tokens"', $sql);
        $this->assertStringNotContainsString(str_repeat('a', 64), $sql);
        $this->assertStringNotContainsString('INSERT INTO "sessions"', $sql);

        // Queda registrado quién la descargó.
        $log = AuditLog::where('action', 'database_backup')->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertGreaterThan(0, $log->metadata['size_bytes']);
    }

    #[Test]
    public function el_sql_generado_se_puede_volver_a_cargar(): void
    {
        $this->makeElection($this->makeNeighborhood('Barrio Restaurable'));
        $admin = $this->makeUser(['database.backup']);

        $path = app(DatabaseBackup::class)->create()['path'];
        $sql = file_get_contents($path);
        @unlink($path);

        $before = ['neighborhoods' => DB::table('neighborhoods')->count(), 'elections' => DB::table('elections')->count(), 'users' => DB::table('users')->count()];

        // Se ejecuta sobre la misma base: borra y vuelve a cargar cada tabla.
        // (BEGIN/COMMIT y PRAGMA se omiten porque la prueba ya corre dentro de una transacción.)
        $statements = array_filter(array_map('trim', explode(";\n", $sql)), fn ($line) => $line !== ''
            && ! str_starts_with($line, 'BEGIN') && ! str_starts_with($line, 'COMMIT') && ! str_starts_with($line, 'PRAGMA'));
        DB::statement('PRAGMA defer_foreign_keys = ON');
        foreach ($statements as $statement) {
            DB::unprepared(preg_replace('/^(--.*\n)+/m', '', $statement).';');
        }

        $this->assertSame($before, ['neighborhoods' => DB::table('neighborhoods')->count(), 'elections' => DB::table('elections')->count(), 'users' => DB::table('users')->count()]);
        $this->assertSame($admin->username, DB::table('users')->where('id', $admin->id)->value('username'));
    }

    #[Test]
    public function con_pg_dump_la_copia_crea_la_base_y_trae_instrucciones(): void
    {
        // pg_dump falso: anota los parámetros que recibió y escribe un volcado mínimo.
        $dir = storage_path('framework/testing/fake-pg-dump');
        @mkdir($dir, 0775, true);
        $php = $dir.DIRECTORY_SEPARATOR.'fake.php';
        file_put_contents($php, <<<'PHP'
<?php
$file = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--file=')) $file = substr($arg, 7);
}
file_put_contents($file, "-- PARAMS: ".implode(' ', array_slice($argv, 1))."\nCREATE DATABASE jac_elections;\n\connect jac_elections\nCREATE TABLE prueba (id int);\n");
PHP);
        if (DIRECTORY_SEPARATOR === '\\') {
            $bin = $dir.DIRECTORY_SEPARATOR.'pg_dump.bat';
            file_put_contents($bin, '@"'.PHP_BINARY.'" "'.$php.'" %*');
        } else {
            $bin = $dir.'/pg_dump';
            file_put_contents($bin, "#!/bin/sh\nexec \"".PHP_BINARY."\" \"$php\" \"\$@\"\n");
            chmod($bin, 0755);
        }
        config(['database.backup.pg_dump_path' => $bin]);

        $target = $dir.DIRECTORY_SEPARATOR.'copia.sql';
        $method = new \ReflectionMethod(DatabaseBackup::class, 'pgDump');
        $this->assertTrue($method->invoke(app(DatabaseBackup::class), $target));

        $sql = file_get_contents($target);
        // Instrucciones primero, luego el volcado completo con la creación de la base.
        $this->assertStringStartsWith('-- Copia de seguridad de Asojuntas Girardot (estructura y datos)', $sql);
        $this->assertStringContainsString('psql -U postgres -d postgres -v ON_ERROR_STOP=1 -f este-archivo.sql', $sql);
        $this->assertStringContainsString("CREATE DATABASE jac_elections;\n\connect jac_elections\nCREATE TABLE prueba", $sql);
        $this->assertStringContainsString('--create', $sql);
        $this->assertStringContainsString('--exclude-table-data=personal_access_tokens', $sql);
        $this->assertFileDoesNotExist($target.'.raw');

        // Si pg_dump falla, no queda nada a medias (y create() cae al .sql de datos).
        config(['database.backup.pg_dump_path' => $dir.DIRECTORY_SEPARATOR.'no-existe']);
        $this->expectException(\RuntimeException::class);
        try {
            $method->invoke(app(DatabaseBackup::class), $dir.DIRECTORY_SEPARATOR.'fallida.sql');
        } finally {
            $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'fallida.sql.raw');
            array_map('unlink', glob($dir.DIRECTORY_SEPARATOR.'*'));
            @rmdir($dir);
        }
    }
}
