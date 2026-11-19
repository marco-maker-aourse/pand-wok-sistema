<?php

namespace App\Http\Requests\Configuracion;

use App\Models\DatosGen;
use App\Models\Sucursal;
use App\Services\Configuracion\DatosGeneralesService;
use App\Rules\Ruc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valida la configuración operativa; los secretos nunca se reciben desde esta pantalla. */
class ActualizarDatosGeneralesRequest extends FormRequest
{
    /**
     * RUC, series, IGV y correlativos definen la facturación de la sucursal: solo
     * un rol administrador puede cambiarlos (mismo criterio que el menú de React
     * y la gestión del PIN de caja). Ver la pantalla sigue con `dashboard.ver`.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdministrator();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        // Registro que se guardará: el propio de la sucursal o el de la empresa principal.
        $registro = app(DatosGeneralesService::class)->empresaDe($this->user()?->idSucursalActiva());

        return [
            // El RUC es único por empresa (restricción de operaciones.datos_gen).
            'ruc_empresa'      => ['required', new Ruc, Rule::unique(DatosGen::class, 'ruc_empresa')->ignore($registro?->id_datos_gen, 'id_datos_gen')],
            'codigo_establecimiento' => ['nullable', 'digits:4', Rule::unique(Sucursal::class, 'codigo_establecimiento')->ignore($this->user()?->idSucursalActiva(), 'id_sucursal')],
            'razon_social'     => ['required', 'string', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:200'],
            'direccion_fiscal' => ['nullable', 'string', 'max:2000'],
            'igv_porcentaje'   => ['required', 'numeric', 'min:0', 'max:100'],
            'uit_valor'        => ['nullable', 'numeric', 'min:0'],
            // Series electrónicas SUNAT: letra del comprobante + 3 caracteres alfanuméricos.
            'serie_boleta'     => ['nullable', 'regex:/^B[A-Z0-9]{3}$/'],
            'serie_factura'    => ['nullable', 'regex:/^F[A-Z0-9]{3}$/'],
            'serie_guia'       => ['nullable', 'regex:/^T[A-Z0-9]{3}$/'],
            'pie_ticket'       => ['nullable', 'string', 'max:2000'],
            'logo'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // Nuevas columnas autorizadas para colores y textos del Login.
            // Los colores se inyectan en CSS: solo se aceptan hexadecimales.
            'color_primario'          => ['nullable', 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/'],
            'color_secundario'        => ['nullable', 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/'],
            'texto_login_titulo'      => ['nullable', 'string', 'max:150'],
            'texto_login_descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['serie_boleta', 'serie_factura', 'serie_guia'] as $serie) {
            if (is_string($this->input($serie))) {
                $this->merge([$serie => strtoupper(trim($this->input($serie)))]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'ruc_empresa.unique' => 'Ese RUC ya pertenece a otra empresa registrada.',
            'serie_boleta.regex' => 'La serie de boleta debe tener 4 caracteres y empezar con B (ej. B001).',
            'serie_factura.regex' => 'La serie de factura debe tener 4 caracteres y empezar con F (ej. F001).',
            'serie_guia.regex' => 'La serie de guía debe tener 4 caracteres y empezar con T (ej. T001).',
            'color_primario.regex' => 'El color principal debe ser un código hexadecimal (ej. #0E7490).',
            'color_secundario.regex' => 'El color secundario debe ser un código hexadecimal (ej. #0F172A).',
        ];
    }
}