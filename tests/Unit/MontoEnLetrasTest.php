<?php

namespace Tests\Unit;

use App\Services\Comprobantes\PresentarComprobanteService;
use App\Support\MontoEnLetras;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MontoEnLetrasTest extends TestCase
{
    public static function montos(): array
    {
        return [
            [0, 'SON: CERO CON 00/100 SOLES'],
            [4.5, 'SON: CUATRO CON 50/100 SOLES'],
            [16, 'SON: DIECISÉIS CON 00/100 SOLES'],
            [21, 'SON: VEINTIUNO CON 00/100 SOLES'],
            [37.8, 'SON: TREINTA Y SIETE CON 80/100 SOLES'],
            [100, 'SON: CIEN CON 00/100 SOLES'],
            [101, 'SON: CIENTO UNO CON 00/100 SOLES'],
            [115.8, 'SON: CIENTO QUINCE CON 80/100 SOLES'],
            [1000, 'SON: MIL CON 00/100 SOLES'],
            [8898, 'SON: OCHO MIL OCHOCIENTOS NOVENTA Y OCHO CON 00/100 SOLES'],
            [21000, 'SON: VEINTIÚN MIL CON 00/100 SOLES'],
            [31500.05, 'SON: TREINTA Y UN MIL QUINIENTOS CON 05/100 SOLES'],
            [1000000, 'SON: UN MILLÓN CON 00/100 SOLES'],
            [2500000.99, 'SON: DOS MILLONES QUINIENTOS MIL CON 99/100 SOLES'],
            // 0.1 + 0.2 no debe dar 29/100 por coma flotante.
            [0.1 + 0.2, 'SON: CERO CON 30/100 SOLES'],
        ];
    }

    #[DataProvider('montos')]
    public function test_convierte_montos_a_letras(float $monto, string $esperado): void
    {
        $this->assertSame($esperado, MontoEnLetras::soles($monto));
    }

    public function test_abreviatura_del_documento_segun_catalogo_06(): void
    {
        $this->assertSame('DNI', PresentarComprobanteService::abreviaturaDocumento('1', 'Documento Nacional de Identidad'));
        $this->assertSame('RUC', PresentarComprobanteService::abreviaturaDocumento('6', 'RUC'));
        $this->assertSame('C.E.', PresentarComprobanteService::abreviaturaDocumento('4', 'Carnet de extranjería'));
        $this->assertSame('OTROS DOCUMENTOS', PresentarComprobanteService::abreviaturaDocumento('0', 'Otros documentos'));
    }
}
