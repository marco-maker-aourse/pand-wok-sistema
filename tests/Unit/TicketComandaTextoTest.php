<?php

namespace Tests\Unit;

use App\Support\Restaurante\TicketComandaTexto;
use PHPUnit\Framework\TestCase;

/**
 * Comanda en texto plano (Star CloudPRNT). Mismo diseño que el agente: destino arriba,
 * nota antes de los platos, cantidad en su columna con sangría francesa y destino al pie.
 */
class TicketComandaTextoTest extends TestCase
{
    private function comanda(array $cambios = []): array
    {
        return array_replace([
            'tipo' => 'COMANDA', 'numero' => 41, 'estacion' => 'Caliente', 'pedido' => 101,
            'tipo_pedido' => 'MESA', 'mesa' => 'M2', 'area' => 'Salón principal', 'cliente' => null,
            'comensales' => 3, 'mozo' => 'Ana', 'nota' => 'Cliente con prisa', 'fecha' => '25/11/2026', 'hora' => '14:32',
            'items' => [
                ['cantidad' => '2', 'nombre' => 'Chaufa de pollo [Menú]', 'opciones' => ['Sopa wantán'], 'observacion' => 'Sin cebolla china', 'cuenta' => null],
                ['cantidad' => '1', 'nombre' => 'Tallarín saltado de carne con verduras', 'opciones' => [], 'observacion' => null, 'cuenta' => ['numero' => 102, 'tipo' => 'MESA', 'mesa' => 'M3']],
            ],
        ], $cambios);
    }

    private function lineas(string $texto): array
    {
        return explode("\n", rtrim($texto, "\n"));
    }

    public function test_destino_y_hora_arriba_nota_antes_de_los_platos_y_destino_al_pie(): void
    {
        $lineas = $this->lineas(TicketComandaTexto::render($this->comanda(), 58));
        $texto = implode("\n", $lineas);

        $this->assertMatchesRegularExpression('/^MESA M2 +14:32$/', $lineas[0]);
        $this->assertSame('#41 - Caliente - Ana - 3 pers.', $lineas[1]);
        $this->assertLessThan(strpos($texto, 'CHAUFA DE POLLO'), strpos($texto, '[NOTA] Cliente con prisa'));
        $this->assertMatchesRegularExpression('/^MESA M2 -+ 3 platos$/', end($lineas), 'cierre, destino y total en un renglón');
        $this->assertSame(32, strlen(end($lineas)));
        $this->assertStringNotContainsString('S/', $texto);
    }

    public function test_ahorro_de_papel_sin_renglones_vacios_ni_avance_final(): void
    {
        $texto = TicketComandaTexto::render($this->comanda(), 58);

        $this->assertStringNotContainsString("\n\n", $texto, 'ni renglones en blanco entre platos ni avance al final');
        $this->assertStringEndsWith("platos\n", $texto, 'la impresora avanza hasta su cuchilla al cortar');
        $this->assertSame(1, substr_count($texto, str_repeat('-', 32)), 'un solo separador, bajo el encabezado');
    }

    public function test_una_anulacion_solo_imprime_lo_necesario_para_no_preparar(): void
    {
        $texto = TicketComandaTexto::render($this->comanda(['tipo' => 'ANULACION']), 58);

        $this->assertStringNotContainsString('Cliente con prisa', $texto);
        $this->assertStringNotContainsString('SIN CEBOLLA', $texto);
        $this->assertStringContainsString('+ Sopa wantan', $texto);
    }

    public function test_cantidad_en_columna_con_sangria_francesa_y_etiqueta_aparte(): void
    {
        $lineas = $this->lineas(TicketComandaTexto::render($this->comanda(), 58));

        $plato = array_search('2   CHAUFA DE POLLO', $lineas, true);
        $this->assertNotFalse($plato, 'la cantidad va en su columna y la etiqueta [Menú] sale del nombre');
        $this->assertSame('    [MENU]', $lineas[$plato + 1]);
        $this->assertSame('    + Sopa wantan', $lineas[$plato + 2]);
        $this->assertSame('    ! SIN CEBOLLA CHINA', $lineas[$plato + 3]);

        $largo = array_values(array_filter($lineas, fn ($l) => str_starts_with($l, '1   TALLARIN')));
        $this->assertCount(1, $largo);
        $indice = array_search($largo[0], $lineas, true);
        $this->assertMatchesRegularExpression('/^ {4}\S/', $lineas[$indice + 1], 'el nombre continúa bajo el nombre');
        $this->assertContains('    Para pedido 102 (mesa M3)', $lineas);
    }

    public function test_respeta_el_ancho_del_papel_y_solo_usa_ascii(): void
    {
        foreach ([58 => 32, 80 => 48] as $mm => $columnas) {
            $texto = TicketComandaTexto::render($this->comanda(['nota' => str_repeat('Alérgico al maní ', 5), 'items' => [
                ['cantidad' => '12', 'nombre' => str_repeat('Arroz chaufa especial de la casa ', 3), 'opciones' => [str_repeat('Papa frita grande ', 4)], 'observacion' => str_repeat('Poco aceite ', 6), 'cuenta' => null],
            ]]), $mm);

            foreach ($this->lineas($texto) as $linea) {
                $this->assertLessThanOrEqual($columnas, strlen($linea), "Línea demasiado larga en {$mm} mm: {$linea}");
            }
            $this->assertMatchesRegularExpression('/^[\x20-\x7E\n]*$/', $texto);
        }
    }

    public function test_el_reparto_de_opciones_conserva_el_signo_de_multiplicar(): void
    {
        $texto = TicketComandaTexto::render($this->comanda(['items' => [
            ['cantidad' => '2', 'nombre' => 'Menú chaufa', 'opciones' => ['1 × Sopa wantán', '1 × Sopa de pollo'], 'observacion' => null, 'cuenta' => null],
        ]]), 58);

        $this->assertStringContainsString('+ 1 x Sopa wantan', $texto);
        $this->assertStringContainsString('+ 1 x Sopa de pollo', $texto);
    }

    public function test_una_anulacion_avisa_arriba_y_marca_cada_plato(): void
    {
        $texto = TicketComandaTexto::render($this->comanda(['tipo' => 'ANULACION']), 80);

        $this->assertStringContainsString('NO PREPARAR', $texto);
        $this->assertSame(2, substr_count($texto, '[ANULADO]'));
        $this->assertStringContainsString('3 platos anulados', $texto);
    }

    public function test_una_reimpresion_se_identifica_como_copia_con_la_hora_original(): void
    {
        $texto = TicketComandaTexto::render($this->comanda(['tipo' => 'REIMPRESION']), 58);

        $this->assertStringContainsString('[COPIA] Original 25/11 14:32', $texto);
    }

    public function test_para_llevar_muestra_el_cliente(): void
    {
        $texto = TicketComandaTexto::render($this->comanda(['tipo_pedido' => 'PARA_LLEVAR', 'mesa' => null, 'area' => null, 'cliente' => 'Rosa', 'nota' => null]), 58);

        $this->assertStringContainsString('PARA LLEVAR - ROSA', $texto);
        $this->assertStringNotContainsString('[NOTA]', $texto);
    }
}
