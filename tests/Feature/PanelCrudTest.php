<?php

namespace Tests\Feature;

use App\Models\CandidateDraft;
use App\Models\City;
use App\Models\Commune;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Panel: CRUD genérico de cada tabla, con permisos por operación, borrado
 * suave con papelera y las restricciones de cada tabla.
 */
class PanelCrudTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private const COMMUNES_ALL = ['communes.view', 'communes.create', 'communes.update', 'communes.delete'];

    private function cityId(): int
    {
        $this->makeNeighborhood('Barrio Base'); // crea departamento, ciudad y comuna

        return City::firstOrFail()->id;
    }

    #[Test]
    public function el_panel_muestra_solo_las_tablas_que_el_rol_puede_ver_y_lo_que_puede_hacer_en_cada_una(): void
    {
        $user = $this->makeUser(['communes.view', 'communes.create', 'blocks.view', 'users.view', 'users.create']);

        $resources = collect($this->actingAs($user)->getJson('/api/admin/panel/resources')->assertOk()->json('data'))->keyBy('key');

        $this->assertEqualsCanonicalizing(['communes', 'blocks', 'users'], $resources->keys()->all());
        $this->assertSame(['create' => true, 'update' => false, 'delete' => false], $resources['communes']['can']);
        $this->assertSame(['create' => false, 'update' => false, 'delete' => false], $resources['blocks']['can']);

        // Crear usuarios existe, pero se hace en otra pantalla: el Panel lo indica.
        $this->assertFalse($resources['users']['can']['create']);
        $elsewhere = collect($resources['users']['elsewhere'])->firstWhere('op', 'create');
        $this->assertTrue($elsewhere['allowed']);
        $this->assertSame('admin-roles', $elsewhere['route']);
    }

    #[Test]
    public function cada_operacion_exige_su_permiso(): void
    {
        $cityId = $this->cityId();
        $commune = Commune::firstOrFail();
        $payload = ['name' => 'Comuna Nueva', 'code' => 'COM99', 'city_id' => $cityId];

        $lector = $this->makeUser(['communes.view']);
        $this->actingAs($lector)->getJson('/api/admin/panel/communes')->assertOk();
        $this->actingAs($lector)->postJson('/api/admin/panel/communes', $payload)->assertForbidden();
        $this->actingAs($lector)->putJson("/api/admin/panel/communes/{$commune->id}", $payload)->assertForbidden();
        $this->actingAs($lector)->deleteJson("/api/admin/panel/communes/{$commune->id}")->assertForbidden();
        // La papelera es parte de "eliminar".
        $this->actingAs($lector)->getJson('/api/admin/panel/communes?trashed=1')->assertForbidden();

        // Sin "ver" la tabla ni siquiera se lista.
        $this->actingAs($this->makeUser(['blocks.view']))->getJson('/api/admin/panel/communes')->assertForbidden();

        $creador = $this->makeUser(['communes.view', 'communes.create']);
        $this->actingAs($creador)->postJson('/api/admin/panel/communes', $payload)->assertCreated();
        $this->actingAs($creador)->deleteJson("/api/admin/panel/communes/{$commune->id}")->assertForbidden();
    }

    #[Test]
    public function crea_edita_y_valida_que_no_se_repitan(): void
    {
        $cityId = $this->cityId();
        $admin = $this->makeUser(self::COMMUNES_ALL);

        $id = $this->actingAs($admin)->postJson('/api/admin/panel/communes', ['name' => ' Comuna 8 ', 'code' => 'COM08', 'city_id' => $cityId])
            ->assertCreated()->json('data.id');
        $this->assertSame('Comuna 8', Commune::find($id)->name, 'Se quitan los espacios sobrantes.');

        // Campos obligatorios, con el nombre del campo en español.
        $this->actingAs($admin)->postJson('/api/admin/panel/communes', ['code' => 'COM09'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'city_id']);

        // No se repite el código dentro de la ciudad.
        $this->actingAs($admin)->postJson('/api/admin/panel/communes', ['name' => 'Otra', 'code' => 'COM08', 'city_id' => $cityId])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->actingAs($admin)->putJson("/api/admin/panel/communes/{$id}", ['name' => 'Comuna Ocho', 'code' => 'COM08', 'city_id' => $cityId])->assertOk();
        $this->assertSame('Comuna Ocho', Commune::find($id)->name);

        // El listado trae el nombre de la ciudad, no su id.
        $row = collect($this->actingAs($admin)->getJson('/api/admin/panel/communes?search=Ocho')->assertOk()->json('data'))->sole();
        $this->assertSame(City::find($cityId)->name, $row['_labels']['city_id']);
    }

    #[Test]
    public function eliminar_es_un_borrado_suave_con_papelera_y_restauracion(): void
    {
        $cityId = $this->cityId();
        $admin = $this->makeUser(self::COMMUNES_ALL);
        $id = $this->actingAs($admin)->postJson('/api/admin/panel/communes', ['name' => 'Temporal', 'code' => 'TMP', 'city_id' => $cityId])->json('data.id');

        $this->actingAs($admin)->deleteJson("/api/admin/panel/communes/{$id}")->assertOk();

        // La fila sigue en la base (marcada), pero ya no aparece en el listado.
        $this->assertSoftDeleted('communes', ['id' => $id]);
        $this->assertNotContains($id, collect($this->actingAs($admin)->getJson('/api/admin/panel/communes')->json('data'))->pluck('id')->all());

        $trash = $this->actingAs($admin)->getJson('/api/admin/panel/communes?trashed=1')->assertOk();
        $this->assertSame([$id], collect($trash->json('data'))->pluck('id')->all());
        $this->assertSame(1, $this->actingAs($admin)->getJson('/api/admin/panel/communes')->json('meta.trashed_total'));

        // Crear otra igual: se pide restaurar la de la papelera en vez de duplicar.
        $response = $this->actingAs($admin)->postJson('/api/admin/panel/communes', ['name' => 'Temporal', 'code' => 'TMP', 'city_id' => $cityId])->assertUnprocessable();
        $this->assertStringContainsString('papelera', $response->json('errors.code.0'));

        $this->actingAs($admin)->postJson("/api/admin/panel/communes/{$id}/restore")->assertOk();
        $this->assertNotSoftDeleted('communes', ['id' => $id]);
    }

    #[Test]
    public function no_se_elimina_lo_que_esta_en_uso(): void
    {
        $this->cityId();
        $commune = Commune::firstOrFail(); // tiene el "Barrio Base"
        $admin = $this->makeUser(self::COMMUNES_ALL);

        $response = $this->actingAs($admin)->deleteJson("/api/admin/panel/communes/{$commune->id}")->assertUnprocessable();
        $this->assertStringContainsString('1 barrio(s)', $response->json('errors.resource.0'));
        $this->assertNotSoftDeleted('communes', ['id' => $commune->id]);
    }

    #[Test]
    public function las_personas_guardan_el_formato_estandar_y_no_repiten_documento(): void
    {
        $admin = $this->makeUser(['persons.view', 'persons.create', 'persons.update', 'persons.delete']);
        $documentTypeId = Person::firstOrFail()->document_type_id;

        $id = $this->actingAs($admin)->postJson('/api/admin/panel/persons', [
            'document_type_id' => $documentTypeId, 'document_number' => '1.070.555.444', 'first_name' => 'MARÍA', 'last_name' => 'de la ROSA',
        ])->assertCreated()->json('data.id');

        $person = Person::find($id);
        $this->assertSame(['1070555444', 'María', 'De la Rosa', true], [$person->document_number, $person->first_name, $person->last_name, (bool) $person->is_active]);

        $this->actingAs($admin)->postJson('/api/admin/panel/persons', [
            'document_type_id' => $documentTypeId, 'document_number' => '1070555444', 'first_name' => 'Otra', 'last_name' => 'Persona',
        ])->assertUnprocessable()->assertJsonValidationErrors('document_number');

        // Una persona con cuenta de usuario no se elimina.
        $withAccount = $this->actingAs($admin)->deleteJson("/api/admin/panel/persons/{$admin->person_id}")->assertUnprocessable();
        $this->assertStringContainsString('cuenta(s) de usuario', $withAccount->json('errors.resource.0'));

        // Eliminada, también queda inactiva.
        $this->actingAs($admin)->deleteJson("/api/admin/panel/persons/{$id}")->assertOk();
        $this->assertFalse((bool) Person::withTrashed()->find($id)->is_active);
    }

    #[Test]
    public function los_usuarios_se_eliminan_con_las_reglas_de_seguridad(): void
    {
        $admin = $this->makeUser(['users.view', 'users.delete', 'users.create']);
        $otro = $this->makeUser();

        // Nadie se elimina a sí mismo.
        $this->actingAs($admin)->deleteJson("/api/admin/panel/users/{$admin->id}")->assertUnprocessable();

        // No se administra una cuenta con más permisos que la propia (regla anti-escalada).
        $jefe = $this->makeUser(['users.view', 'users.delete', 'roles.view', 'roles.update']);
        $this->actingAs($admin)->deleteJson("/api/admin/panel/users/{$jefe->id}")->assertForbidden();

        $this->actingAs($admin)->deleteJson("/api/admin/panel/users/{$otro->id}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $otro->id]);

        // Eliminado: ya no puede iniciar sesión (para el sistema la cuenta no existe).
        $this->postJson('/api/logout');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['identity' => $otro->username, 'password' => 'secret-password'])->assertUnauthorized();

        // Crear cuentas no es del Panel: lo dice en vez de crear una cuenta sin rol.
        $response = $this->actingAs($admin)->postJson('/api/admin/panel/users', ['username' => 'x'])->assertUnprocessable();
        $this->assertStringContainsString('Usuarios y accesos', $response->json('errors.resource.0'));
        $this->assertFalse(User::where('username', 'x')->exists());
    }

    #[Test]
    public function los_roles_del_sistema_o_en_uso_no_se_eliminan(): void
    {
        $admin = $this->makeUser(['roles.view', 'roles.delete']);
        $super = Role::create(['name' => 'super_admin', 'display_name' => 'Super Admin', 'is_active' => true]);
        $libre = Role::create(['name' => 'sin_uso', 'display_name' => 'Sin uso', 'is_active' => true]);
        $enUso = $admin->roles()->firstOrFail();

        $this->actingAs($admin)->deleteJson("/api/admin/panel/roles/{$super->id}")->assertUnprocessable();
        $this->actingAs($admin)->deleteJson("/api/admin/panel/roles/{$enUso->id}")->assertUnprocessable();

        $this->actingAs($admin)->deleteJson("/api/admin/panel/roles/{$libre->id}")->assertOk();
        $this->assertSoftDeleted('roles', ['id' => $libre->id]);
    }

    #[Test]
    public function un_candidato_ya_oficial_no_se_edita_ni_se_elimina_desde_los_borradores(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Oficial'));
        $admin = $this->makeUser(['candidate_drafts.view', 'candidate_drafts.update', 'candidate_drafts.delete']);

        $official = CandidateDraft::create(['election_id' => $election->id, 'first_name' => 'Ana', 'last_name' => 'Oficial', 'is_processed' => true, 'review_status' => 'approved']);
        $pending = CandidateDraft::create(['election_id' => $election->id, 'first_name' => 'Beto', 'last_name' => 'Pendiente']);

        $this->actingAs($admin)->putJson("/api/admin/panel/candidate_drafts/{$official->id}", ['first_name' => 'Cambiado', 'last_name' => 'X'])->assertUnprocessable();
        $this->actingAs($admin)->deleteJson("/api/admin/panel/candidate_drafts/{$official->id}")->assertUnprocessable();
        $this->assertSame('Ana', $official->fresh()->first_name);

        $this->actingAs($admin)->putJson("/api/admin/panel/candidate_drafts/{$pending->id}", ['first_name' => 'beto', 'last_name' => 'CORREGIDO'])->assertOk();
        $this->assertSame('Corregido', $pending->fresh()->last_name);
    }

    #[Test]
    public function lo_que_esta_en_la_papelera_se_restaura_si_el_sistema_lo_vuelve_a_necesitar(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Papelera'));
        $admin = $this->makeUser([
            'slates.view', 'slates.delete', 'persons.view', 'persons.delete',
            'candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update', 'candidate_drafts.approve', 'candidate_drafts.promote',
        ]);

        // Plancha 1 vacía y una persona sin uso, ambas a la papelera.
        $slate = \App\Models\Slate::create(['election_id' => $election->id, 'code' => 'P1', 'name' => 'Plancha 1', 'is_active' => true]);
        $person = Person::create(['document_type_id' => Person::firstOrFail()->document_type_id, 'document_number' => '8880001', 'first_name' => 'Ana', 'last_name' => 'Vuelve']);
        $this->actingAs($admin)->deleteJson("/api/admin/panel/slates/{$slate->id}")->assertOk();
        $this->actingAs($admin)->deleteJson("/api/admin/panel/persons/{$person->id}")->assertOk();

        // Registrar la Plancha 1 con esa misma persona y oficializarla: sin esto,
        // el sistema intentaba crear duplicados y la base los rechazaba (error 500).
        $batch = $this->actingAs($admin)->postJson('/api/secretary/planchas/drafts', [
            'election_id' => $election->id,
            'slate_code' => 'P1',
            'review_page_data' => ['bloques' => [['titulo' => 'Directiva', 'cargos' => [
                ['puesto' => 'PRESIDENTE', 'nombre' => 'Ana Vuelve', 'identificacion' => '8880001'],
            ]]]],
        ])->assertCreated()->json('data.capture_batch_uuid');
        $this->actingAs($admin)->postJson('/api/secretary/planchas/drafts/decision/batch', ['decision' => 'approved', 'capture_batch_uuid' => $batch])->assertOk();
        $promotion = $this->actingAs($admin)->postJson('/api/secretary/planchas/drafts/promote', ['capture_batch_uuid' => $batch])->assertOk();

        $this->assertSame(1, $promotion->json('data.processed'));
        $this->assertNotSoftDeleted('slates', ['id' => $slate->id]);
        $this->assertNotSoftDeleted('persons', ['id' => $person->id]);
        $this->assertTrue((bool) $slate->fresh()->is_active);
        $this->assertSame(1, Person::withTrashed()->where('document_number', '8880001')->count(), 'No se duplicó la persona.');
    }

    #[Test]
    public function solo_se_administran_las_tablas_del_catalogo(): void
    {
        $user = $this->makeUser(['audit_logs.view', 'states.view']);

        // La bitácora y las tablas internas no están en el Panel.
        $this->actingAs($user)->getJson('/api/admin/panel/audit_logs')->assertNotFound();
        $this->actingAs($user)->getJson('/api/admin/panel/sessions')->assertNotFound();

        // Una tabla de solo lectura no acepta cambios aunque se fuerce la petición.
        $this->actingAs($user)->getJson('/api/admin/panel/states')->assertOk();
        $this->actingAs($user)->postJson('/api/admin/panel/states', ['name' => 'X', 'code' => 'X'])->assertForbidden();
    }
}
