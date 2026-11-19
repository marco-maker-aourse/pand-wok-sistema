<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\TipoDocumento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TipoDocumentoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('TiposDocumento/Index', [
            'tipos_documento' => TipoDocumento::query()
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
                'max:4',
                Rule::unique(TipoDocumento::class, 'codigo_sunat'),
            ],
            'nombre'          => ['required', 'string', 'max:100'],
            'longitud_exacta' => ['nullable', 'integer', 'min:1', 'max:30'],
            'tipo_persona'    => ['nullable', 'string', 'max:30'],
            'estado'          => ['boolean'],
        ], [
            'codigo_sunat.required' => 'El código SUNAT es obligatorio.',
            'codigo_sunat.unique'   => 'Ya existe un documento registrado con ese código SUNAT.',
            'nombre.required'       => 'El nombre del documento es obligatorio.',
        ]);

        TipoDocumento::create($validated);

        return back()->with('success', 'Tipo de documento registrado exitosamente.');
    }

    public function update(Request $request, TipoDocumento $tipoDocumento): RedirectResponse
    {
        $validated = $request->validate([
            'codigo_sunat' => [
                'required',
                'string',
                'max:4',
                Rule::unique(TipoDocumento::class, 'codigo_sunat')
                    ->ignore($tipoDocumento->id_tipo_documento, 'id_tipo_documento'),
            ],
            'nombre'          => ['required', 'string', 'max:100'],
            'longitud_exacta' => ['nullable', 'integer', 'min:1', 'max:30'],
            'tipo_persona'    => ['nullable', 'string', 'max:30'],
            'estado'          => ['boolean'],
        ], [
            'codigo_sunat.required' => 'El código SUNAT es obligatorio.',
            'codigo_sunat.unique'   => 'Ya existe otro documento con ese código SUNAT.',
            'nombre.required'       => 'El nombre del documento es obligatorio.',
        ]);

        $tipoDocumento->update($validated);

        return back()->with('success', 'Tipo de documento actualizado exitosamente.');
    }

    public function destroy(TipoDocumento $tipoDocumento): RedirectResponse
    {
        $tipoDocumento->update(['estado' => false]);

        return back()->with('success', 'Tipo de documento desactivado del catálogo.');
    }
}