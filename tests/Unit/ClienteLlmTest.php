<?php

namespace Tests\Unit;

use App\Services\Asistente\Llm\ClienteLlm;
use App\Services\Asistente\Llm\ErrorProveedorLlm;
use App\Services\Asistente\Llm\ProveedorLlm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClienteLlmTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'asistente.streaming' => true,
            'asistente.conexiones' => [
                'groq' => ['url' => 'https://groq.test/v1/chat/completions', 'token' => 'g', 'modelo' => 'openai/gpt-oss-20b', 'razonamiento' => 'low'],
                'openrouter' => ['url' => 'https://router.test/v1/chat/completions', 'token' => 'o', 'modelo' => 'meta/llama'],
                'sin_clave' => ['url' => 'https://x.test/v1/chat/completions', 'token' => null, 'modelo' => 'm'],
                'ollama' => ['url' => 'http://127.0.0.1:11434/v1/chat/completions', 'token' => null, 'modelo' => 'qwen', 'requiere_token' => false],
            ],
        ]);
    }

    public function test_omite_proveedores_incompletos_y_respeta_el_orden(): void
    {
        config(['asistente.proveedores' => ['sin_clave', 'ollama', 'groq', 'inexistente']]);

        $nombres = array_map(fn (ProveedorLlm $p) => $p->nombre, (new ClienteLlm())->proveedores());

        $this->assertSame(['ollama', 'groq'], $nombres);
    }

    public function test_reasoning_effort_solo_se_envia_a_modelos_de_razonamiento(): void
    {
        $this->assertSame('low', ProveedorLlm::desdeConfig('a', ['url' => 'https://a.test', 'token' => 't', 'modelo' => 'openai/gpt-oss-20b', 'razonamiento' => 'low'])->razonamiento);
        $this->assertNull(ProveedorLlm::desdeConfig('a', ['url' => 'https://a.test', 'token' => 't', 'modelo' => 'llama-3.3-70b', 'razonamiento' => 'low'])->razonamiento);
    }

    public function test_si_falla_un_proveedor_usa_el_siguiente(): void
    {
        config(['asistente.proveedores' => ['groq', 'openrouter']]);
        Http::fake([
            'groq.test/*' => Http::response(['error' => ['message' => 'caído']], 503),
            'router.test/*' => Http::response(['choices' => [['message' => ['content' => 'Respuesta de respaldo']]]]),
        ]);

        $respuesta = (new ClienteLlm())->completar([['role' => 'user', 'content' => 'hola']]);

        $this->assertSame('Respuesta de respaldo', $respuesta->texto);
        $this->assertSame('openrouter', $respuesta->proveedor);
    }

    public function test_tras_un_limite_de_uso_largo_pausa_el_proveedor(): void
    {
        config(['asistente.proveedores' => ['groq', 'openrouter']]);
        Http::fake([
            'groq.test/*' => Http::response(['error' => ['message' => 'Rate limit']], 429, ['Retry-After' => '30']),
            'router.test/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]]),
        ]);

        $cliente = new ClienteLlm();
        $cliente->completar([['role' => 'user', 'content' => 'uno']]);
        $cliente->completar([['role' => 'user', 'content' => 'dos']]);

        // Groq solo se llamó la primera vez; la segunda se fue directo al respaldo.
        $this->assertCount(1, collect(Http::recorded())->filter(fn ($r) => str_contains($r[0]->url(), 'groq.test')));
    }

    public function test_lee_el_stream_con_texto_y_llamadas_a_herramientas_fragmentadas(): void
    {
        config(['asistente.proveedores' => ['groq']]);
        $eventos = [
            ['choices' => [['delta' => ['content' => 'Voy ']]]],
            ['choices' => [['delta' => ['content' => 'a revisar.']]]],
            ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'c1', 'function' => ['name' => 'resumen_', 'arguments' => '{"des']]]]]]],
            ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['name' => 'ventas', 'arguments' => 'de":"2026-11-18"}']]]]]]],
            ['choices' => [['delta' => []]], 'x_groq' => ['usage' => ['total_tokens' => 321]]],
        ];
        $cuerpo = implode('', array_map(fn ($e) => 'data: ' . json_encode($e) . "\n\n", $eventos)) . "data: [DONE]\n\n";
        Http::fake(['*' => Http::response($cuerpo, 200, ['Content-Type' => 'text/event-stream'])]);

        $fragmentos = [];
        $respuesta = (new ClienteLlm())->completar([['role' => 'user', 'content' => 'ventas']], [], function (string $f) use (&$fragmentos) { $fragmentos[] = $f; });

        $this->assertSame(['Voy ', 'a revisar.'], $fragmentos);
        $this->assertSame('Voy a revisar.', $respuesta->texto);
        $this->assertSame('resumen_ventas', $respuesta->llamadas[0]['function']['name']);
        $this->assertSame('{"desde":"2026-11-18"}', $respuesta->llamadas[0]['function']['arguments']);
        $this->assertSame('c1', $respuesta->llamadas[0]['id']);
        $this->assertSame(321, $respuesta->tokens);
        $this->assertTrue(Http::recorded()[0][0]->data()['stream']);
    }

    public function test_sin_proveedores_configurados_lanza_error(): void
    {
        config(['asistente.proveedores' => []]);

        $this->expectException(ErrorProveedorLlm::class);
        (new ClienteLlm())->completar([['role' => 'user', 'content' => 'hola']]);
    }
}
