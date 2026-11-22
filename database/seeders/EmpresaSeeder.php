<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\SerieSucursal;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

/**
 * Empresa (una sola fila), su sucursal inicial y las series de esa sucursal. Los datos
 * fiscales son provisionales: se completan en Datos generales. Nunca reinicia correlativos.
 */
class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::query()->first() ?? Empresa::query()->create([
            'ruc_empresa' => '20689941333',
            'razon_social' => 'Empresa sin configurar',
            'nombre_comercial' => 'Restaurante',
            'direccion_fiscal' => 'Av. Principal 123',
            'igv_porcentaje' => 18.00,
            'uit_valor' => 5150.00,
            'pie_ticket' => 'Gracias por su visita',
        ]);

        $sucursal = Sucursal::query()->orderBy('id_sucursal')->first() ?? Sucursal::query()->create([
            'nombre' => 'Sede Principal',
            'direccion' => $empresa->direccion_fiscal,
            'responsable' => 'Administración General',
            'codigo_establecimiento' => '0000',
            'estado' => true,
        ]);

        SerieSucursal::query()->firstOrCreate(
            ['id_sucursal' => $sucursal->id_sucursal],
            ['serie_boleta' => 'B001', 'serie_factura' => 'F001', 'serie_guia' => 'T001'],
        );
    }
}
