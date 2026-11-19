<?php

namespace Tests\Feature\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Models\TrabajoImpresion;
use App\Services\Restaurante\EnviarComandaService;
use App\Services\Restaurante\GestionarEstacionesService;
use App\Services\Restaurante\GestionarPedidoService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CloudPrntTest extends TestCase
{
    use PreparaBaseRestaurante;

    private const CLAVE = 'clave-de-prueba-no-productiva';
    private const API = 'https://localhost/api/restaurante/cloudprnt/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararBaseRestaurante();
        DB::statement("ATTACH DATABASE ':memory:' AS seguridad");
        DB::statement('CREATE TABLE seguridad.usuarios (id_usuario INTEGER PRIMARY KEY, nombres TEXT)');
        DB::table('seguridad.usuarios')->insert(['id_usuario' => 10, 'nombres' => 'Ana']);
        DB::table('catalogos.impresoras')->insert([
            ['id_impresora' => 1, 'id_sucursal' => 1, 'nombre' => 'Cocina Centro', 'cloudprnt_clave_hash' => hash('sha256', self::CLAVE)],
            ['id_impresora' => 2, 'id_sucursal' => 2, 'nombre' => 'Cocina Norte', 'cloudprnt_clave_hash' => hash('sha256', 'clave-otra-impresora')],
        ]);

        app(GestionarEstacionesService::class)->guardar(['nombre' => 'Caliente', 'por_defecto' => true, 'id_impresora' => 1], 1);

        $pedidos = app(GestionarPedidoService::class);
        $pedido = $pedidos->abrir(['tipo' => Pedido::MESA, 'id_mesa' => 1, 'clave_idempotencia' => 'abrir'], 10, 1);
        $pedidos->agregarItems($pedido->id_pedido, [[...$this->lomo('lomo'), 'observacion' => 'Sin cebolla']], 10, 1);
        app(EnviarComandaService::class)->enviar($pedido->id_pedido, 'envio', 10, 1);
    }

    public function test_imprime_una_vez_y_acepta_confirmaciones_repetidas(): void
    {
        $job = TrabajoImpresion::query()->sole();
        $this->assertNull($job->id_terminal);
        $this->assertSame(1, $job->id_impresora_cloudprnt);
        $token = (string) $job->getKey();
        $this->withBasicAuth('1', self::CLAVE);

        $this->postJson(self::API.'1', ['statusCode' => '200 OK'])
            ->assertOk()->assertJson(['jobReady' => true, 'mediaTypes' => ['text/plain'], 'jobToken' => $token]);
        $this->postJson(self::API.'1', ['statusCode' => '200 OK'])->assertJsonPath('jobToken', $token);
        $this->delete(self::API."1?code=200&token={$token}")->assertConflict();
        $descarga = self::API."1?type=text%2Fplain&token={$token}";
        $this->get($descarga)->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSeeText('Lomo saltado')->assertSeeText('Sin cebolla')->assertDontSeeText('S/');
        $this->get($descarga)->assertConflict();
        $this->postJson(self::API.'1', ['statusCode' => '200 OK'])->assertJsonPath('jobReady', false);
        $this->delete(self::API."1?code=200%20OK&token={$token}")->assertOk();
        $this->delete(self::API."1?code=200%20OK&token={$token}")->assertOk();
        $this->assertSame('CONFIRMADO', $job->refresh()->estado);
        $this->assertSame(1, $job->intentos);
        $this->assertDatabaseHas('catalogos.impresoras', ['id_impresora' => 1, 'ultimo_resultado_impresion' => 'correcta']);
    }

    public function test_exige_https_credencial_propia_y_trabajo_de_su_impresora(): void
    {
        $token = TrabajoImpresion::query()->sole()->getKey();
        $this->postJson(self::API.'1', ['statusCode' => '200 OK'])->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Basic realm="CloudPRNT"');
        $this->withBasicAuth('1', self::CLAVE)->postJson('http://localhost/api/restaurante/cloudprnt/1', ['statusCode' => '200 OK'])->assertForbidden();
        $this->postJson(self::API.'2', ['statusCode' => '200 OK'])->assertUnauthorized();
        $this->withBasicAuth('2', 'clave-otra-impresora')->get(self::API."2?type=text%2Fplain&token={$token}")->assertNotFound();
        $this->delete(self::API."2?code=200&token={$token}")->assertNotFound();
    }

    public function test_una_descarga_sin_confirmacion_no_se_envia_de_nuevo(): void
    {
        $job = TrabajoImpresion::query()->sole();
        $token = $job->getKey();
        $this->withBasicAuth('1', self::CLAVE)->get(self::API."1?type=text%2Fplain&token={$token}")->assertOk();

        $this->travel(3)->minutes();
        $this->postJson(self::API.'1', ['statusCode' => '200 OK', 'printingInProgress' => true])->assertJsonPath('jobReady', false);
        $this->assertSame('ENTREGADO_AGENTE', $job->refresh()->estado);
        $this->postJson(self::API.'1', ['statusCode' => '401 Paper Empty'])->assertJsonPath('jobReady', false);
        $this->assertSame('REQUIERE_REVISION', $job->refresh()->estado);
        $this->get(self::API."1?type=text%2Fplain&token={$token}")->assertConflict();

        $this->delete(self::API."1?code=200&token={$token}")->assertOk();
        $this->assertSame('CONFIRMADO', $job->refresh()->estado);
    }
}
