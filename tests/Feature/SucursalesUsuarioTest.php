<?php

namespace Tests\Feature;

use App\Http\Requests\Seguridad\StoreUsuarioRequest;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Asignación de sucursales adicionales desde el formulario de usuarios. */
class SucursalesUsuarioTest extends TestCase
{
    public function test_solo_un_administrador_puede_asignar_sucursales_adicionales(): void
    {
        $this->assertTrue($this->solicitud('Administrador General', ['sucursales_adicionales' => [2]])->authorize());
        $this->assertFalse($this->solicitud('Supervisor', ['sucursales_adicionales' => [2]])->authorize());
        // Un gestor de sede sigue pudiendo editar usuarios si no toca las sucursales.
        $this->assertTrue($this->solicitud('Supervisor', ['nombres' => 'CAJERO'])->authorize());
    }

    public function test_las_adicionales_excluyen_la_sede_y_los_repetidos(): void
    {
        $request = $this->solicitud('Administrador General', ['id_sucursal' => 1, 'sucursales_adicionales' => ['3', 1, 2, 3]]);
        $request->setValidator(Validator::make($request->all(), [
            'id_sucursal' => ['required', 'integer'],
            'sucursales_adicionales' => ['array'],
            'sucursales_adicionales.*' => ['integer'],
        ]));

        $this->assertSame([3, 2], $request->sucursalesAdicionales());
    }

    public function test_sin_el_campo_no_se_gestionan_las_sucursales(): void
    {
        $request = $this->solicitud('Supervisor', ['id_sucursal' => 1]);
        $request->setValidator(Validator::make($request->all(), ['id_sucursal' => ['required', 'integer']]));

        // null indica al controlador que conserve las asignaciones existentes.
        $this->assertNull($request->sucursalesAdicionales());
    }

    private function solicitud(string $rol, array $datos): StoreUsuarioRequest
    {
        $usuario = new User();
        $usuario->setRelation('rol', new Rol(['nombre' => $rol, 'estado' => true]));

        $request = StoreUsuarioRequest::create('/usuarios', 'POST', $datos);
        $request->setUserResolver(fn (): User => $usuario);

        return $request;
    }
}
