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
 * Permisos por tabla y operación: catálogo, traducción de los roles que ya
 * existían, separación jurado/secretaría y cálculo único de permisos para el SPA.
 */
class DynamicPermissionsTest extends TestCase
{
    use ElectoralScenario;
    use RefreshDatabase;

    #[Test]
    public function la_migracion_traduce_cada_rol_al_esquema_por_tabla_sin_cambiar_lo_que_puede_hacer(): void
    {
        $secretaria = $this->roleWith('secretaria_vieja', ['slates.capture', 'slates.review']);
        $admin = $this->roleWith('admin_viejo', ['users.view', 'geography.manage', 'roles.manage']);
        $jurado = $this->roleWith('jurado_viejo', ['records.upload']);
        $revisor = $this->roleWith('revisor_viejo', ['records.review']);

        $this->runPermissionsMigration();

        $this->assertEqualsCanonicalizing(
            ['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update', 'candidate_drafts.approve', 'slates.view', 'slates.create', 'candidates.view'],
            $secretaria->fresh()->permissions->pluck('name')->all()
        );

        $this->assertEqualsCanonicalizing(
            ['users.view', 'persons.view', 'neighborhoods.view', 'neighborhoods.create', 'neighborhoods.update', 'neighborhoods.delete',
                'roles.view', 'roles.create', 'roles.update', 'roles.delete'],
            $admin->fresh()->permissions->pluck('name')->all()
        );

        // El jurado solo sube su acta: NO recibe "ver actas", que es el permiso del revisor.
        $this->assertSame(['scrutiny_records.create'], $jurado->fresh()->permissions->pluck('name')->all());

        // Quien revisaba actas las sigue pudiendo corregir y aprobar.
        $this->assertContains('scrutiny_records.approve', $revisor->fresh()->permissions->pluck('name')->all());
        $this->assertContains('scrutiny_block_results.update', $revisor->fresh()->permissions->pluck('name')->all());

        // Los permisos del esquema anterior ya no existen.
        $this->assertSame(0, Permission::whereIn('name', PermissionCatalog::legacyOnlyNames())->count());
    }

    #[Test]
    public function la_migracion_se_puede_correr_dos_veces_sin_cambiar_nada(): void
    {
        $role = $this->roleWith('rol_doble', ['users.view', 'records.upload']);

        $this->runPermissionsMigration();
        $afterFirst = $role->fresh()->permissions->pluck('name')->sort()->values()->all();

        $this->runPermissionsMigration();

        $this->assertSame($afterFirst, $role->fresh()->permissions->pluck('name')->sort()->values()->all());
        $this->assertSame(1, Permission::where('name', 'users.view')->count());
    }

    #[Test]
    public function el_catalogo_da_a_cada_tabla_solo_las_operaciones_que_tienen_sentido(): void
    {
        $names = PermissionCatalog::names();

        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $this->assertContains("persons.{$action}", $names);
            $this->assertContains("neighborhoods.{$action}", $names);
        }

        // La bitácora y las revisiones son historial: solo se ven.
        $this->assertSame(['audit_logs.view'], array_values(array_filter($names, fn ($n) => str_starts_with($n, 'audit_logs.'))));
        $this->assertNotContains('scrutiny_records.delete', $names);
        $this->assertNotContains('candidates.create', $names);
        $this->assertNotContains('permissions.create', $names);

