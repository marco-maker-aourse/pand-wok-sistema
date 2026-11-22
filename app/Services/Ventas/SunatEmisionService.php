<?php

namespace App\Services\Ventas;

use App\Models\Venta;
use App\Services\Comprobantes\DescripcionItems;
use App\Services\Impresion\CrearTrabajoImpresionService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SoapClient;
use Throwable;
use ZipArchive;

class SunatEmisionService
{
    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    private const INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const CURRENCY = 'PEN';

    public function __construct(private readonly ?CrearTrabajoImpresionService $impresion = null) {}

    public function emitirComprobante(int $idVenta): array
    {
        $lock = Cache::lock("sunat-emision-venta-{$idVenta}", 180);

        if (! $lock->get()) {
            return $this->result(false, 'PENDIENTE', 'La emisión de este comprobante ya está en proceso.');
        }

        try {
            return $this->emitirBajoBloqueo($idVenta);
        } catch (Throwable $exception) {
            Log::error('Falló la emisión del comprobante SUNAT.', [
                'id_venta' => $idVenta,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            DB::table('operaciones.ventas')
                ->where('id_venta', $idVenta)
                ->where('estado_sunat', '!=', 'ACEPTADO')
                ->update(['estado_sunat' => 'PENDIENTE']);

            $message = $exception instanceof \SoapFault
                && (str_ends_with((string) $exception->faultcode, '.2335')
                    || str_contains($exception->getMessage(), 'Incorrect reference digest value'))
                    ? 'SUNAT rechazó la firma digital del XML (error 2335). La venta está registrada y puede reintentarse después de corregir la firma.'
                    : 'No se pudo completar la emisión. Revisa el registro del servidor para conocer el motivo.';

            return $this->result(false, 'PENDIENTE', $message);
        } finally {
            $lock->release();
        }
    }

    private function emitirBajoBloqueo(int $idVenta): array
    {
        $venta = $this->obtenerVenta($idVenta);

        if (! $venta) {
            return $this->result(false, 'ERROR', 'La venta indicada no existe.');
        }

        if ($venta->estado_sunat === 'ACEPTADO') {
            $impresion = $this->encolarRepresentacionFiscal($idVenta);

            return $this->conImpresion($this->result(true, 'ACEPTADO', 'El comprobante ya fue aceptado por SUNAT.', $venta->hash_cpe), $impresion);
        }

        if ($venta->estado_sunat === 'RECHAZADO') {
            return $this->result(false, 'RECHAZADO', 'El comprobante fue rechazado. No se reenviará con el mismo número.');
        }

        // Datos fiscales de la empresa; el código de establecimiento es el de la sucursal donde se vendió.
        $empresa = app(\App\Services\Configuracion\DatosGeneralesService::class)->empresa();
        $emisor = $empresa ? (object) [
            ...$empresa->getAttributes(),
            'codigo_establecimiento' => DB::table('configuracion.sucursales')->where('id_sucursal', $venta->id_sucursal)->value('codigo_establecimiento'),
        ] : null;

        if (! $emisor) {
            throw new \RuntimeException('La empresa no tiene datos fiscales configurados.');
        }

        if (! preg_match('/^\d{4}$/', (string) $emisor->codigo_establecimiento)) {
            return $this->result(
                false,
                'PENDIENTE',
                'Configura el código de establecimiento SUNAT de 4 dígitos en Datos Generales antes de reintentar.',
            );
        }

        $detalles = $this->obtenerDetalles($idVenta);
        $this->validarDatos($venta, $emisor, $detalles);

        $filename = implode('-', [
            $emisor->ruc_empresa,
            $venta->cod_comprobante,
            $venta->serie,
            str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT),
        ]);

        $xml = $this->generarXmlUbl21($venta, $emisor, $detalles);
        $hash = '';
        $xmlFirmado = $this->firmarXml($xml, $hash);
        $zip = $this->crearZip($filename, $xmlFirmado);

        $xmlPath = "sunat/xml/{$filename}.xml";
        $zipPath = "sunat/xml/{$filename}.zip";
        $disk = Storage::disk('local');
        $disk->put($xmlPath, $xmlFirmado);
        $disk->put($zipPath, $zip);

        DB::table('operaciones.ventas')
            ->where('id_venta', $idVenta)
            ->update([
                'estado_sunat' => 'PENDIENTE',
                'hash_cpe' => $hash,
                'enlace_xml' => $xmlPath,
                'enlace_cdr' => null,
            ]);

        $response = $this->enviarSoap($zip, "{$filename}.zip", $emisor);
        $cdr = $this->leerCdr($response);
        $cdrPath = "sunat/cdr/R-{$filename}.zip";
        $disk->put($cdrPath, $response);

        $accepted = $cdr['codigo'] === '0';
        DB::table('operaciones.ventas')
            ->where('id_venta', $idVenta)
            ->update([
                'estado_sunat' => $accepted ? 'ACEPTADO' : 'RECHAZADO',
                'hash_cpe' => $hash,
                'enlace_xml' => $xmlPath,
                'enlace_cdr' => $cdrPath,
            ]);

        // La aceptación ya está persistida antes de crear la consecuencia asíncrona.
        // Un fallo de agente/impresora nunca revierte el CDR ni la venta.
        if ($accepted) {
            if ($cdr['observaciones'] !== []) {
                Log::info('SUNAT aceptó el comprobante con observaciones.', [
                    'id_venta' => $idVenta,
                    'observaciones' => $cdr['observaciones'],
                ]);
            }

            $impresion = $this->encolarRepresentacionFiscal($idVenta);
        }

        return $this->conImpresion($this->result(
            $accepted,
            $accepted ? 'ACEPTADO' : 'RECHAZADO',
            $cdr['descripcion'],
            $hash,
        ), $impresion ?? null);
    }

    private function obtenerVenta(int $idVenta): ?object
    {
        return DB::table('operaciones.ventas as v')
            ->join('catalogos.clientes as c', 'v.id_cliente', '=', 'c.id_cliente')
            ->join('catalogos.tipos_documento as td', 'c.id_tipo_documento', '=', 'td.id_tipo_documento')
            ->join('catalogos.tipos_comprobante as tc', 'v.id_tipo_comprobante', '=', 'tc.id_tipo_comprobante')
            ->where('v.id_venta', $idVenta)
            ->select([
                'v.*',
                'c.nombre_razon_social',
                'c.num_documento',
                'c.direccion_fiscal as direccion_cliente',
                'td.codigo_sunat as cod_doc_cliente',
                'tc.codigo_sunat as cod_comprobante',
            ])
            ->first();
    }

    private function obtenerDetalles(int $idVenta): \Illuminate\Support\Collection
    {
        $detalles = DB::table('operaciones.detalle_ventas as dv')
            ->join('catalogos.producto_presentaciones as pp', 'dv.id_precio', '=', 'pp.id_precio')
            ->join('catalogos.productos as p', 'pp.id_producto', '=', 'p.id_producto')
            ->join('catalogos.unidades_medida as um', 'p.id_unidad_base', '=', 'um.id_unidad')
            ->where('dv.id_venta', $idVenta)
            ->orderBy('dv.id_detalle')
            ->select([
                'dv.id_detalle',
                'dv.cantidad',
                'dv.precio_unitario',
                'dv.subtotal',
                'pp.factor_conversion',
                'p.codigo_sunat',
                'p.nombre as nombre_producto',
                'p.afecta_igv',
                'um.codigo_sunat as unidad_sunat',
                'dv.id_detalle_pedido',
            ])
            ->get();

        // Restaurante: la descripción incluye las opciones del plato que explican su precio.
        $opciones = DescripcionItems::opciones($detalles->pluck('id_detalle_pedido')->all());

        return $detalles->map(function (object $detalle) use ($opciones): object {
            $detalle->nombre_producto = DescripcionItems::describir(
                (string) $detalle->nombre_producto,
                $opciones[(int) $detalle->id_detalle_pedido] ?? null,
            );

            return $detalle;
        });
    }

    private function validarDatos(object $venta, object $emisor, \Illuminate\Support\Collection $detalles): void
    {
        if (! in_array((string) $venta->cod_comprobante, ['01', '03'], true)) {
            throw new \RuntimeException('Solo se pueden emitir facturas y boletas electrónicas.');
        }

        if (! preg_match('/^\d{11}$/', (string) $emisor->ruc_empresa)) {
            throw new \RuntimeException('El RUC del emisor debe tener 11 dígitos.');
        }

        if ((string) $venta->cod_comprobante === '01'
            && ((string) $venta->cod_doc_cliente !== '6' || ! preg_match('/^\d{11}$/', (string) $venta->num_documento))) {
            throw new \RuntimeException('La factura requiere un cliente identificado con RUC de 11 dígitos.');
        }

        if (blank($emisor->razon_social) || blank($emisor->direccion_fiscal)) {
            throw new \RuntimeException('Falta la razón social o dirección fiscal del emisor.');
        }

        if ($detalles->isEmpty()) {
            throw new \RuntimeException('La venta no tiene líneas para emitir.');
        }

        if ((float) $emisor->igv_porcentaje < 0) {
            throw new \RuntimeException('La tasa de IGV configurada no es válida.');
        }

        foreach ($detalles as $detalle) {
            if ((float) $detalle->cantidad <= 0
                || (float) $detalle->factor_conversion <= 0
                || blank($detalle->unidad_sunat)
                || blank($detalle->nombre_producto)) {
                throw new \RuntimeException('Una línea de venta tiene cantidad, unidad o producto inválido.');
            }
        }
    }

    private function generarXmlUbl21(object $venta, object $emisor, \Illuminate\Support\Collection $detalles): string
    {
        $lineas = $this->calcularLineas($venta, $emisor, $detalles);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $invoice = $dom->createElementNS(self::INVOICE, 'Invoice');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC);
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC);
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::EXT);
        $dom->appendChild($invoice);

        $extensions = $this->append($dom, $invoice, 'ext:UBLExtensions');
        $extension = $this->append($dom, $extensions, 'ext:UBLExtension');
        $this->append($dom, $extension, 'ext:ExtensionContent');
        $this->append($dom, $invoice, 'cbc:UBLVersionID', '2.1');
        $this->append($dom, $invoice, 'cbc:CustomizationID', '2.0');
        $this->append($dom, $invoice, 'cbc:ID', $this->numeroDocumento($venta));
        $issuedAt = \Illuminate\Support\Carbon::parse($venta->fecha_emision)->timezone('America/Lima');
        $this->append($dom, $invoice, 'cbc:IssueDate', $issuedAt->format('Y-m-d'));
        $this->append($dom, $invoice, 'cbc:IssueTime', $issuedAt->format('H:i:s'));
        $this->append($dom, $invoice, 'cbc:InvoiceTypeCode', (string) $venta->cod_comprobante, [
            'listID' => '0101',
            'listAgencyName' => 'PE:SUNAT',
            'listName' => 'Tipo de Documento',
        ]);
        $this->append($dom, $invoice, 'cbc:DocumentCurrencyCode', self::CURRENCY);
        $this->appendFirmaUbl($dom, $invoice, $emisor);
        $this->appendEmisor($dom, $invoice, $emisor);
        $this->appendReceptor($dom, $invoice, $venta);
        $this->appendFinancialSections($dom, $invoice, $venta, $emisor, $lineas);

        return $dom->saveXML() ?: throw new \RuntimeException('No fue posible serializar el comprobante UBL.');
    }

    private function appendFinancialSections(
        DOMDocument $dom,
        DOMElement $invoice,
        object $venta,
        object $emisor,
        array $lineas,
    ): void {
        $this->appendFormaPago($dom, $invoice, $venta);
        $this->appendTotalesImpuestos($dom, $invoice, $venta, $emisor, $lineas);
        $this->appendTotalesMonetarios($dom, $invoice, $venta);

        foreach ($lineas as $index => $linea) {
            $this->appendLinea($dom, $invoice, $index + 1, $linea, $emisor);
        }
    }

    private function calcularLineas(object $venta, object $emisor, \Illuminate\Support\Collection $detalles): array
    {
        $gravables = $detalles->filter(fn ($detail) => (bool) $detail->afecta_igv)->values();
        $baseGravable = (float) $gravables->sum('subtotal');
        $descuentoPendiente = round((float) $venta->descuento_global, 2);
        $tasaIgv = (float) $emisor->igv_porcentaje;
        $lineas = [];
        $baseTotal = 0.0;
        $igvTotal = 0.0;
        $grossAfterDiscount = 0.0;

        foreach ($detalles->values() as $index => $detalle) {
            $subtotal = round((float) $detalle->subtotal, 2);
            $esGravable = (bool) $detalle->afecta_igv;
            $descuento = 0.0;

            if ($esGravable && $descuentoPendiente > 0) {
                $descuento = $gravables->last()->id_detalle === $detalle->id_detalle
                    ? $descuentoPendiente
                    : round((float) $venta->descuento_global * $subtotal / max($baseGravable, 0.01), 2);
                $descuento = min($descuento, $subtotal, $descuentoPendiente);
                $descuentoPendiente = round($descuentoPendiente - $descuento, 2);
            }

            $totalLinea = round($subtotal - $descuento, 2);
            $baseLinea = $esGravable && $tasaIgv > 0
                ? round($totalLinea / (1 + ($tasaIgv / 100)), 2)
                : $totalLinea;
            $baseOriginal = $esGravable && $tasaIgv > 0
                ? round($subtotal / (1 + ($tasaIgv / 100)), 2)
                : $subtotal;
            $igvLinea = $esGravable ? round($totalLinea - $baseLinea, 2) : 0.0;
            $cantidad = round((float) $detalle->cantidad * (float) $detalle->factor_conversion, 4);

            $lineas[] = [
                'detalle' => $detalle,
                'cantidad' => $cantidad,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'base' => $baseLinea,
                'igv' => $igvLinea,
                'gravable' => $esGravable,
                'precio_unitario' => $cantidad > 0 ? round($baseOriginal / $cantidad, 10) : 0,
                'precio_con_igv' => $cantidad > 0 ? round($subtotal / $cantidad, 10) : 0,
            ];
            $baseTotal += $baseLinea;
            $igvTotal += $igvLinea;
            $grossAfterDiscount += $totalLinea;
        }

        $expectedBase = round((float) $venta->op_gravadas + (float) $venta->op_exoneradas, 2);
        $baseAdjustment = round($expectedBase - $baseTotal, 2);
        $igvAdjustment = round((float) $venta->igv - $igvTotal, 2);
        $totalAdjustment = round($baseAdjustment + $igvAdjustment, 2);
        $lastTaxableLine = null;
        foreach ($lineas as $index => $linea) {
            if ($linea['gravable']) {
                $lastTaxableLine = $index;
            }
        }

        if ($descuentoPendiente !== 0.0
            || abs(round($grossAfterDiscount, 2) - (float) $venta->total_venta) > 0.01
            || abs($baseAdjustment) > 0.05
            || abs($igvAdjustment) > 0.05
            || abs($totalAdjustment) > 0.01
            || (($baseAdjustment !== 0.0 || $igvAdjustment !== 0.0) && $lastTaxableLine === null)) {
            throw new \RuntimeException('Los importes del detalle no coinciden con los totales guardados de la venta.');
        }

        if ($lastTaxableLine !== null) {
            $lineas[$lastTaxableLine]['base'] = round($lineas[$lastTaxableLine]['base'] + $baseAdjustment, 2);
            $lineas[$lastTaxableLine]['igv'] = round($lineas[$lastTaxableLine]['igv'] + $igvAdjustment, 2);
        }

        return $lineas;
    }

    private function appendFirmaUbl(DOMDocument $dom, DOMElement $invoice, object $emisor): void
    {
        $signature = $this->append($dom, $invoice, 'cac:Signature');
        $this->append($dom, $signature, 'cbc:ID', "{$emisor->ruc_empresa}-SIGN");
        $party = $this->append($dom, $signature, 'cac:SignatoryParty');
        $identification = $this->append($dom, $party, 'cac:PartyIdentification');
        $this->append($dom, $identification, 'cbc:ID', (string) $emisor->ruc_empresa);
        $legal = $this->append($dom, $party, 'cac:PartyName');
        $this->append($dom, $legal, 'cbc:Name', (string) $emisor->razon_social);
        $attachment = $this->append($dom, $signature, 'cac:DigitalSignatureAttachment');
        $reference = $this->append($dom, $attachment, 'cac:ExternalReference');
        $this->append($dom, $reference, 'cbc:URI', '#SignatureSP');
    }

    private function appendEmisor(DOMDocument $dom, DOMElement $invoice, object $emisor): void
    {
        $supplier = $this->append($dom, $invoice, 'cac:AccountingSupplierParty');
        $party = $this->append($dom, $supplier, 'cac:Party');
        $identification = $this->append($dom, $party, 'cac:PartyIdentification');
        $this->append($dom, $identification, 'cbc:ID', (string) $emisor->ruc_empresa, ['schemeID' => '6']);
        $legal = $this->append($dom, $party, 'cac:PartyLegalEntity');
        $this->append($dom, $legal, 'cbc:RegistrationName', (string) $emisor->razon_social);
        $address = $this->append($dom, $legal, 'cac:RegistrationAddress');
        $this->append($dom, $address, 'cbc:AddressTypeCode', (string) $emisor->codigo_establecimiento, [
            'listAgencyName' => 'PE:SUNAT',
            'listName' => 'Establecimientos anexos',
        ]);
        $line = $this->append($dom, $address, 'cac:AddressLine');
        $this->append($dom, $line, 'cbc:Line', (string) $emisor->direccion_fiscal);
        $country = $this->append($dom, $address, 'cac:Country');
        $this->append($dom, $country, 'cbc:IdentificationCode', 'PE');
    }

    private function appendReceptor(DOMDocument $dom, DOMElement $invoice, object $venta): void
    {
        $customer = $this->append($dom, $invoice, 'cac:AccountingCustomerParty');
        $party = $this->append($dom, $customer, 'cac:Party');
        $identification = $this->append($dom, $party, 'cac:PartyIdentification');
        $this->append($dom, $identification, 'cbc:ID', (string) $venta->num_documento, [
            'schemeID' => (string) $venta->cod_doc_cliente,
        ]);
        $legal = $this->append($dom, $party, 'cac:PartyLegalEntity');
        $this->append($dom, $legal, 'cbc:RegistrationName', (string) $venta->nombre_razon_social);

        if (! blank($venta->direccion_cliente)) {
            $address = $this->append($dom, $legal, 'cac:RegistrationAddress');
            $line = $this->append($dom, $address, 'cac:AddressLine');
            $this->append($dom, $line, 'cbc:Line', (string) $venta->direccion_cliente);
            $country = $this->append($dom, $address, 'cac:Country');
            $this->append($dom, $country, 'cbc:IdentificationCode', 'PE');
        }
    }

    private function appendTotalesImpuestos(DOMDocument $dom, DOMElement $invoice, object $venta, object $emisor, array $lineas): void
    {
        $taxTotal = $this->append($dom, $invoice, 'cac:TaxTotal');
        $this->append($dom, $taxTotal, 'cbc:TaxAmount', $this->money((float) $venta->igv), ['currencyID' => self::CURRENCY]);
        $groups = [
            ['gravable' => true, 'base' => (float) $venta->op_gravadas, 'tax' => (float) $venta->igv],
            ['gravable' => false, 'base' => (float) $venta->op_exoneradas, 'tax' => 0.0],
        ];

        foreach ($groups as $group) {
            if ($group['base'] <= 0) {
                continue;
            }
            $subtotal = $this->append($dom, $taxTotal, 'cac:TaxSubtotal');
            $this->append($dom, $subtotal, 'cbc:TaxableAmount', $this->money($group['base']), ['currencyID' => self::CURRENCY]);
            $this->append($dom, $subtotal, 'cbc:TaxAmount', $this->money($group['tax']), ['currencyID' => self::CURRENCY]);
            $category = $this->append($dom, $subtotal, 'cac:TaxCategory');
            $this->append($dom, $category, 'cbc:ID', $group['gravable'] ? 'S' : 'E');
            $this->append($dom, $category, 'cbc:Percent', $group['gravable'] ? number_format((float) $emisor->igv_porcentaje, 2, '.', '') : '0.00');
            $this->append($dom, $category, 'cbc:TaxExemptionReasonCode', $group['gravable'] ? '10' : '20');
            $scheme = $this->append($dom, $category, 'cac:TaxScheme');
            $this->append($dom, $scheme, 'cbc:ID', $group['gravable'] ? '1000' : '9997');
            $this->append($dom, $scheme, 'cbc:Name', $group['gravable'] ? 'IGV' : 'EXO');
            $this->append($dom, $scheme, 'cbc:TaxTypeCode', 'VAT');
        }
    }

    private function appendTotalesMonetarios(DOMDocument $dom, DOMElement $invoice, object $venta): void
    {
        $monetary = $this->append($dom, $invoice, 'cac:LegalMonetaryTotal');
        $base = (float) $venta->op_gravadas + (float) $venta->op_exoneradas;
        $this->append($dom, $monetary, 'cbc:LineExtensionAmount', $this->money($base), ['currencyID' => self::CURRENCY]);
        $this->append($dom, $monetary, 'cbc:TaxExclusiveAmount', $this->money($base), ['currencyID' => self::CURRENCY]);
        $this->append($dom, $monetary, 'cbc:TaxInclusiveAmount', $this->money((float) $venta->total_venta), ['currencyID' => self::CURRENCY]);
        if ((float) $venta->descuento_global > 0) {
            $this->append($dom, $monetary, 'cbc:AllowanceTotalAmount', $this->money((float) $venta->descuento_global), ['currencyID' => self::CURRENCY]);
        }
        $this->append($dom, $monetary, 'cbc:PayableAmount', $this->money((float) $venta->total_venta), ['currencyID' => self::CURRENCY]);
    }

    private function appendFormaPago(DOMDocument $dom, DOMElement $invoice, object $venta): void
    {
        $paymentTerms = $this->append($dom, $invoice, 'cac:PaymentTerms');
        $this->append($dom, $paymentTerms, 'cbc:ID', 'FormaPago');
        $this->append($dom, $paymentTerms, 'cbc:PaymentMeansID', 'Contado');
        $this->append($dom, $paymentTerms, 'cbc:Amount', $this->money((float) $venta->total_venta), [
            'currencyID' => self::CURRENCY,
        ]);
    }

    private function appendLinea(DOMDocument $dom, DOMElement $invoice, int $number, array $line, object $emisor): void
    {
        $detalle = $line['detalle'];
        $invoiceLine = $this->append($dom, $invoice, 'cac:InvoiceLine');
        $this->append($dom, $invoiceLine, 'cbc:ID', (string) $number);
        $this->append($dom, $invoiceLine, 'cbc:InvoicedQuantity', number_format($line['cantidad'], 4, '.', ''), [
            'unitCode' => (string) $detalle->unidad_sunat,
        ]);
        $this->append($dom, $invoiceLine, 'cbc:LineExtensionAmount', $this->money($line['base']), ['currencyID' => self::CURRENCY]);
        $pricingReference = $this->append($dom, $invoiceLine, 'cac:PricingReference');
        $alternativePrice = $this->append($dom, $pricingReference, 'cac:AlternativeConditionPrice');
        $this->append($dom, $alternativePrice, 'cbc:PriceAmount', $this->money($line['precio_con_igv']), [
            'currencyID' => self::CURRENCY,
        ]);
        $this->append($dom, $alternativePrice, 'cbc:PriceTypeCode', '01');

        if ($line['descuento'] > 0) {
            $allowance = $this->append($dom, $invoiceLine, 'cac:AllowanceCharge');
            $this->append($dom, $allowance, 'cbc:ChargeIndicator', 'false');
            $this->append($dom, $allowance, 'cbc:AllowanceChargeReasonCode', '02');
            $this->append($dom, $allowance, 'cbc:Amount', $this->money($line['descuento'] / (1 + ((float) $emisor->igv_porcentaje / 100))), [
                'currencyID' => self::CURRENCY,
            ]);
        }

        $taxTotal = $this->append($dom, $invoiceLine, 'cac:TaxTotal');
        $this->append($dom, $taxTotal, 'cbc:TaxAmount', $this->money($line['igv']), ['currencyID' => self::CURRENCY]);
        $taxSubtotal = $this->append($dom, $taxTotal, 'cac:TaxSubtotal');
        $this->append($dom, $taxSubtotal, 'cbc:TaxableAmount', $this->money($line['base']), ['currencyID' => self::CURRENCY]);
        $this->append($dom, $taxSubtotal, 'cbc:TaxAmount', $this->money($line['igv']), ['currencyID' => self::CURRENCY]);
        $category = $this->append($dom, $taxSubtotal, 'cac:TaxCategory');
        $this->append($dom, $category, 'cbc:ID', $line['gravable'] ? 'S' : 'E');
        $this->append($dom, $category, 'cbc:Percent', $line['gravable'] ? number_format((float) $emisor->igv_porcentaje, 2, '.', '') : '0.00');
        $this->append($dom, $category, 'cbc:TaxExemptionReasonCode', $line['gravable'] ? '10' : '20');
        $scheme = $this->append($dom, $category, 'cac:TaxScheme');
        $this->append($dom, $scheme, 'cbc:ID', $line['gravable'] ? '1000' : '9997');
        $this->append($dom, $scheme, 'cbc:Name', $line['gravable'] ? 'IGV' : 'EXO');
        $this->append($dom, $scheme, 'cbc:TaxTypeCode', 'VAT');

        $item = $this->append($dom, $invoiceLine, 'cac:Item');
        $this->append($dom, $item, 'cbc:Description', (string) $detalle->nombre_producto);
        if (! blank($detalle->codigo_sunat)) {
            $sellerItem = $this->append($dom, $item, 'cac:SellersItemIdentification');
            $this->append($dom, $sellerItem, 'cbc:ID', (string) $detalle->codigo_sunat);
        }

        $price = $this->append($dom, $invoiceLine, 'cac:Price');
        $this->append($dom, $price, 'cbc:PriceAmount', number_format($line['precio_unitario'], 10, '.', ''), ['currencyID' => self::CURRENCY]);
    }

    private function firmarXml(string $xml, string &$hash): string
    {
        if (! class_exists(XMLSecurityDSig::class) || ! class_exists(XMLSecurityKey::class)) {
            throw new \RuntimeException('La dependencia de firma XML no está instalada.');
        }

        $certificatePath = (string) config('services.sunat.certificado', 'keys/certificado.p12');
        $certificatePath = str_starts_with($certificatePath, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:\\\\/', $certificatePath)
                ? $certificatePath
                : storage_path('app/'.$certificatePath);

        if (! is_file($certificatePath) || ! is_readable($certificatePath)) {
            throw new \RuntimeException('No se encontró el certificado digital PKCS#12 configurado.');
        }

        $certificateContents = file_get_contents($certificatePath);
        $certificates = [];
        if ($certificateContents === false
            || ! openssl_pkcs12_read($certificateContents, $certificates, (string) config('services.sunat.certificado_clave', ''))
            || empty($certificates['pkey'])
            || empty($certificates['cert'])) {
            throw new \RuntimeException('No se pudo abrir el certificado digital o la contraseña es incorrecta.');
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (! $dom->loadXML($xml, LIBXML_NONET)) {
            throw new \RuntimeException('El XML generado no es válido.');
        }

        $extensionContent = $dom->getElementsByTagNameNS(self::EXT, 'ExtensionContent')->item(0);
        if (! $extensionContent instanceof DOMElement) {
            throw new \RuntimeException('El XML no contiene el espacio reservado para la firma.');
        }

        $signature = new XMLSecurityDSig();
        $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $signature->addReference(
            $dom,
            XMLSecurityDSig::SHA256,
            [XMLSecurityDSig::ENVELOPED],
            ['force_uri' => true],
        );
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($certificates['pkey']);
        $signature->sign($key, $extensionContent);
        $signature->add509Cert($certificates['cert']);
        $signatureNode = $dom->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'Signature')->item(0);
        if (! $signatureNode instanceof DOMElement) {
            throw new \RuntimeException('No se pudo adjuntar la firma digital al comprobante.');
        }
        $signatureNode->setAttribute('Id', 'SignatureSP');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
        $digest = $xpath->query('//ds:Signature/ds:SignedInfo/ds:Reference/ds:DigestValue')?->item(0);
        if (! $digest instanceof DOMElement || $digest->textContent === '') {
            throw new \RuntimeException('No se pudo obtener el hash de la firma XML.');
        }
        $hash = $digest->textContent;

        return $dom->saveXML() ?: throw new \RuntimeException('No fue posible serializar el XML firmado.');
    }

    private function enviarSoap(string $zipContent, string $zipName, object $emisor): string
    {
        if (! extension_loaded('soap') || ! class_exists(SoapClient::class)) {
            throw new \RuntimeException('La extensión PHP SOAP no está habilitada.');
        }

        $solUsername = trim((string) config('services.sunat.usuario_sol', ''));
        $solPassword = (string) config('services.sunat.clave_sol', '');
        if ($solUsername === '' || $solPassword === '') {
            throw new \RuntimeException('Configura SUNAT_USUARIO_SOL y SUNAT_CLAVE_SOL en el entorno.');
        }

        $username = str_starts_with($solUsername, (string) $emisor->ruc_empresa)
            ? $solUsername
            : $emisor->ruc_empresa.$solUsername;
        $endpoint = (string) config(
            'services.sunat.endpoint_cpe',
            'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService?wsdl',
        );

        $client = new SoapClient($endpoint, [
            'login' => $username,
            'password' => $solPassword,
            'exceptions' => true,
            'trace' => false,
            'connection_timeout' => 30,
            'cache_wsdl' => WSDL_CACHE_BOTH,
        ]);
        $response = $client->__soapCall('sendBill', [[
            'fileName' => $zipName,
            'contentFile' => $zipContent,
        ]]);
        $cdr = $response->applicationResponse ?? null;

        if (! is_string($cdr) || $cdr === '') {
            throw new \RuntimeException('SUNAT no devolvió una Constancia de Recepción (CDR).');
        }

        return $cdr;
    }

    private function leerCdr(string $cdrZip): array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'sunat-cdr-');
        if ($tempPath === false) {
            throw new \RuntimeException('No se pudo preparar la lectura del CDR.');
        }

        try {
            if (file_put_contents($tempPath, $cdrZip) === false) {
                throw new \RuntimeException('No se pudo leer el archivo CDR recibido.');
            }

            $zip = new ZipArchive();
            if ($zip->open($tempPath) !== true) {
                throw new \RuntimeException('SUNAT devolvió un CDR que no es un ZIP válido.');
            }

            try {
                $xml = null;
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    if (strtolower(pathinfo($zip->getNameIndex($index), PATHINFO_EXTENSION)) === 'xml') {
                        $xml = $zip->getFromIndex($index);
                        break;
                    }
                }
            } finally {
                $zip->close();
            }

            if (! is_string($xml) || $xml === '') {
                throw new \RuntimeException('El CDR no contiene el XML de respuesta.');
            }

            $dom = new DOMDocument();
            if (! $dom->loadXML($xml, LIBXML_NONET)) {
                throw new \RuntimeException('El XML de respuesta CDR no es válido.');
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('cac', self::CAC);
            $xpath->registerNamespace('cbc', self::CBC);
            $code = trim($xpath->evaluate('string((//cac:DocumentResponse/cac:Response/cbc:ResponseCode)[1])'));
            $description = trim($xpath->evaluate('string((//cac:DocumentResponse/cac:Response/cbc:Description)[1])'));
            $notes = [];
            foreach ($xpath->query('//cbc:Note') ?: [] as $note) {
                $text = trim($note->textContent);
                if ($text !== '') {
                    $notes[] = $text;
                }
            }

            if ($code === '') {
                throw new \RuntimeException('El CDR no incluye un código de respuesta.');
            }

            return [
                'codigo' => $code,
                'descripcion' => $description !== '' ? $description : 'SUNAT respondió con código '.$code.'.',
                // Las notas se preservan dentro del CDR y se registran para auditoría.
                'observaciones' => $notes,
            ];
        } finally {
            @unlink($tempPath);
        }
    }

    private function crearZip(string $filename, string $xml): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'sunat-xml-');
        if ($tempPath === false) {
            throw new \RuntimeException('No se pudo preparar el archivo ZIP para SUNAT.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('No se pudo crear el ZIP del comprobante.');
            }

            $zip->addFromString("{$filename}.xml", $xml);
            $zip->close();
            $content = file_get_contents($tempPath);
            if ($content === false) {
                throw new \RuntimeException('No se pudo leer el ZIP del comprobante.');
            }

            return $content;
        } finally {
            @unlink($tempPath);
        }
    }

    private function append(DOMDocument $dom, DOMElement $parent, string $qualifiedName, ?string $value = null, array $attributes = []): DOMElement
    {
        [$prefix] = explode(':', $qualifiedName, 2);
        $namespace = match ($prefix) {
            'cac' => self::CAC,
            'cbc' => self::CBC,
            'ext' => self::EXT,
            default => throw new \InvalidArgumentException("Namespace UBL no soportado: {$prefix}"),
        };
        $element = $dom->createElementNS($namespace, $qualifiedName);

        if ($value !== null) {
            $element->appendChild($dom->createTextNode($value));
        }
        foreach ($attributes as $name => $attributeValue) {
            $element->setAttribute($name, (string) $attributeValue);
        }

        $parent->appendChild($element);

        return $element;
    }

    private function numeroDocumento(object $venta): string
    {
        return $venta->serie.'-'.str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    private function result(bool $success, string $status, string $message, ?string $hash = null): array
    {
        return [
            'success' => $success,
            'estado' => $status,
            'mensaje' => $message,
            'hash' => $hash,
        ];
    }

    /** El POS usa este estado para decidir si muestra «Imprimir ticket»; solo existe con CPE aceptado. */
    private function conImpresion(array $resultado, ?array $impresion): array
    {
        return $impresion === null ? $resultado : [...$resultado, 'impresion' => $impresion];
    }

    /**
     * Crea una sola representación fiscal por venta aceptada y terminal de origen, y
     * devuelve cómo se imprime (automática ya encolada o manual) sin volver a consultar.
     *
     * @return array{modo: string, puede_imprimir: bool, encolada: bool}|null
     */
    private function encolarRepresentacionFiscal(int $idVenta): ?array
    {
        try {
            $venta = Venta::withoutGlobalScopes()->find($idVenta);
            if (! $venta || $venta->estado_sunat !== 'ACEPTADO') {
                return null;
            }

            // Mantiene los helpers de generación XML utilizables en pruebas unitarias
            // sin inicializar servicios de infraestructura que no se necesitan allí.
            $impresion = $this->impresion ?? app(CrearTrabajoImpresionService::class);
            $trabajo = $impresion->comprobanteFiscalAceptado($venta);
            if ($trabajo) {
                Log::info('Representación fiscal encolada para impresión.', [
                    'id_venta' => $venta->id_venta,
                    'id_trabajo_impresion' => $trabajo->id_trabajo_impresion,
                    'id_terminal' => $trabajo->id_terminal,
                ]);
            }

            return $impresion->estadoImpresion($venta, $trabajo !== null);
        } catch (Throwable $exception) {
            // La emisión ya está aceptada. Se conserva el CDR y el operador puede reimprimir luego.
            Log::warning('No se pudo encolar la representación fiscal aceptada.', [
                'id_venta' => $idVenta,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
