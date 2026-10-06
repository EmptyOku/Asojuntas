<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Role;
use App\Models\ScrutinyRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Cada operación sobre una tabla exige su propio permiso: ver no permite
 * crear, editar no permite eliminar, y las acciones especiales (aprobar,
 * restablecer contraseñas) van aparte del CRUD.
 */
class PermissionsByTableTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    #[Test]
    public function ver_una_tabla_no_permite_crear_editar_ni_eliminar(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Solo Lectura');
        $lector = $this->makeUser(['neighborhoods.view', 'persons.view']);

        $this->actingAs($lector)->getJson('/api/admin/neighborhoods')->assertOk();
        $this->actingAs($lector)->postJson('/api/admin/neighborhoods', [])->assertForbidden();
        $this->actingAs($lector)->putJson("/api/admin/neighborhoods/{$barrio->id}", [])->assertForbidden();
        $this->actingAs($lector)->deleteJson("/api/admin/neighborhoods/{$barrio->id}")->assertForbidden();

        $this->actingAs($lector)->getJson('/api/admin/persons')->assertOk();
        $this->actingAs($lector)->postJson('/api/admin/persons', [])->assertForbidden();
    }

    #[Test]
    public function editar_no_permite_eliminar(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Editable');
        $editor = $this->makeUser(['neighborhoods.view', 'neighborhoods.update']);

        // Llega al controlador (422 por datos vacíos), no lo frena el permiso.
        $this->actingAs($editor)->putJson("/api/admin/neighborhoods/{$barrio->id}", [])->assertStatus(422);
        $this->actingAs($editor)->deleteJson("/api/admin/neighborhoods/{$barrio->id}")->assertForbidden();
    }

    #[Test]
    public function ver_actas_no_permite_aprobarlas(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Acta'));
        $record = ScrutinyRecord::create([
            'election_id' => $election->id,
            'polling_table_id' => $this->makePollingTable($election)->id,
            'record_number' => 'ACTA-1',
            'status' => 'pending_review',
        ]);

        // Quien solo revisa puede abrir el acta, pero no decidir sobre ella.
        $revisor = $this->makeUser(['scrutiny_records.view']);
        $this->actingAs($revisor)->getJson("/api/admin/audit-records/{$record->id}")->assertOk();
        $this->actingAs($revisor)->postJson("/api/admin/audit-records/{$record->id}/decision", ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('pending_review', $record->fresh()->status);

        $aprobador = $this->makeUser(['scrutiny_records.view', 'scrutiny_records.approve']);
        $this->actingAs($aprobador)->postJson("/api/admin/audit-records/{$record->id}/decision", ['decision' => 'approved'])->assertOk();
    }

    #[Test]
    public function restablecer_contrasenas_es_un_permiso_aparte_de_editar_usuarios(): void
    {
        $target = $this->makeUser();
        $payload = ['password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123'];

        $editor = $this->makeUser(['users.view', 'users.update']);
        $this->actingAs($editor)->postJson("/api/admin/users/{$target->id}/reset-password", $payload)->assertForbidden();

        $soporte = $this->makeUser(['users.view', 'users.reset_password']);
        $this->actingAs($soporte)->postJson("/api/admin/users/{$target->id}/reset-password", $payload)->assertOk();
    }

    #[Test]
    public function el_asistente_de_cuentas_exige_crear_usuarios_y_crear_personas(): void
    {
        $payload = [
            'document_type_id' => DocumentType::firstOrCreate(['code' => 'CC'], ['name' => 'Cédula de ciudadanía'])->id,
            'document_number' => '1070999888',
            'first_name' => 'Ana', 'last_name' => 'Paz',
            'username' => 'ana.paz', 'email' => 'ana.paz@example.test',
            'password' => 'ClaveSegura123', 'password_confirmation' => 'ClaveSegura123',
            'roles' => [Role::create(['name' => 'consulta', 'display_name' => 'Consulta', 'is_active' => true])->id],
        ];

        // Toca dos tablas: con solo "crear usuarios" no alcanza.
        $soloUsuarios = $this->makeUser(['users.view', 'users.create']);
        $this->actingAs($soloUsuarios)->postJson('/api/admin/users-complete', $payload)->assertForbidden();
        $this->assertDatabaseMissing('persons', ['document_number' => '1070999888']);

        $completo = $this->makeUser(['users.view', 'users.create', 'persons.view', 'persons.create']);
        $this->actingAs($completo)->postJson('/api/admin/users-complete', $payload)->assertCreated();
    }
}
