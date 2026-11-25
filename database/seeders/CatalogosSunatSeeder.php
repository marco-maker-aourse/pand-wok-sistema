<?php

namespace Database\Seeders;

use App\Models\TipoComprobante;
use App\Models\TipoDocumento;
use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

/**
 * Catálogos oficiales de SUNAT que usa el restaurante. Es repetible: busca por el
 * código SUNAT y solo crea lo que falta; nunca cambia lo que el administrador editó.
 */
class CatalogosSunatSeeder extends Seeder
{
    /** Catálogo 06: documentos de identidad. */
    private const TIPOS_DOCUMENTO = [
        ['codigo_sunat' => TipoDocumento::DNI, 'nombre' => 'DNI', 'longitud_exacta' => 8, 'tipo_persona' => 'NATURAL'],
        ['codigo_sunat' => TipoDocumento::RUC, 'nombre' => 'RUC', 'longitud_exacta' => 11, 'tipo_persona' => null],
        ['codigo_sunat' => TipoDocumento::CARNET_EXTRANJERIA, 'nombre' => 'Carnet de extranjería', 'longitud_exacta' => null, 'tipo_persona' => 'NATURAL'],
        ['codigo_sunat' => TipoDocumento::PASAPORTE, 'nombre' => 'Pasaporte', 'longitud_exacta' => null, 'tipo_persona' => 'NATURAL'],
        ['codigo_sunat' => TipoDocumento::SIN_DOCUMENTO, 'nombre' => 'Sin documento', 'longitud_exacta' => null, 'tipo_persona' => null],
    ];

    /** Catálogo 01: comprobantes que se emiten en ventas o se reciben en compras. */
    private const TIPOS_COMPROBANTE = [
        ['codigo_sunat' => TipoComprobante::BOLETA, 'descripcion' => 'Boleta de venta', 'exige_ruc' => false, 'permite_anulacion' => true],
        ['codigo_sunat' => TipoComprobante::FACTURA, 'descripcion' => 'Factura', 'exige_ruc' => true, 'permite_anulacion' => true],
        ['codigo_sunat' => TipoComprobante::NOTA_CREDITO, 'descripcion' => 'Nota de crédito', 'exige_ruc' => false, 'permite_anulacion' => false],
        ['codigo_sunat' => TipoComprobante::NOTA_DEBITO, 'descripcion' => 'Nota de débito', 'exige_ruc' => false, 'permite_anulacion' => false],
        ['codigo_sunat' => TipoComprobante::GUIA_REMISION, 'descripcion' => 'Guía de remisión', 'exige_ruc' => false, 'permite_anulacion' => true],
        ['codigo_sunat' => TipoComprobante::TICKET_MAQUINA, 'descripcion' => 'Ticket de máquina registradora', 'exige_ruc' => false, 'permite_anulacion' => false],
        ['codigo_sunat' => TipoComprobante::OTROS, 'descripcion' => 'Otros (compra sin comprobante)', 'exige_ruc' => false, 'permite_anulacion' => false],
    ];

    /** Catálogo 03: unidades de medida habituales en cocina, bar y almacén. */
    private const UNIDADES_MEDIDA = [
        [UnidadMedida::UNIDAD, 'Unidad'],
        [UnidadMedida::SERVICIO, 'Servicio'],
        ['KGM', 'Kilogramo'],
        ['GRM', 'Gramo'],
        ['LTR', 'Litro'],
        ['MLT', 'Mililitro'],
        ['GLL', 'Galón'],
        ['DZN', 'Docena'],
        ['BX', 'Caja'],
        ['PK', 'Paquete'],
        ['BG', 'Bolsa'],
        ['BO', 'Botella'],
        ['CA', 'Lata'],
        ['SA', 'Saco'],
    ];

    public function run(): void
    {
        foreach (self::TIPOS_DOCUMENTO as $tipo) {
            TipoDocumento::query()->firstOrCreate(['codigo_sunat' => $tipo['codigo_sunat']], [...$tipo, 'estado' => true]);
        }

        foreach (self::TIPOS_COMPROBANTE as $tipo) {
            TipoComprobante::query()->firstOrCreate(['codigo_sunat' => $tipo['codigo_sunat']], [...$tipo, 'estado' => true]);
        }

        foreach (self::UNIDADES_MEDIDA as [$codigo, $nombre]) {
            UnidadMedida::query()->firstOrCreate(['codigo_sunat' => $codigo], ['nombre' => $nombre, 'estado' => true]);
        }
    }
}
