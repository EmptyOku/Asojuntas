<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Services\ElectoralAccessGuard;
use App\Support\PermissionCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Roles dinámicos, fase 1: catálogo de permisos, backfill de roles existentes,
 * separación jurado/secretaría y cálculo único de permisos para el SPA.
 */
class DynamicPermissionsTest extends TestCase
{
    use ElectoralScenario;
    use RefreshDatabase;

    #[Test]
    public function la_migracion_da_los_permisos_nuevos_a_quien_ya_llegaba_a_esas_pantallas(): void
    {
        $secretaria = $this->roleWith('secretaria_vieja', ['records.upload', 'records.review']);
        $admin = $this->roleWith('admin_viejo', ['users.view', 'elections.view', 'elections.update']);
        $jurado = $this->roleWith('jurado_viejo', ['records.upload']);

        $this->runPermissionsMigration();

        $this->assertEqualsCanonicalizing(
            ['records.upload', 'records.review', 'slates.view', 'slates.capture', 'slates.review', 'slates.promote'],
            $secretaria->fresh()->permissions->pluck('name')->all()
        );

        $this->assertEqualsCanonicalizing(
            ['users.view', 'elections.view', 'elections.update', 'dashboard.view', 'geography.view',
                'geography.manage', 'map.view', 'candidates.view', 'slates.view'],
            $admin->fresh()->permissions->pluck('name')->all()
        );

        // El jurado no hereda nada de secretaría.
        $this->assertSame(['records.upload'], $jurado->fresh()->permissions->pluck('name')->all());
    }

    #[Test]
    public function la_migracion_se_puede_correr_dos_veces_sin_duplicar(): void
    {
        $role = $this->roleWith('rol_doble', ['users.view']);

        $this->runPermissionsMigration();
        $this->runPermissionsMigration();

        $this->assertSame(1, Permission::where('name', 'dashboard.view')->count());
        $this->assertSame(2, $role->fresh()->permissions()->count());
    }

