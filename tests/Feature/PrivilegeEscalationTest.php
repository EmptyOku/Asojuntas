<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Regla anti-escalada: nadie puede otorgar permisos que no tiene ni
 * administrar una cuenta con más permisos que la suya.
 */
class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private const ADMIN_PERMISSIONS = ['users.view', 'users.create', 'users.update', 'roles.view', 'roles.manage', 'roles.assign'];

    private function superAdminRole(): Role
    {
        $role = Role::create(['name' => 'super_admin_test', 'display_name' => 'Super Admin', 'is_active' => true]);
        $names = [...self::ADMIN_PERMISSIONS, 'users.delete'];
        $role->permissions()->sync(collect($names)->map(fn ($n) => Permission::firstOrCreate(['name' => $n], ['display_name' => $n, 'is_active' => true])->id)->all());

        return $role;
    }

    private function superAdmin(Role $role): User
    {
        $user = $this->makeUser();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    #[Test]
    public function un_administrador_no_puede_asignarse_un_rol_con_mas_permisos(): void
    {
        $superRole = $this->superAdminRole();
        $admin = $this->makeUser(self::ADMIN_PERMISSIONS);

        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$admin->id}/roles", ['roles' => [$superRole->id]])
            ->assertForbidden();

        $this->assertFalse($admin->fresh()->roles->contains('id', $superRole->id));
    }

    #[Test]
    public function un_administrador_no_puede_editar_ni_desactivar_el_rol_super_admin(): void
    {
        $superRole = $this->superAdminRole();
        $admin = $this->makeUser(self::ADMIN_PERMISSIONS);

        $this->actingAs($admin)
            ->putJson("/api/admin/roles/{$superRole->id}", ['name' => 'super_admin_test', 'display_name' => 'Hackeado', 'permissions' => ['users.view']])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson("/api/admin/roles/{$superRole->id}/toggle-active")
            ->assertForbidden();

        $this->assertSame('Super Admin', $superRole->fresh()->display_name);
        $this->assertTrue((bool) $superRole->fresh()->is_active);
    }

    #[Test]
    public function un_administrador_no_puede_tomar_la_cuenta_del_super_admin(): void
    {
        $superUser = $this->superAdmin($this->superAdminRole());
        $admin = $this->makeUser(self::ADMIN_PERMISSIONS);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$superUser->id}/reset-password", ['password' => 'Tomada12345', 'password_confirmation' => 'Tomada12345'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$superUser->id}/toggle-active")
            ->assertForbidden();

        $this->assertTrue((bool) $superUser->fresh()->is_active);
    }

    #[Test]
    public function un_administrador_no_puede_crear_un_rol_con_permisos_que_no_tiene(): void
    {
        $this->superAdminRole(); // crea el permiso users.delete
        $admin = $this->makeUser(self::ADMIN_PERMISSIONS);

        $this->actingAs($admin)
            ->postJson('/api/admin/roles', ['name' => 'rol_trampa', 'display_name' => 'Trampa', 'permissions' => ['users.delete']])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'rol_trampa']);
    }

    #[Test]
    public function el_listado_de_roles_marca_cuales_puede_asignar_cada_usuario(): void
    {
        $superRole = $this->superAdminRole();
        $admin = $this->makeUser(self::ADMIN_PERMISSIONS);

        $roles = collect($this->actingAs($admin)->getJson('/api/admin/roles')->assertOk()->json('data'));

        $this->assertFalse($roles->firstWhere('id', $superRole->id)['grantable']);
    }

    #[Test]
    public function un_usuario_solo_puede_tener_un_rol(): void
    {
        $admin = $this->makeUser(self::ADMIN_PERMISSIONS);
        $target = $this->makeUser();
        $one = Role::create(['name' => 'rol_uno', 'display_name' => 'Uno', 'is_active' => true]);
        $two = Role::create(['name' => 'rol_dos', 'display_name' => 'Dos', 'is_active' => true]);

        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$target->id}/roles", ['roles' => [$one->id, $two->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles');

        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$target->id}/roles", ['roles' => [$two->id]])
            ->assertOk();

        $this->assertSame([$two->id], $target->fresh()->roles->pluck('id')->all());
    }

    #[Test]
    public function el_super_admin_sigue_pudiendo_administrar_todo(): void
    {
        $superRole = $this->superAdminRole();
        $superUser = $this->superAdmin($superRole);
        $admin = $this->makeUser(self::ADMIN_PERMISSIONS);

        $this->actingAs($superUser)
            ->postJson("/api/admin/users/{$admin->id}/reset-password", ['password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123'])
            ->assertOk();

        $this->actingAs($superUser)
            ->putJson("/api/admin/roles/{$superRole->id}", ['name' => 'super_admin_test', 'display_name' => 'Super Admin', 'permissions' => [...self::ADMIN_PERMISSIONS, 'users.delete']])
            ->assertOk();
    }
}
