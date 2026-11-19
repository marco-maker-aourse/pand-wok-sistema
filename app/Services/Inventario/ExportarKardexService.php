<?php

namespace App\Services\Inventario;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exporta el kardex valorizado (KardexValorizadoService) a Excel y PDF.
 * Solo da formato: todos los importes llegan calculados y son los mismos que la pantalla.
 */
class ExportarKardexService
{
    /** Columnas: fecha, operación, documento, usuario, 3 de entradas, 3 de salidas, 3 de saldo. */
    private const COLUMNAS = 13;

    private const FORMATO_CANTIDAD = '#,##0.00##';

    private const FORMATO_COSTO = '"S/ "#,##0.0000';

    private const FORMATO_TOTAL = '"S/ "#,##0.00';

    public static function nombreArchivo(array $kardex, string $extension): string
    {
        $producto = preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', (string) $kardex['producto']['nombre']) ?: 'producto');

        return strtolower(trim("kardex-{$producto}-{$kardex['periodo']['desde']}-al-{$kardex['periodo']['hasta']}", '-')).".{$extension}";
    }

    /** Contenido binario del .xlsx. */
    public function excel(array $kardex): string
    {
        $libro = new Spreadsheet;
        $libro->getProperties()->setTitle('Kardex valorizado')->setCreator((string) ($kardex['empresa']['razon_social'] ?? 'SWVAMM'));
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Kardex');
        $ultima = Coordinate::stringFromColumnIndex(self::COLUMNAS);

        // ---------- Encabezado del reporte
        $hoja->setCellValue('A1', 'KARDEX VALORIZADO');
        $hoja->mergeCells("A1:{$ultima}1");
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $datos = [
            ['Empresa', trim(($kardex['empresa']['razon_social'] ?? '').' — RUC '.($kardex['empresa']['ruc'] ?? ''), ' —')],
            ['Sucursal', $kardex['empresa']['sucursal'] ?? ''],
            ['Producto', trim(($kardex['producto']['codigo'] ? $kardex['producto']['codigo'].' — ' : '').$kardex['producto']['nombre'])],
            ['Unidad de medida', $kardex['producto']['unidad']],
            ['Periodo', self::fecha($kardex['periodo']['desde']).' al '.self::fecha($kardex['periodo']['hasta'])],
            ['Método de valuación', $kardex['metodo']],
        ];
        $fila = 3;
        foreach ($datos as [$etiqueta, $valor]) {
            $hoja->setCellValue("A{$fila}", $etiqueta);
            $hoja->setCellValue("C{$fila}", $valor);
            $hoja->mergeCells("A{$fila}:B{$fila}");
            $hoja->mergeCells("C{$fila}:{$ultima}{$fila}");
            $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
            $fila++;
        }

        // ---------- Cabecera de la tabla (dos niveles)
        $cab = $fila + 1;
        $sub = $cab + 1;
        foreach (['A' => 'Fecha', 'B' => 'Operación', 'C' => 'Documento', 'D' => 'Usuario'] as $col => $texto) {
            $hoja->setCellValue("{$col}{$cab}", $texto);
            $hoja->mergeCells("{$col}{$cab}:{$col}{$sub}");
        }
        foreach (['E' => 'ENTRADAS', 'H' => 'SALIDAS', 'K' => 'SALDO'] as $col => $texto) {
            $hasta = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + 2);
            $hoja->setCellValue("{$col}{$cab}", $texto);
            $hoja->mergeCells("{$col}{$cab}:{$hasta}{$cab}");
            foreach (['Cantidad', 'Costo unit.', 'Costo total'] as $i => $titulo) {
                $hoja->setCellValue(Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + $i).$sub, $titulo);
            }
        }
        $cabecera = $hoja->getStyle("A{$cab}:{$ultima}{$sub}");
        $cabecera->getFont()->setBold(true);
        $cabecera->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $cabecera->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

        // ---------- Saldo inicial, movimientos y totales
        $fila = $sub + 1;
        $primeraDatos = $fila;
        $this->filaSaldo($hoja, $fila++, 'Saldo inicial', $kardex['saldo_inicial']);

        foreach ($kardex['movimientos'] as $m) {
            $hoja->setCellValue("A{$fila}", $m['fecha']);
            $hoja->setCellValue("B{$fila}", $m['operacion']);
            $hoja->setCellValueExplicit("C{$fila}", (string) ($m['documento'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $hoja->setCellValue("D{$fila}", $m['usuario'] ?? '');
            $this->tres($hoja, 'E', $fila, $m['entrada']);
            $this->tres($hoja, 'H', $fila, $m['salida']);
            $this->tres($hoja, 'K', $fila, $m['saldo']);
            $fila++;
        }

        $t = $kardex['totales'];
        $hoja->setCellValue("A{$fila}", 'Totales del periodo');
        $hoja->mergeCells("A{$fila}:D{$fila}");
        $hoja->setCellValue("E{$fila}", $t['entradas_cantidad']);
        $hoja->setCellValue("G{$fila}", $t['entradas_total']);
        $hoja->setCellValue("H{$fila}", $t['salidas_cantidad']);
        $hoja->setCellValue("J{$fila}", $t['salidas_total']);
        $hoja->setCellValue("K{$fila}", $kardex['saldo_final']['cantidad']);
        $hoja->setCellValue("L{$fila}", $kardex['saldo_final']['costo_unitario']);
        $hoja->setCellValue("M{$fila}", $kardex['saldo_final']['total']);
        $hoja->getStyle("A{$fila}:{$ultima}{$fila}")->getFont()->setBold(true);
        $hoja->getStyle("A{$fila}:{$ultima}{$fila}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
        $ultimaDatos = $fila;

        // ---------- Formatos
        foreach (['E', 'H', 'K'] as $col) {
            $hoja->getStyle("{$col}{$primeraDatos}:{$col}{$ultimaDatos}")->getNumberFormat()->setFormatCode(self::FORMATO_CANTIDAD);
        }
        foreach (['F', 'I', 'L'] as $col) {
            $hoja->getStyle("{$col}{$primeraDatos}:{$col}{$ultimaDatos}")->getNumberFormat()->setFormatCode(self::FORMATO_COSTO);
        }
        foreach (['G', 'J', 'M'] as $col) {
            $hoja->getStyle("{$col}{$primeraDatos}:{$col}{$ultimaDatos}")->getNumberFormat()->setFormatCode(self::FORMATO_TOTAL);
        }
        $hoja->getStyle("A{$cab}:{$ultima}{$ultimaDatos}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('94A3B8');
        $hoja->getStyle("A{$primeraDatos}:{$ultima}{$primeraDatos}")->getFont()->setItalic(true);

        foreach (['A' => 16, 'B' => 20, 'C' => 18, 'D' => 16] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        foreach (range('E', 'M') as $col) {
            $hoja->getColumnDimension($col)->setWidth(13);
        }

        $nota = $ultimaDatos + 2;
        $hoja->setCellValue("A{$nota}", "Generado el {$kardex['generado']['en']}".($kardex['generado']['por'] ? " por {$kardex['generado']['por']}" : '').'. Cantidades en '.mb_strtolower($kardex['producto']['unidad']).'.');
        $hoja->getStyle("A{$nota}")->getFont()->setItalic(true)->setSize(9);
        if ($kardex['truncado']) {
            $hoja->setCellValue('A'.($nota + 1), 'El periodo supera '.KardexValorizadoService::MAXIMO_MOVIMIENTOS.' movimientos: se muestran los primeros. Reduce el rango de fechas.');
            $hoja->getStyle('A'.($nota + 1))->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
        }

        // Navegación e impresión: cabecera fija, filtro y hoja horizontal ajustada al ancho.
        $hoja->freezePane('A'.($sub + 1));
        $hoja->setAutoFilter("A{$sub}:{$ultima}".($ultimaDatos - 1));
        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd($cab, $sub);
        $hoja->getPageMargins()->setLeft(0.4)->setRight(0.4);
        $hoja->getHeaderFooter()->setOddFooter('&LKardex — '.$kardex['producto']['nombre'].'&RPágina &P de &N');

        $archivo = fopen('php://temp', 'r+');
        (new Xlsx($libro))->save($archivo);
        rewind($archivo);
        $contenido = stream_get_contents($archivo);
        fclose($archivo);
        $libro->disconnectWorksheets();

        return $contenido;
    }

    /** Contenido binario del PDF (A4 horizontal). */
    public function pdf(array $kardex): string
    {
        return Pdf::loadView('pdf.kardex', ['kardex' => $kardex])
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isPhpEnabled', true)
            ->output();
    }

    private function filaSaldo(Worksheet $hoja, int $fila, string $texto, array $saldo): void
    {
        $hoja->setCellValue("A{$fila}", $texto);
        $hoja->mergeCells("A{$fila}:D{$fila}");
        $this->tres($hoja, 'K', $fila, $saldo);
    }

    /** Cantidad, costo unitario y total desde la columna indicada; vacío si no hay movimiento. */
    private function tres(Worksheet $hoja, string $columna, int $fila, ?array $valores): void
    {
        if (! $valores) {
            return;
        }
        $indice = Coordinate::columnIndexFromString($columna);
        foreach (['cantidad', 'costo_unitario', 'total'] as $i => $clave) {
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($indice + $i).$fila, $valores[$clave]);
        }
    }

    private static function fecha(string $iso): string
    {
        [$a, $m, $d] = explode('-', $iso);

        return "{$d}/{$m}/{$a}";
    }
}
