<?php

namespace Tests\Feature;

use App\Exceptions\DocumentLookupException;
use App\Services\External\DocumentLookupService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentLookupServiceTest extends TestCase
{
    public function test_consulta_dni_usa_token_en_query_y_normaliza_respuesta_de_api_peru(): void
    {
        config()->set('services.api_peru.dni_url', 'https://dniruc.apisperu.com/api/v1/dni');
        config()->set('services.api_peru.token', 'token-de-prueba');
        config()->set('services.api_peru.auth_mode', 'query');

        Http::fake([
            'https://dniruc.apisperu.com/*' => Http::response([
                'dni' => '12345678',
                'nombres' => 'Ana',
                'apellidoPaterno' => 'Pérez',
                'apellidoMaterno' => 'Flores',
            ]),
        ]);

        $result = app(DocumentLookupService::class)->lookup('dni', '12345678');

        $this->assertSame('Ana Pérez Flores', $result['nombre']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/dni/12345678?token=token-de-prueba'));
    }

    public function test_consulta_ruc_normaliza_razon_social_en_camel_case(): void
    {
        config()->set('services.api_peru.ruc_url', 'https://dniruc.apisperu.com/api/v1/ruc');
        config()->set('services.api_peru.token', 'token-de-prueba');
        config()->set('services.api_peru.auth_mode', 'bearer');

        Http::fake([
            'https://dniruc.apisperu.com/*' => Http::response([
                'ruc' => '20100047218',
                'razonSocial' => 'EMPRESA DE PRUEBA S.A.C.',
                'direccion' => 'Lima',
                'estado' => 'ACTIVO',
            ]),
        ]);

        $result = app(DocumentLookupService::class)->lookup('ruc', '20100047218');

        $this->assertSame('EMPRESA DE PRUEBA S.A.C.', $result['nombre']);
        $this->assertSame('Lima', $result['direccion']);
    }

    public function test_falla_de_conexion_del_proveedor_se_expone_como_error_controlado(): void
    {
        config()->set('services.api_peru.dni_url', 'https://provider.test/dni');
        config()->set('services.api_peru.token', 'token-de-prueba');
        Http::fake(['provider.test/*' => Http::failedConnection()]);

        try {
            app(DocumentLookupService::class)->lookup('dni', '12345678');
            $this->fail('Se esperaba un error controlado de conexión.');
        } catch (DocumentLookupException $exception) {
            $this->assertSame(503, $exception->status);
            $this->assertStringContainsString('conectar con el proveedor', $exception->getMessage());
        }
    }
}
