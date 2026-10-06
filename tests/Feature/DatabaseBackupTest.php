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
}
