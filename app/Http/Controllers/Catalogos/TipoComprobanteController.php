<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\TipoComprobante;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TipoComprobanteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('TiposComprobante/Index', [
            'tipos_comprobante' => TipoComprobante::query()
                ->orderBy('codigo_sunat')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'codigo_sunat' => [
                'required',
                'string',
                'size:2',
                'regex:/^\d{2}$/',
                Rule::unique(TipoComprobante::class, 'codigo_sunat'),
            ],
            'descripcion'       => ['required', 'string', 'max:100'],
            'exige_ruc'         => ['boolean'],
            'permite_anulacion' => ['boolean'],
            'estado'            => ['boolean'],
        ], [
            'codigo_sunat.required' => 'EL CÓDIGO SUNAT ES OBLIGATORIO.',
            'codigo_sunat.size'     => 'EL CÓDIGO SUNAT DEBE TENER EXACTAMENTE 2 DÍGITOS (EJ. 01, 03).',
            'codigo_sunat.regex'    => 'EL CÓDIGO SUNAT DEBE SER NUMÉRICO DE 2 DÍGITOS.',
            'codigo_sunat.unique'   => 'YA EXISTE UN COMPROBANTE REGISTRADO CON ESE CÓDIGO SUNAT.',
            'descripcion.required'  => 'LA DESCRIPCIÓN DEL COMPROBANTE ES OBLIGATORIA.',
        ]);

        TipoComprobante::create([
            'codigo_sunat'      => $validated['codigo_sunat'],
            'descripcion'       => strtoupper(trim($validated['descripcion'])),
            'exige_ruc'         => $validated['exige_ruc'] ?? false,
            'permite_anulacion' => $validated['permite_anulacion'] ?? true,
            'estado'            => $validated['estado'] ?? true,
        ]);

        return back()->with('success', 'TIPO DE COMPROBANTE REGISTRADO CON ÉXITO.');
    }

    public function update(Request $request, TipoComprobante $tipoComprobante): RedirectResponse
    {
        $validated = $request->validate([
            'codigo_sunat' => [
                'required',
                'string',
                'size:2',
                'regex:/^\d{2}$/',
                Rule::unique(TipoComprobante::class, 'codigo_sunat')
                    ->ignore($tipoComprobante->id_tipo_comprobante, 'id_tipo_comprobante'),
            ],
            'descripcion'       => ['required', 'string', 'max:100'],
            'exige_ruc'         => ['boolean'],
            'permite_anulacion' => ['boolean'],
            'estado'            => ['boolean'],
        ], [
            'codigo_sunat.required' => 'EL CÓDIGO SUNAT ES OBLIGATORIO.',
            'codigo_sunat.size'     => 'EL CÓDIGO SUNAT DEBE TENER EXACTAMENTE 2 DÍGITOS.',
            'codigo_sunat.regex'    => 'EL CÓDIGO SUNAT DEBE SER NUMÉRICO DE 2 DÍGITOS.',
            'codigo_sunat.unique'   => 'YA EXISTE OTRO COMPROBANTE CON ESE CÓDIGO SUNAT.',
            'descripcion.required'  => 'LA DESCRIPCIÓN DEL COMPROBANTE ES OBLIGATORIA.',
        ]);

        $tipoComprobante->update([
            'codigo_sunat'      => $validated['codigo_sunat'],
            'descripcion'       => strtoupper(trim($validated['descripcion'])),
            'exige_ruc'         => $validated['exige_ruc'] ?? false,
            'permite_anulacion' => $validated['permite_anulacion'] ?? true,
            'estado'            => $validated['estado'] ?? true,
        ]);

        return back()->with('success', 'TIPO DE COMPROBANTE ACTUALIZADO CON ÉXITO.');
    }

    public function destroy(TipoComprobante $tipoComprobante): RedirectResponse
    {
        $tipoComprobante->update(['estado' => false]);

        return back()->with('success', 'COMPROBANTE DESACTIVADO DEL CATÁLOGO.');
    }
}