    #[Test]
    public function el_seeder_crea_todo_el_catalogo_y_super_admin_lo_recibe_completo(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (PermissionCatalog::names() as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where('name', 'super_admin')->firstOrFail();
        $this->assertEqualsCanonicalizing(PermissionCatalog::names(), $superAdmin->permissions->pluck('name')->all());

        $digitizer = Role::where('name', 'digitizer')->firstOrFail();
        $this->assertNotContains('slates.capture', $digitizer->permissions->pluck('name')->all());
    }

    #[Test]
    public function un_jurado_ya_no_entra_a_la_api_de_secretaria(): void
    {
        $jurado = $this->makeUser(['records.upload'], $this->makeNeighborhood('Barrio J'));

        $this->actingAs($jurado)->getJson('/api/secretary/planchas/drafts/grouped')->assertForbidden();
        $this->actingAs($jurado)->postJson('/api/secretary/planchas/drafts/promote')->assertForbidden();
    }

    #[Test]
    public function capturar_planchas_no_permite_oficializarlas(): void
    {
        $secretaria = $this->makeUser(['slates.capture'], $this->makeNeighborhood('Barrio S'));

        $this->actingAs($secretaria)->getJson('/api/secretary/planchas/drafts/grouped')->assertOk();
        $this->actingAs($secretaria)->postJson('/api/secretary/planchas/drafts/promote')->assertForbidden();
        $this->actingAs($secretaria)->postJson('/api/secretary/planchas/drafts/decision/batch')->assertForbidden();
    }

    #[Test]
    public function cada_pantalla_exige_su_propio_permiso(): void
    {
        $soloMapa = $this->makeUser(['map.view']);

        $this->actingAs($soloMapa)->getJson('/api/admin/neighborhoods/geo')->assertOk();
        $this->actingAs($soloMapa)->getJson('/api/admin/neighborhoods/report')->assertForbidden();
        $this->actingAs($soloMapa)->getJson('/api/admin/planchas/by-neighborhood')->assertForbidden();

        // elections.view ya no abre el mapa por sí solo.
        $soloElecciones = $this->makeUser(['elections.view']);
        $this->actingAs($soloElecciones)->getJson('/api/admin/neighborhoods/geo')->assertForbidden();
    }

    #[Test]
    public function el_spa_recibe_los_mismos_permisos_que_revisa_el_backend(): void
    {
        $user = $this->makeUser(['map.view']);
        // Permiso directo (sin rol): antes /api/user lo omitía aunque el middleware lo aceptaba.
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'audit.view'], ['display_name' => 'audit.view']));

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('permissions', fn (array $permissions) => collect($permissions)->sort()->values()->all() === ['audit.view', 'map.view']);
    }

    #[Test]
    public function el_listado_de_permisos_incluye_modulo_y_dependencias(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->makeUser(['roles.view']);

        $response = $this->actingAs($admin)->getJson('/api/admin/permissions')->assertOk();

        $promote = collect($response->json('data'))->firstWhere('name', 'slates.promote');
        $this->assertSame('slates', $promote['module']);
        $this->assertSame(['slates.review'], $promote['depends_on']);

        $review = collect($response->json('data'))->firstWhere('name', 'records.review');
        $this->assertNotNull($review['scope_note']);
    }

    #[Test]
    public function un_rol_nuevo_con_captura_de_actas_exige_barrio_aunque_no_se_llame_digitizer(): void
    {
        $admin = $this->makeUser(['roles.assign', 'users.update']);
        $sinBarrio = $this->makeUser([]);
        $coordinador = $this->roleWith('coordinador_territorial', ['records.upload']);

        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$sinBarrio->id}/roles", ['roles' => [$coordinador->id]])
            ->assertStatus(422);

        // Con records.review no está limitado a un barrio: no lo exige.
        $revisor = $this->roleWith('revisor_general', ['records.upload', 'records.review']);
        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$sinBarrio->id}/roles", ['roles' => [$revisor->id]])
            ->assertOk();
    }

    #[Test]
    public function solo_otro_jurado_ocupa_el_barrio(): void
    {
        $guard = app(ElectoralAccessGuard::class);
        $barrio = $this->makeNeighborhood('Barrio Unico');

        // Un consultor que vive en el barrio no cuenta como jurado.
        $this->makeUser(['elections.view'], $barrio);
        $this->assertFalse($guard->neighborhoodHasJury($barrio->id));

        $jurado = $this->makeUser(['records.upload'], $barrio);
        $this->assertTrue($guard->neighborhoodHasJury($barrio->id));
        $this->assertFalse($guard->neighborhoodHasJury($barrio->id, $jurado->id));
    }

    #[Test]
    public function el_listado_de_roles_indica_si_requieren_barrio(): void
    {
        $admin = $this->makeUser(['roles.view']);
        $this->roleWith('captura_planchas', ['slates.capture']);
        $this->roleWith('solo_consulta', ['map.view']);

        $roles = collect($this->actingAs($admin)->getJson('/api/admin/roles')->assertOk()->json('data'))
            ->keyBy('name');

        $this->assertTrue($roles['captura_planchas']['requires_neighborhood']);
        $this->assertFalse($roles['solo_consulta']['requires_neighborhood']);
    }

    #[Test]
    public function al_guardar_un_rol_se_agregan_sus_dependencias(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->makeUser(['roles.manage', 'roles.view']);

        $response = $this->actingAs($admin)->postJson('/api/admin/roles', [
            'name' => 'oficializador',
            'display_name' => 'Oficializador',
            'permissions' => ['slates.promote', Permission::where('name', 'geography.manage')->value('id')],
        ])->assertCreated();

        $this->assertEqualsCanonicalizing(
            ['slates.promote', 'slates.review', 'geography.manage', 'geography.view'],
            collect($response->json('data.permissions'))->pluck('name')->all()
        );
    }

    #[Test]
    public function no_se_puede_quitar_roles_manage_al_ultimo_que_lo_tiene(): void
    {
        $admin = $this->makeUser(['roles.manage', 'roles.view']);
        $rolDelAdmin = $admin->roles()->first();

        $this->actingAs($admin)->putJson("/api/admin/roles/{$rolDelAdmin->id}", [
            'name' => $rolDelAdmin->name,
            'display_name' => $rolDelAdmin->display_name,
            'permissions' => ['roles.view'],
        ])->assertStatus(422)->assertJsonValidationErrors('roles');

        // Se revirtió: el rol conserva roles.manage.
        $this->assertTrue($rolDelAdmin->fresh()->permissions->pluck('name')->contains('roles.manage'));

        $this->actingAs($admin)
            ->patchJson("/api/admin/roles/{$rolDelAdmin->id}/toggle-active")
            ->assertStatus(422);
        $this->assertTrue($rolDelAdmin->fresh()->is_active);
    }

    #[Test]
    public function con_otro_administrador_activo_si_se_puede_quitar(): void
    {
        $admin = $this->makeUser(['roles.manage', 'roles.view']);
        $this->makeUser(['roles.manage']);
        $rolDelAdmin = $admin->roles()->first();

        $this->actingAs($admin)->putJson("/api/admin/roles/{$rolDelAdmin->id}", [
            'name' => $rolDelAdmin->name,
            'display_name' => $rolDelAdmin->display_name,
            'permissions' => ['roles.view'],
        ])->assertOk();
    }

    #[Test]
    public function no_se_puede_dejar_sin_roles_de_administracion_al_ultimo_administrador(): void
    {
        $asignador = $this->makeUser(['roles.assign', 'users.update']);
        $unicoAdmin = $this->makeUser(['roles.manage']);
        $otroRol = $this->roleWith('consulta', ['map.view']);

        $this->actingAs($asignador)
            ->putJson("/api/admin/users/{$unicoAdmin->id}/roles", ['roles' => [$otroRol->id]])
            ->assertStatus(422);

        $this->actingAs($asignador)
            ->patchJson("/api/admin/users/{$unicoAdmin->id}/toggle-active")
            ->assertStatus(422);
        $this->assertTrue($unicoAdmin->fresh()->is_active);
    }

    #[Test]
    public function nadie_puede_desactivar_su_propia_cuenta(): void
    {
        $admin = $this->makeUser(['users.update']);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$admin->id}/toggle-active")
            ->assertStatus(422);
    }

    #[Test]
    public function el_login_devuelve_el_barrio_de_la_persona_igual_que_user(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Login');
        $jurado = $this->makeUser(['records.upload'], $barrio);

        // Sin esto, el dashboard del jurado decía "No hay barrio" hasta recargar.
        $this->postJson('/api/login', ['identity' => $jurado->username, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('user.person.neighborhood.name', 'Barrio Login');
    }

    private function roleWith(string $name, array $permissions): Role
    {
        $role = Role::create(['name' => $name, 'display_name' => $name, 'is_active' => true]);

        $role->permissions()->sync(
            collect($permissions)->map(fn (string $permission) => Permission::firstOrCreate(
                ['name' => $permission],
                ['display_name' => $permission, 'is_active' => true]
            )->id)->all()
        );

        return $role;
    }

    private function runPermissionsMigration(): void
    {
        $migration = require database_path('migrations/2026_09_24_120000_add_screen_and_slate_permissions.php');
        $migration->up();
    }
}
