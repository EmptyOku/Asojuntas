<?php

namespace Tests\Feature;

use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Las rutas de administración exigen permiso, no solo sesión iniciada.
 * Las masivas (create-all / close-all) son las más sensibles.
 */
class AdminRoutePermissionTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    #[Test]
    public function un_jurado_no_puede_cerrar_todas_las_elecciones(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Jurado');
        $eleccion = $this->makeElection($barrio);
        $jurado = $this->makeUser(['records.upload'], $barrio);

        $this->actingAs($jurado)
            ->postJson('/api/admin/neighborhoods/elections/close-all')
            ->assertForbidden();

        $this->assertTrue(
            Election::query()->whereKey($eleccion->id)->value('is_active'),
            'La elección debía seguir activa tras el intento no autorizado.'
        );
    }

    #[Test]
    public function un_jurado_no_puede_crear_elecciones_en_masa(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Jurado');
        $jurado = $this->makeUser(['records.upload'], $barrio);

        $this->actingAs($jurado)
            ->postJson('/api/admin/neighborhoods/elections/create-all')
            ->assertForbidden();
    }

    #[Test]
    public function un_jurado_no_puede_listar_personas(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Jurado');
        $jurado = $this->makeUser(['records.upload'], $barrio);

        $this->actingAs($jurado)
            ->getJson('/api/admin/persons')
            ->assertForbidden();

        $this->actingAs($jurado)
            ->getJson('/api/admin/users/search-persons?search=per')
            ->assertForbidden();
    }

    #[Test]
    public function un_administrador_con_permiso_si_accede(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Admin');
        $admin = $this->makeUser(['users.view', 'elections.view', 'elections.update'], $barrio);

        $this->actingAs($admin)
            ->getJson('/api/admin/persons')
            ->assertOk();
    }

    #[Test]
    public function las_rutas_de_administracion_rechazan_a_los_anonimos(): void
    {
        $this->postJson('/api/admin/neighborhoods/elections/close-all')->assertUnauthorized();
        $this->getJson('/api/admin/persons')->assertUnauthorized();
    }
}