        // Crear exige ver… salvo en las actas, donde el jurado crea sin revisar.
        $this->assertSame(['persons.view'], PermissionCatalog::find('persons.create')['depends_on']);
        $this->assertSame([], PermissionCatalog::find('scrutiny_records.create')['depends_on']);
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
        // El jurado no entra a la secretaría de planchas ni revisa actas de otros barrios.
        $this->assertNotContains('candidate_drafts.create', $digitizer->permissions->pluck('name')->all());
        $this->assertNotContains('scrutiny_records.view', $digitizer->permissions->pluck('name')->all());
        $this->assertContains('scrutiny_records.create', $digitizer->permissions->pluck('name')->all());
    }

    #[Test]
    public function un_jurado_ya_no_entra_a_la_api_de_secretaria(): void
    {
        $jurado = $this->makeUser(['scrutiny_records.create'], $this->makeNeighborhood('Barrio J'));

        $this->actingAs($jurado)->getJson('/api/secretary/planchas/drafts/grouped')->assertForbidden();
        $this->actingAs($jurado)->postJson('/api/secretary/planchas/drafts/promote')->assertForbidden();
    }

    #[Test]
    public function capturar_planchas_no_permite_oficializarlas(): void
    {
        $secretaria = $this->makeUser(['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update'], $this->makeNeighborhood('Barrio S'));

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

        // Ver elecciones no abre el mapa por sí solo.
        $soloElecciones = $this->makeUser(['elections.view']);
        $this->actingAs($soloElecciones)->getJson('/api/admin/neighborhoods/geo')->assertForbidden();
    }

    #[Test]
    public function el_spa_recibe_los_mismos_permisos_que_revisa_el_backend(): void
    {
        $user = $this->makeUser(['map.view']);
        // Permiso directo (sin rol): antes /api/user lo omitía aunque el middleware lo aceptaba.
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'audit_logs.view'], ['display_name' => 'audit_logs.view']));

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('permissions', fn (array $permissions) => collect($permissions)->sort()->values()->all() === ['audit_logs.view', 'map.view']);
    }

    #[Test]
    public function el_listado_de_permisos_incluye_modulo_y_dependencias(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->makeUser(['roles.view']);

        $response = $this->actingAs($admin)->getJson('/api/admin/permissions')->assertOk();

        // Una acción especial: no es un CRUD de tabla.
        $promote = collect($response->json('data'))->firstWhere('name', 'candidate_drafts.promote');
        $this->assertTrue($promote['special']);
        $this->assertSame(['candidate_drafts.view', 'candidate_drafts.approve'], $promote['depends_on']);

        // Un permiso de tabla: sabe su tabla y su operación.
        $create = collect($response->json('data'))->firstWhere('name', 'persons.create');
        $this->assertSame(['persons', 'create', false], [$create['module'], $create['action'], $create['special']]);

        $review = collect($response->json('data'))->firstWhere('name', 'scrutiny_records.view');
        $this->assertNotNull($review['scope_note']);

        // La matriz de roles se pinta con esto.
        $resources = collect($response->json('resources'))->keyBy('key');
        $this->assertSame(['view', 'create', 'update', 'delete'], $resources['persons']['actions']);
        $this->assertSame(['view'], $resources['audit_logs']['actions']);
        $this->assertNotNull($resources['audit_logs']['why']);
    }

    #[Test]
    public function un_rol_nuevo_con_captura_de_actas_exige_barrio_aunque_no_se_llame_digitizer(): void
    {
        // Tiene los permisos que asigna (regla anti-escalada, ver PrivilegeEscalationTest).
        $admin = $this->makeUser(['users.assign_role', 'users.update', 'persons.update', 'users.reset_password', 'scrutiny_records.create', 'scrutiny_records.view', 'scrutiny_records.approve']);
        $sinBarrio = $this->makeUser([]);
        $coordinador = $this->roleWith('coordinador_territorial', ['scrutiny_records.create']);

        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$sinBarrio->id}/roles", ['roles' => [$coordinador->id]])
            ->assertStatus(422);

        // Con "ver actas" no está limitado a un barrio: no lo exige.
        $revisor = $this->roleWith('revisor_general', ['scrutiny_records.create', 'scrutiny_records.view']);
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

        $jurado = $this->makeUser(['scrutiny_records.create'], $barrio);
        $this->assertTrue($guard->neighborhoodHasJury($barrio->id));
        $this->assertFalse($guard->neighborhoodHasJury($barrio->id, $jurado->id));
    }

    #[Test]
    public function el_listado_de_roles_indica_si_requieren_barrio(): void
    {
        $admin = $this->makeUser(['roles.view']);
        $this->roleWith('captura_planchas', ['candidate_drafts.create']);
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
        // Tiene los permisos que otorga, incluidas las dependencias que se agregan solas.
        $admin = $this->makeUser(['roles.create', 'roles.update', 'roles.view', 'candidate_drafts.promote', 'candidate_drafts.view', 'candidate_drafts.approve', 'neighborhoods.create', 'neighborhoods.update', 'neighborhoods.delete', 'neighborhoods.view']);

        $response = $this->actingAs($admin)->postJson('/api/admin/roles', [
            'name' => 'oficializador',
            'display_name' => 'Oficializador',
            'permissions' => ['candidate_drafts.promote', Permission::where('name', 'neighborhoods.delete')->value('id')],
        ])->assertCreated();

        $this->assertEqualsCanonicalizing(
            ['candidate_drafts.promote', 'candidate_drafts.approve', 'candidate_drafts.view', 'neighborhoods.delete', 'neighborhoods.view'],
            collect($response->json('data.permissions'))->pluck('name')->all()
        );
    }

    #[Test]
    public function no_se_puede_quitar_editar_roles_al_ultimo_que_lo_tiene(): void
    {
        $admin = $this->makeUser(['roles.create', 'roles.update', 'roles.view']);
        $rolDelAdmin = $admin->roles()->first();

        $this->actingAs($admin)->putJson("/api/admin/roles/{$rolDelAdmin->id}", [
            'name' => $rolDelAdmin->name,
            'display_name' => $rolDelAdmin->display_name,
            'permissions' => ['roles.view'],
        ])->assertStatus(422)->assertJsonValidationErrors('roles');

        // Se revirtió: el rol conserva "editar roles".
        $this->assertTrue($rolDelAdmin->fresh()->permissions->pluck('name')->contains('roles.update'));

        $this->actingAs($admin)
            ->patchJson("/api/admin/roles/{$rolDelAdmin->id}/toggle-active")
            ->assertStatus(422);
        $this->assertTrue($rolDelAdmin->fresh()->is_active);
    }

    #[Test]
    public function con_otro_administrador_activo_si_se_puede_quitar(): void
    {
        $admin = $this->makeUser(['roles.create', 'roles.update', 'roles.view']);
        $this->makeUser(['roles.create', 'roles.update']);
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
        $asignador = $this->makeUser(['users.assign_role', 'users.update', 'persons.update', 'users.reset_password', 'map.view']);
        $unicoAdmin = $this->makeUser(['roles.create', 'roles.update']);
        $otroRol = $this->roleWith('consulta', ['map.view']);

        // Quien no puede editar roles no puede administrar a quien sí puede
        // (regla anti-escalada, 403): lo frena antes del chequeo de "último
        // administrador", que sigue probado en no_se_puede_quitar_editar_roles_al_ultimo_que_lo_tiene.
        $this->actingAs($asignador)
            ->putJson("/api/admin/users/{$unicoAdmin->id}/roles", ['roles' => [$otroRol->id]])
            ->assertForbidden();

        $this->actingAs($asignador)
            ->patchJson("/api/admin/users/{$unicoAdmin->id}/toggle-active")
            ->assertForbidden();
        $this->assertTrue($unicoAdmin->fresh()->is_active);
        $this->assertTrue($unicoAdmin->fresh()->roles->pluck('name')->isNotEmpty());
    }

    #[Test]
    public function nadie_puede_desactivar_su_propia_cuenta(): void
    {
        $admin = $this->makeUser(['users.update', 'persons.update', 'users.reset_password']);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$admin->id}/toggle-active")
            ->assertStatus(422);
    }

    #[Test]
    public function el_login_devuelve_el_barrio_de_la_persona_igual_que_user(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Login');
        $jurado = $this->makeUser(['scrutiny_records.create'], $barrio);

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
        $migration = require database_path('migrations/2026_10_05_120000_permissions_by_table.php');
        $migration->up();
    }
}
