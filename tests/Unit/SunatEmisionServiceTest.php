<?php

namespace Tests\Unit;

use App\Services\Ventas\SunatEmisionService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SunatEmisionServiceTest extends TestCase
{
    public function test_incluye_codigo_de_establecimiento_en_direccion_del_emisor(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $invoice = $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            'Invoice',
        );
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $dom->appendChild($invoice);

        $method = new ReflectionMethod(SunatEmisionService::class, 'appendEmisor');
        $method->invoke(new SunatEmisionService(), $dom, $invoice, (object) [
            'ruc_empresa' => '20123456789',
            'razon_social' => 'Empresa de prueba S.A.C.',
            'direccion_fiscal' => 'Dirección de prueba',
            'codigo_establecimiento' => '0123',
        ]);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $code = $xpath->query('//cbc:AddressTypeCode')->item(0);

        if (! $code instanceof DOMElement) {
            self::fail('El XML generado debe incluir el código de establecimiento como elemento.');
        }

        $this->assertSame('0123', $code->textContent);
        $this->assertSame('PE:SUNAT', $code->getAttribute('listAgencyName'));
        $this->assertSame('Establecimientos anexos', $code->getAttribute('listName'));
    }

    public function test_conserva_precision_del_precio_unitario_para_cuadrar_importe_de_linea(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $invoice = $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            'Invoice',
        );
        foreach ([
            'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        ] as $prefix => $namespace) {
            $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', "xmlns:{$prefix}", $namespace);
        }
        $dom->appendChild($invoice);

        $method = new ReflectionMethod(SunatEmisionService::class, 'appendLinea');
        $method->invoke(new SunatEmisionService(), $dom, $invoice, 1, [
            'detalle' => (object) [
                'unidad_sunat' => 'NIU',
                'nombre_producto' => 'Producto de prueba',
                'codigo_sunat' => null,
            ],
            'cantidad' => 2.0,
            'base' => 915.25,
            'precio_unitario' => 457.625,
            'precio_con_igv' => 540.0,
            'descuento' => 0.0,
            'igv' => 164.75,
            'gravable' => true,
        ], (object) ['igv_porcentaje' => 18]);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $lineAmount = (float) $xpath->evaluate('string(//cac:InvoiceLine/cbc:LineExtensionAmount)');
        $quantity = (float) $xpath->evaluate('string(//cac:InvoiceLine/cbc:InvoicedQuantity)');
        $unitValue = (float) $xpath->evaluate('string(//cac:InvoiceLine/cac:Price/cbc:PriceAmount)');

        $this->assertSame('457.6250000000', $xpath->evaluate('string(//cac:InvoiceLine/cac:Price/cbc:PriceAmount)'));
        $this->assertEqualsWithDelta($lineAmount, $quantity * $unitValue, 0.0001);
    }

    public function test_declara_forma_de_pago_contado_con_el_total_del_comprobante(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $invoice = $dom->createElementNS(
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            'Invoice',
        );
        $invoice->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:cac',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        );
        $invoice->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:cbc',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        );
        $dom->appendChild($invoice);

        $line = [
            'detalle' => (object) [
                'unidad_sunat' => 'NIU',
                'nombre_producto' => 'Producto de prueba',
                'codigo_sunat' => null,
            ],
            'cantidad' => 2.0,
            'base' => 915.25,
            'precio_unitario' => 457.625,
            'precio_con_igv' => 540.0,
            'descuento' => 0.0,
            'igv' => 164.75,
            'gravable' => true,
        ];
        $service = new SunatEmisionService();
        $financialSections = new ReflectionMethod(SunatEmisionService::class, 'appendFinancialSections');
        $financialSections->invoke($service, $dom, $invoice, (object) [
            'op_gravadas' => 915.25,
            'op_exoneradas' => 0,
            'igv' => 164.75,
            'total_venta' => 1080.00,
            'descuento_global' => 0,
        ], (object) ['igv_porcentaje' => 18], [$line]);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace(
            'cac',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        );
        $xpath->registerNamespace(
            'cbc',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        );
        $this->assertSame('FormaPago', $xpath->evaluate('string(//cac:PaymentTerms/cbc:ID)'));
        $this->assertSame('Contado', $xpath->evaluate('string(//cac:PaymentTerms/cbc:PaymentMeansID)'));
        $this->assertSame('1080.00', $xpath->evaluate('string(//cac:PaymentTerms/cbc:Amount)'));
        $this->assertSame('PEN', $xpath->evaluate('string(//cac:PaymentTerms/cbc:Amount/@currencyID)'));
        $this->assertSame(
            'TaxTotal',
            $xpath->evaluate('local-name(//cac:PaymentTerms/following-sibling::*[1])'),
        );
        $this->assertSame(
            'LegalMonetaryTotal',
            $xpath->evaluate('local-name(//cac:TaxTotal/following-sibling::*[1])'),
        );
        $this->assertSame(
            'InvoiceLine',
            $xpath->evaluate('local-name(//cac:LegalMonetaryTotal/following-sibling::*[1])'),
        );
        $this->assertSame(1, (int) $xpath->evaluate('count(//cac:InvoiceLine)'));
    }
}
