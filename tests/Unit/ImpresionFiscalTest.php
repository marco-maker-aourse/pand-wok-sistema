<?php

namespace Tests\Unit;

use App\Models\Impresora;
use App\Models\TerminalPos;
use App\Models\TurnoCaja;
use App\Models\Venta;
use App\Services\Comprobantes\PresentarComprobanteService;
use App\Services\Impresion\CrearTrabajoImpresionService;
use App\Services\Ventas\SunatEmisionService;
use ReflectionMethod;
use Tests\TestCase;
use ZipArchive;

class ImpresionFiscalTest extends TestCase
{
    public function test_no_encola_comprobante_pendiente_o_rechazado(): void
    {
        $service = new CrearTrabajoImpresionService(new PresentarComprobanteService());

        foreach (['PENDIENTE', 'RECHAZADO'] as $estado) {
            $venta = new Venta(['estado_sunat' => $estado]);

            $this->assertNull($service->comprobanteFiscalAceptado($venta));
            $this->assertNull($service->reimprimirComprobanteFiscal($venta));
        }
    }

    public function test_lector_cdr_conserva_observaciones_de_comprobante_aceptado(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'cdr-prueba-');
        $this->assertNotFalse($zipPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('R-20123456789-03-B001-00000001.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ApplicationResponse xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cac:DocumentResponse><cac:Response><cbc:ResponseCode>0</cbc:ResponseCode><cbc:Description>Aceptado</cbc:Description></cac:Response></cac:DocumentResponse>
  <cbc:Note>Observación de prueba</cbc:Note>
</ApplicationResponse>
XML);
        $zip->close();

        try {
            $method = new ReflectionMethod(SunatEmisionService::class, 'leerCdr');
            $method->setAccessible(true);
            $service = app(SunatEmisionService::class);
            $cdr = $method->invoke($service, (string) file_get_contents($zipPath));

            $this->assertSame('0', $cdr['codigo']);
            $this->assertSame(['Observación de prueba'], $cdr['observaciones']);
        } finally {
            @unlink($zipPath);
        }
    }

    /** Venta en memoria con su turno, terminal e impresora (sin base de datos). */
    private function ventaConTerminal(string $estadoSunat, ?bool $automatica, bool $impresoraActiva = true): Venta
    {
        $venta = new Venta(['estado_sunat' => $estadoSunat]);
        $turno = new TurnoCaja();
        if ($automatica !== null) {
            $terminal = new TerminalPos(['impresion_automatica' => $automatica, 'activo' => true]);
            $terminal->setRelation('impresora', new Impresora(['activo' => $impresoraActiva]));
            $turno->setRelation('terminal', $terminal);
        } else {
            $turno->setRelation('terminal', null);
        }
        $venta->setRelation('turno', $turno);

        return $venta;
    }

    public function test_impresion_automatica_aceptada_no_muestra_boton(): void
    {
        $estado = (new CrearTrabajoImpresionService(new PresentarComprobanteService()))->estadoImpresion($this->ventaConTerminal('ACEPTADO', true), true);

        $this->assertSame(['modo' => 'AUTOMATICA', 'puede_imprimir' => true, 'encolada' => true], $estado);
    }

    public function test_impresion_manual_aceptada_ofrece_boton(): void
    {
        $estado = (new CrearTrabajoImpresionService(new PresentarComprobanteService()))->estadoImpresion($this->ventaConTerminal('ACEPTADO', false), false);

        $this->assertSame(['modo' => 'MANUAL', 'puede_imprimir' => true, 'encolada' => false], $estado);
    }

    public function test_sin_aceptacion_sunat_no_se_puede_imprimir_en_ningun_modo(): void
    {
        $servicio = new CrearTrabajoImpresionService(new PresentarComprobanteService());

        foreach (['PENDIENTE', 'RECHAZADO', 'ERROR'] as $estadoSunat) {
            foreach ([true, false] as $automatica) {
                $estado = $servicio->estadoImpresion($this->ventaConTerminal($estadoSunat, $automatica), true);
                $this->assertFalse($estado['puede_imprimir'], "{$estadoSunat} no se imprime");
                $this->assertFalse($estado['encolada'], "{$estadoSunat} no se encola");
            }
        }
    }

    public function test_sin_terminal_o_impresora_activa_no_esta_disponible(): void
    {
        $servicio = new CrearTrabajoImpresionService(new PresentarComprobanteService());

        $this->assertSame('NO_DISPONIBLE', $servicio->estadoImpresion($this->ventaConTerminal('ACEPTADO', null), false)['modo']);
        $this->assertSame('NO_DISPONIBLE', $servicio->estadoImpresion($this->ventaConTerminal('ACEPTADO', true, false), false)['modo']);
    }

    public function test_ancho_de_papel_de_la_vista_previa_sigue_a_la_impresora_de_la_caja(): void
    {
        $service = new CrearTrabajoImpresionService(new PresentarComprobanteService());

        foreach ([58 => 58, 80 => 80] as $papel => $esperado) {
            $venta = $this->ventaConTerminal('ACEPTADO', false);
            $venta->turno->terminal->impresora->ancho_papel_mm = $papel;
            $this->assertSame($esperado, $service->anchoPapel($venta));
        }

        // Sin terminal (o sin impresora) la vista previa usa 80 mm.
        $this->assertSame(80, $service->anchoPapel($this->ventaConTerminal('PENDIENTE', null)));
        $this->assertSame(80, $service->anchoPapel(new Venta()));
    }
}
