<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Asistente "Nueva cuenta": persona y cuenta se crean en una sola petición
 * (antes eran dos, y abandonar el paso 2 dejaba personas sin cuenta).
 */
class UserWizardCreationTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'document_type_id' => DocumentType::firstOrCreate(['code' => 'CC'], ['name' => 'Cédula de ciudadanía'])->id,
            'document_number' => '1070123456',
            'first_name' => 'Ana',
            'last_name' => 'Paz',
            'username' => 'ana.paz',
            'email' => 'ana.paz@example.test',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
            'roles' => [Role::firstOrCreate(['name' => 'consulta'], ['display_name' => 'Consulta', 'is_active' => true])->id],
        ], $overrides);
    }

    private function admin(): User
    {
        return $this->makeUser(['users.view', 'users.create', 'roles.view', 'roles.assign']);
    }

    #[Test]
    public function crea_persona_y_cuenta_en_una_sola_peticion(): void
    {
        $admin = $this->admin();
        $payload = $this->payload();

        DB::enableQueryLog();
        $this->actingAs($admin)->postJson('/api/admin/users-complete', $payload)
            ->assertCreated()
            ->assertJsonPath('data.username', 'ana.paz');
        $queries = count(DB::getQueryLog());

        $user = User::where('username', 'ana.paz')->firstOrFail();
        $this->assertSame('1070123456', $user->person->document_number);
        $this->assertTrue(Hash::check('ClaveSegura123', $user->password), 'La contraseña se guarda cifrada.');
        $this->assertTrue($user->roles->contains('id', $payload['roles'][0]));

        // Antes: 8 consultas (persona) + 18 (cuenta) en dos peticiones.
        $this->assertLessThanOrEqual(20, $queries, "Demasiadas consultas: {$queries}");
    }

    #[Test]
    public function un_documento_ya_registrado_devuelve_error_en_el_documento(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/admin/users-complete', $this->payload())->assertCreated();

        $this->actingAs($admin)->postJson('/api/admin/users-complete', $this->payload([
            'first_name' => 'Otra', 'username' => 'otra', 'email' => 'otra@example.test',
        ]))->assertUnprocessable()->assertJsonValidationErrors('document_number');

        $this->assertSame(1, Person::where('document_number', '1070123456')->count());
        $this->assertFalse(User::where('username', 'otra')->exists());
    }

    #[Test]
    public function si_la_cuenta_falla_no_queda_una_persona_suelta(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/admin/users-complete', $this->payload())->assertCreated();

        // Usuario repetido: no debe registrarse la persona nueva.
        $this->actingAs($admin)->postJson('/api/admin/users-complete', $this->payload([
            'document_number' => '999888777',
        ]))->assertUnprocessable()->assertJsonValidationErrors('username');

        $this->assertFalse(Person::where('document_number', '999888777')->exists());
    }

    #[Test]
    public function no_permite_asignar_un_rol_con_permisos_que_no_tiene(): void
    {
        $role = Role::create(['name' => 'poderoso', 'display_name' => 'Poderoso', 'is_active' => true]);
        $role->givePermissionTo(\App\Models\Permission::firstOrCreate(['name' => 'users.delete'], ['display_name' => 'users.delete', 'is_active' => true]));

        $this->actingAs($this->admin())->postJson('/api/admin/users-complete', $this->payload(['roles' => [$role->id]]))
            ->assertForbidden();

        $this->assertFalse(Person::where('document_number', '1070123456')->exists());
    }
}
