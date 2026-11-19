<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Asistente\AgenteAsistente;
use App\Services\Asistente\HerramientasAsistente;
use App\Services\Asistente\Llm\ClienteLlm;
use App\Services\Asistente\Llm\ErrorProveedorLlm;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AgenteAsistenteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'asistente.proveedores' => ['groq'],
            'asistente.conexiones.groq' => ['url' => 'https://groq.test/v1/chat/completions', 'token' => 'clave', 'modelo' => 'openai/gpt-oss-20b', 'razonamiento' => 'low'],
            'asistente.streaming' => false,
            'asistente.max_rondas' => 4,
        ]);
    }

    private function agente(HerramientasAsistente $herramientas): AgenteAsistente
    {
        $agente = Mockery::mock(AgenteAsistente::class, [$herramientas, new ClienteLlm()])->makePartial()->shouldAllowMockingProtectedMethods();
        $agente->shouldReceive('instrucciones')->andReturn('Instrucciones de prueba.');

        return $agente;
    }

    private function respuestaModelo(array $mensaje): array
    {
        return ['choices' => [['message' => ['role' => 'assistant'] + $mensaje]], 'usage' => ['total_tokens' => 100]];
    }

    public function test_ejecuta_la_herramienta_pedida_y_devuelve_la_redaccion_final(): void
    {
        $usuario = new User();
        $herramientas = Mockery::mock(HerramientasAsistente::class);
        $herramientas->shouldReceive('definiciones')->andReturn([['type' => 'function', 'function' => ['name' => 'resumen_ventas']]]);
        $herramientas->shouldReceive('etiqueta')->with('resumen_ventas')->andReturn('Consultando ventas');
        $herramientas->shouldReceive('ejecutar')->once()
            ->with('resumen_ventas', ['desde' => '2026-11-17', 'hasta' => '2026-11-17'], $usuario, 7)
            ->andReturn(['total' => 'S/ 150.50', 'comprobantes' => 3]);

        Http::fakeSequence()
            ->push($this->respuestaModelo(['content' => null, 'tool_calls' => [[
                'id' => 'llamada_1', 'type' => 'function',
                'function' => ['name' => 'resumen_ventas', 'arguments' => '{"desde":"2026-11-17","hasta":"2026-11-17"}'],
            ]]]))
            ->push($this->respuestaModelo(['content' => 'Ayer vendiste **S/ 150.50** en 3 comprobantes.']));

        $eventos = [];
        $respuesta = $this->agente($herramientas)->responder(
            '¿cuánto vendí ayer?',
            [['rol' => 'user', 'texto' => 'hola'], ['rol' => 'bot', 'texto' => 'Hola.']],
            $usuario,
            7,
            function (array $evento) use (&$eventos) { $eventos[] = $evento; },
        );

        $this->assertSame('Ayer vendiste **S/ 150.50** en 3 comprobantes.', $respuesta);
        $this->assertContains(['tipo' => 'estado', 'texto' => 'Consultando ventas'], $eventos);

        $segunda = Http::recorded()[1][0]->data();
        // system + historial (2) + pregunta + llamada del asistente + resultado de la herramienta.
        $this->assertCount(6, $segunda['messages']);
        $this->assertSame('tool', $segunda['messages'][5]['role']);
        $this->assertSame('llamada_1', $segunda['messages'][5]['tool_call_id']);
        $this->assertStringContainsString('S/ 150.50', $segunda['messages'][5]['content']);
        $this->assertSame('low', $segunda['reasoning_effort']);
    }

    public function test_la_ultima_ronda_no_ofrece_herramientas_para_evitar_bucles(): void
    {
        $herramientas = Mockery::mock(HerramientasAsistente::class);
        $herramientas->shouldReceive('definiciones')->andReturn([['type' => 'function', 'function' => ['name' => 'alertas_operativas']]]);
        $herramientas->shouldReceive('etiqueta')->andReturn('Revisando alertas');
        $herramientas->shouldReceive('ejecutar')->times(3)->andReturn(['productos_sin_stock' => 0]);

        $llamada = $this->respuestaModelo(['content' => null, 'tool_calls' => [['id' => 'x', 'type' => 'function', 'function' => ['name' => 'alertas_operativas', 'arguments' => '{}']]]]);
        Http::fakeSequence()->push($llamada)->push($llamada)->push($llamada)->push($this->respuestaModelo(['content' => 'Sin alertas.']));

        $this->assertSame('Sin alertas.', $this->agente($herramientas)->responder('alertas', [], new User(), 1));
        $this->assertArrayNotHasKey('tools', Http::recorded()[3][0]->data());
    }

    public function test_normaliza_montos_al_formato_peruano(): void
    {
        $this->assertSame('Total **S/ 8,898.00**', AgenteAsistente::normalizarMontos('Total **S/ 8 898,00**'));
        $this->assertSame('S/ 8,898.00 y S/ 197.73', AgenteAsistente::normalizarMontos('S/ 8.898,00 y S/ 197,73'));
        $this->assertSame('S/ 1,234,567.50', AgenteAsistente::normalizarMontos("S/\u{202F}1\u{202F}234\u{202F}567,50"));
        $this->assertSame('S/ 8,898.00 · S/ 0.69 · 1,888 kg', AgenteAsistente::normalizarMontos('S/ 8,898.00 · S/ 0.69 · 1,888 kg'));
    }

    public function test_advierte_al_modelo_si_consulta_un_anio_que_nadie_menciono(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-18 10:00', 'America/Lima'));
        $herramientas = Mockery::mock(HerramientasAsistente::class);
        $herramientas->shouldReceive('definiciones')->andReturn([['type' => 'function', 'function' => ['name' => 'productos_mas_vendidos']]]);
        $herramientas->shouldReceive('etiqueta')->andReturn('Calculando');
        $herramientas->shouldReceive('ejecutar')->andReturn(['productos' => []]);

        Http::fakeSequence()
            ->push($this->respuestaModelo(['content' => null, 'tool_calls' => [['id' => 'a', 'type' => 'function', 'function' => ['name' => 'productos_mas_vendidos', 'arguments' => '{"desde":"2023-10-28","hasta":"2023-11-17"}']]]]))
            ->push($this->respuestaModelo(['content' => 'Arroz.']));

        $this->agente($herramientas)->responder('¿y el producto estrella del 28 de octubre al 17 de noviembre?', [], new User(), 1);

        $resultado = json_decode(Http::recorded()[1][0]->data()['messages'][3]['content'], true);
        $this->assertStringContainsString('El año actual es 2026', $resultado['advertencia']);
    }

    public function test_no_advierte_si_el_anio_anterior_es_legitimo(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2027-01-05 10:00', 'America/Lima'));
        $herramientas = Mockery::mock(HerramientasAsistente::class);
        $herramientas->shouldReceive('definiciones')->andReturn([['type' => 'function', 'function' => ['name' => 'resumen_ventas']]]);
        $herramientas->shouldReceive('etiqueta')->andReturn('Consultando');
        $herramientas->shouldReceive('ejecutar')->andReturn(['total' => 'S/ 10.00']);

        Http::fakeSequence()
            ->push($this->respuestaModelo(['content' => null, 'tool_calls' => [['id' => 'a', 'type' => 'function', 'function' => ['name' => 'resumen_ventas', 'arguments' => '{"desde":"2026-12-01","hasta":"2026-12-31"}']]]]))
            ->push($this->respuestaModelo(['content' => 'S/ 10.00']));

        $this->agente($herramientas)->responder('ventas de diciembre', [], new User(), 1);

        $resultado = json_decode(Http::recorded()[1][0]->data()['messages'][3]['content'], true);
        $this->assertArrayNotHasKey('advertencia', $resultado);
    }

    public function test_sin_proveedores_disponibles_lanza_error_para_usar_el_respaldo(): void
    {
        $herramientas = Mockery::mock(HerramientasAsistente::class);
        $herramientas->shouldReceive('definiciones')->andReturn([]);
        Http::fake(['*' => Http::response(['error' => ['message' => 'servidor caído']], 500)]);

        $this->expectException(ErrorProveedorLlm::class);
        $this->agente($herramientas)->responder('hola', [], new User(), 1);
    }
}
