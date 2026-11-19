<?php

namespace Tests\Feature;

use App\Http\Controllers\Operaciones\TurnoCajaController;
use App\Http\Requests\Seguridad\StoreUsuarioRequest;
use App\Models\Rol;
use App\Models\User;
use App\Services\Caja\GestionarTurnoCajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class PinCajeroTest extends TestCase
{
    public function test_el_pin_se_hashea_y_no_se_serializa(): void
    {
        $user = new User(['pin_cajero' => '4821']);

        $hash = $user->getAttributes()['pin_cajero'];

        $this->assertNotSame('4821', $hash);
        $this->assertTrue(Hash::check('4821', $hash));
        $this->assertArrayNotHasKey('pin_cajero', $user->toArray());
    }

    public function test_editar_atributos_sin_pin_conserva_el_hash_existente(): void
    {
        $user = new User(['pin_cajero' => '4821']);
        $hashOriginal = $user->getAttributes()['pin_cajero'];

        $user->fill(['nombres' => 'CAJERO DE PRUEBA']);

        $this->assertSame($hashOriginal, $user->getAttributes()['pin_cajero']);
    }

    public function test_solo_administrador_puede_enviar_un_nuevo_pin(): void
    {
        $administrador = $this->usuarioConRol('Administrador General');
        $gestor = $this->usuarioConRol('Supervisor');

        $this->assertTrue($this->solicitudConPin($administrador)->authorize());
        $this->assertFalse($this->solicitudConPin($gestor)->authorize());
    }

    public function test_valida_pin_correcto_rechaza_incorrecto_y_limita_intentos(): void
    {
        $user = new User(['pin_cajero' => '4821']);
        $user->forceFill(['id_usuario' => 9901]);
        $request = Request::create('/caja/aperturar', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $request->setUserResolver(fn (): User => $user);
        $key = 'caja-pin:9901|'.$request->ip();
        RateLimiter::clear($key);

        $controller = new TurnoCajaController(Mockery::mock(GestionarTurnoCajaService::class));
        $method = new \ReflectionMethod($controller, 'validarPinCajero');

        $method->invoke($controller, $request, '4821');
        $this->assertFalse(RateLimiter::tooManyAttempts($key, 3));

        foreach (range(1, 3) as $attempt) {
            try {
                $method->invoke($controller, $request, '0000');
                $this->fail('Un PIN inválido no debe autorizar una operación de caja.');
            } catch (ValidationException $exception) {
                $this->assertSame('El PIN de caja no es válido.', $exception->errors()['pin_cajero'][0]);
            }
        }

        $this->assertSame(3, RateLimiter::attempts($key));
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 3));

        try {
            $method->invoke($controller, $request, '4821');
            $this->fail('El cuarto intento debe quedar bloqueado temporalmente.');
        } catch (ValidationException $exception) {
            $this->assertSame('PIN temporalmente bloqueado. Solicita a un administrador que valide tu acceso.', $exception->errors()['pin_cajero'][0]);
        }
    }

    private function usuarioConRol(string $nombreRol): User
    {
        $user = new User();
        $user->setRelation('rol', new Rol(['nombre' => $nombreRol, 'estado' => true]));

        return $user;
    }

    private function solicitudConPin(User $user): StoreUsuarioRequest
    {
        $request = StoreUsuarioRequest::create('/usuarios', 'POST', ['pin_cajero' => '4821']);
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
