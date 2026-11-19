<?php

namespace Tests\Unit;

use App\Services\Asistente\ClasificadorConsultasOperativas;
use Tests\TestCase;

class ClasificadorConsultasOperativasTest extends TestCase
{
    public function test_reconoce_variantes_de_metodos_de_pago(): void
    {
        $clasificador = app(ClasificadorConsultasOperativas::class);

        $this->assertSame('metodos_pago', $clasificador->clasificar('que metodos de pago hay'));
        $this->assertSame('metodos_pago', $clasificador->clasificar('formas de pagos disponibles'));
    }

    public function test_reconoce_consultas_proactivas_y_seguimientos(): void
    {
        $clasificador = app(ClasificadorConsultasOperativas::class);

        $this->assertSame('analisis', $clasificador->clasificar('cual es la proyeccion de ventas'));
        $this->assertSame('recomendaciones', $clasificador->clasificar('que recomiendas revisar'));
        $this->assertSame('seguimiento_documental', $clasificador->clasificar('7 dias antes'));
        $this->assertSame('producto_mas_vendido', $clasificador->clasificar('que producto se vendio mas'));
    }
}
