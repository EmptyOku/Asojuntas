<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Cubre el endurecimiento del login: regeneración de sesión, límite de
 * intentos y registro del último acceso.
 */
class AuthenticationHardeningTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
    }

    // Nota: la regeneración de sesión (AuthController::login) no se cubre aquí.
    // El harness de pruebas de Laravel no reproduce fielmente la fijación de
    // sesión —una aserción sobre session()->getId() pasa con y sin la
    // corrección—, así que una prueba de ese tipo daría confianza falsa.

    #[Test]
    public function el_login_registra_el_ultimo_acceso(): void
    {
        $usuario = $this->makeUser();
        $this->assertNull($usuario->last_login_at);

        $this->postJson('/api/login', [
            'identity' => $usuario->email,
            'password' => 'secret-password',
        ])->assertOk();

        $this->assertNotNull($usuario->fresh()->last_login_at);
    }

    #[Test]
    public function el_login_limita_los_intentos_fallidos(): void
    {
        $usuario = $this->makeUser();

        for ($intento = 1; $intento <= 5; $intento++) {
            $this->postJson('/api/login', [
                'identity' => $usuario->email,
                'password' => 'clave-incorrecta',
            ])->assertStatus(401);
        }

        // El sexto intento dentro del minuto ya no llega al controlador.
        $this->postJson('/api/login', [
            'identity' => $usuario->email,
            'password' => 'clave-incorrecta',
        ])->assertStatus(429);
    }

    #[Test]
    public function un_usuario_inactivo_no_puede_entrar(): void
    {
        $usuario = $this->makeUser();
        $usuario->forceFill(['is_active' => false])->save();

        $this->postJson('/api/login', [
            'identity' => $usuario->email,
            'password' => 'secret-password',
        ])->assertStatus(401);
    }
}
