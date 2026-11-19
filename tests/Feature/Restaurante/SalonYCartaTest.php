<?php

namespace Tests\Feature\Restaurante;

use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\Pedido;
use App\Services\Restaurante\GestionarCartaService;
use App\Services\Restaurante\GestionarPedidoService;
use App\Services\Restaurante\GestionarSalonService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalonYCartaTest extends TestCase
{
    use PreparaBaseRestaurante;

    private GestionarSalonService $salon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararBaseRestaurante();
        $this->salon = app(GestionarSalonService::class);
    }

    public function test_crea_area_y_mesa_normalizando_el_codigo(): void
    {
        $area = $this->salon->guardarArea(['nombre' => ' Barra '], 1);
        $mesa = $this->salon->guardarMesa(['id_area' => $area->id_area, 'codigo' => ' b1 ', 'capacidad' => 2], 1);

        $this->assertSame('Barra', $area->nombre);
        $this->assertSame('B1', $mesa->codigo);
        $this->assertSame(Mesa::DISPONIBLE, $mesa->estado);
    }

    public function test_rechaza_duplicados_y_areas_de_otra_sucursal(): void
    {
        $casos = [
            'área repetida (sin distinguir mayúsculas)' => fn () => $this->salon->guardarArea(['nombre' => 'SALÓN'], 1),
            'mesa repetida' => fn () => $this->salon->guardarMesa(['id_area' => 1, 'codigo' => 'm1'], 1),
            'área de otra sucursal' => fn () => $this->salon->guardarMesa(['id_area' => 2, 'codigo' => 'X1'], 1),
            'capacidad inválida' => fn () => $this->salon->guardarMesa(['id_area' => 1, 'codigo' => 'X2', 'capacidad' => 0], 1),
        ];

        foreach ($casos as $caso => $accion) {
            try {
                $accion();
                $this->fail("Se aceptó: {$caso}");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        // El mismo código sí puede existir en otra sucursal.
        $this->assertSame('M1', $this->salon->guardarMesa(['id_area' => 2, 'codigo' => 'M1'], 2)->codigo);
    }

    public function test_estados_manuales_de_mesa(): void
    {
        $this->assertSame(Mesa::RESERVADA, $this->salon->cambiarEstadoMesa(1, Mesa::RESERVADA, 1)->estado);

        // Una mesa reservada recibe la cuenta de quien reservó.
        app(GestionarPedidoService::class)->abrir(['tipo' => Pedido::MESA, 'id_mesa' => 1, 'clave_idempotencia' => 'r'], 10, 1);
        $this->assertSame('OCUPADA', $this->estadoMesa(1));

        foreach ([[1, Mesa::FUERA_SERVICIO], [2, Mesa::OCUPADA]] as [$mesa, $estado]) {
            try {
                $this->salon->cambiarEstadoMesa($mesa, $estado, 1);
                $this->fail("Se aplicó {$estado} a la mesa {$mesa}.");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_no_se_desactiva_una_mesa_o_area_con_cuenta_abierta(): void
    {
        app(GestionarPedidoService::class)->abrir(['tipo' => Pedido::MESA, 'id_mesa' => 1, 'clave_idempotencia' => 'a'], 10, 1);

        try {
            $this->salon->guardarMesa(['id_area' => 1, 'codigo' => 'M1', 'activo' => false], 1, 1);
            $this->fail('Se desactivó una mesa ocupada.');
        } catch (DomainException) {
        }

        $this->expectExceptionMessage('cuentas abiertas');
        $this->salon->guardarArea(['nombre' => 'Salón', 'activo' => false], 1, 1);
    }

    public function test_agotados_por_sucursal_y_asignacion_de_modificadores(): void
    {
        $carta = app(GestionarCartaService::class);

        $carta->marcarAgotado(2, true, 10, 1);
        $carta->marcarAgotado(2, true, 10, 1);
        $this->assertSame([2], $carta->agotados(1));
        $this->assertSame([], $carta->agotados(2));
        $carta->marcarAgotado(2, false, 10, 1);
        $this->assertSame([], $carta->agotados(1));

        $carta->asignarGrupos(1, [2, 1]);
        $this->assertSame(
            [2 => 0, 1 => 1],
            DB::table('restaurante.producto_grupo_modificador')->where('id_producto', 1)->orderBy('orden')->pluck('orden', 'id_grupo')->map(fn ($o) => (int) $o)->all(),
        );

        try {
            $carta->asignarGrupos(3, [1]);
            $this->fail('Se asignaron modificadores a un insumo.');
        } catch (DomainException) {
        }

        $this->assertSame('PREPARADO', $carta->cambiarTipo(2, 'PREPARADO')->tipo_producto);
        $this->expectException(DomainException::class);
        $carta->cambiarTipo(2, 'OTRO');
    }
}
