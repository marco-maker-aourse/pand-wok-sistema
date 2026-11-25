<?php

namespace Tests\Unit;

use App\Support\Restaurante\TicketComandaTexto;
use PHPUnit\Framework\TestCase;

/** Comanda en texto plano (Star CloudPRNT): legible de un vistazo y segura al anular. */
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
                ['cantidad' => '1', 'nombre' => 'Tallarín saltado', 'opciones' => [], 'observacion' => null, 'cuenta' => ['numero' => 102, 'tipo' => 'MESA', 'mesa' => 'M3']],
            ],
        ], $cambios);
    }

    private function lineas(string $texto): array
    {
        return explode("\n", rtrim($texto, "\n"));
    }

    public function test_la_mesa_y_la_nota_del_pedido_van_antes_de_los_platos(): void
    {
        $texto = TicketComandaTexto::render($this->comanda(), 58);

        $this->assertStringContainsString('MESA M2', $texto);
        $this->assertLessThan(strpos($texto, 'CHAUFA DE POLLO'), strpos($texto, 'NOTA: CLIENTE CON PRISA'));
        $this->assertStringContainsString('2 x CHAUFA DE POLLO [MENU]', $texto);
        $this->assertStringContainsString('+ Sopa wantan', $texto);
        $this->assertStringContainsString('>> SIN CEBOLLA CHINA', $texto);
        $this->assertStringContainsString('Para pedido 102 (mesa M3)', $texto);
        $this->assertStringContainsString('3 platos', $texto);
        $this->assertStringNotContainsString('S/', $texto);
    }

    public function test_respeta_el_ancho_del_papel_y_solo_usa_ascii(): void
    {
        foreach ([58 => 32, 80 => 48] as $mm => $columnas) {
            $texto = TicketComandaTexto::render($this->comanda(['items' => [
                ['cantidad' => '1', 'nombre' => str_repeat('Arroz chaufa especial de la casa ', 3), 'opciones' => [], 'observacion' => null, 'cuenta' => null],
            ]]), $mm);

            foreach ($this->lineas($texto) as $linea) {
                $this->assertLessThanOrEqual($columnas, strlen($linea), "Línea demasiado larga en {$mm} mm: {$linea}");
            }
            $this->assertMatchesRegularExpression('/^[\x20-\x7E\n]*$/', $texto);
        }
    }

    public function test_una_anulacion_dice_no_preparar_en_cada_plato(): void
    {
        $texto = TicketComandaTexto::render($this->comanda(['tipo' => 'ANULACION']), 80);

        $this->assertStringContainsString('ANULACION - NO PREPARAR', $texto);
        $this->assertStringContainsString('ANULADO 2 x CHAUFA DE POLLO [MENU]', $texto);
        $this->assertStringContainsString('ANULADO 1 x TALLARIN SALTADO', $texto);
        $this->assertStringContainsString('3 platos anulados', $texto);
    }

    public function test_una_reimpresion_se_identifica_como_copia_con_la_hora_original(): void
    {
        $texto = TicketComandaTexto::render($this->comanda(['tipo' => 'REIMPRESION']), 58);

        $this->assertStringContainsString('COPIA', $texto);
        $this->assertStringContainsString('Enviada 25/11/2026 14:32', $texto);
    }

    public function test_para_llevar_muestra_el_cliente(): void
    {
        $texto = TicketComandaTexto::render($this->comanda(['tipo_pedido' => 'PARA_LLEVAR', 'mesa' => null, 'area' => null, 'cliente' => 'Rosa', 'nota' => null]), 58);

        $this->assertStringContainsString('PARA LLEVAR - ROSA', $texto);
        $this->assertStringNotContainsString('NOTA:', $texto);
    }
}
