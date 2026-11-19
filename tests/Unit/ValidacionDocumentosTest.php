<?php

namespace Tests\Unit;

use App\Models\Cliente;
use App\Models\Proveedor;
use App\Rules\Ruc;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Regla única de RUC y búsqueda por nombre o documento. */
class ValidacionDocumentosTest extends TestCase
{
    public function test_ruc_acepta_prefijos_validos_de_sunat(): void
    {
        foreach (['10467793549', '15123456789', '16123456789', '17123456789', '20100070970'] as $ruc) {
            $this->assertTrue($this->valida($ruc), $ruc);
        }
    }

    public function test_ruc_rechaza_letras_longitud_y_prefijos_invalidos(): void
    {
        foreach (['ABCDEFGHIJK', '2010007097', '201000709701', '11123456789', '30123456789', '20 10007097', ''] as $ruc) {
            $this->assertFalse($this->valida($ruc), "«{$ruc}» no debería ser válido");
        }

        $errores = Validator::make(['ruc' => 'X'], ['ruc' => [new Ruc]])->errors()->first('ruc');
        $this->assertSame(Ruc::MENSAJE, $errores);
    }

    public function test_busqueda_de_clientes_por_nombre_o_documento(): void
    {
        $consulta = Cliente::query()->buscar('  perez  ');

        $this->assertStringContainsString('"nombre_razon_social" ILIKE ?', $consulta->toSql());
        $this->assertStringContainsString('"num_documento" ILIKE ?', $consulta->toSql());
        $this->assertSame(['%perez%', '%perez%'], $consulta->getBindings());
    }

    public function test_busqueda_escapa_comodines_y_omite_terminos_vacios(): void
    {
        $this->assertSame(['%100\%%', '%100\%%'], Proveedor::query()->buscar('100%')->getBindings());
        $this->assertStringNotContainsString('ILIKE', Proveedor::query()->buscar('   ')->toSql());
    }

    private function valida(string $ruc): bool
    {
        return Validator::make(['ruc' => $ruc], ['ruc' => ['required', new Ruc]])->passes();
    }
}
