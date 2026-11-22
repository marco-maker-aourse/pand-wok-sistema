<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ActualizarDatosGeneralesRequest;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Services\Comprobantes\SeriesSucursalService;
use App\Services\Configuracion\DatosGeneralesService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DatosGeneralesController extends Controller
{
    public function __construct(
        private readonly DatosGeneralesService $datosGenerales,
        private readonly SeriesSucursalService $series,
    ) {}

    public function index(Request $request): Response
    {
        $branchId = $request->user()->idSucursalActiva();
        $branch = Sucursal::query()->findOrFail($branchId);
        $empresa = $this->datosGenerales->paraEdicion();

        return Inertia::render('Configuracion/DatosGenerales', [
            // Datos de la empresa (comunes) + series de la sucursal activa (propias).
            'datos' => [...$empresa->only(DatosGeneralesService::CAMPOS_EDITABLES), ...$this->series->deSucursal($branchId)],
            'logoUrl' => $this->datosGenerales->logoUrl($empresa->logo_path),
            'sucursalActual' => $branch->only(['id_sucursal', 'nombre', 'codigo_establecimiento']),
        ]);
    }

    public function update(ActualizarDatosGeneralesRequest $request): RedirectResponse
    {
        // La sucursal se toma exclusivamente de la sesión; un formulario no puede alterar otra sede.
        $branchId = $request->user()->idSucursalActiva();
        $validados = collect($request->validated());
        $datosEmpresa = $validados->except(['codigo_establecimiento', 'logo', ...SeriesSucursalService::CAMPOS])->all();

        // Cada sucursal emite con sus propias series: se validan antes de guardar nada.
        $series = $validados->only(SeriesSucursalService::CAMPOS)->all();
        try {
            $this->series->validarUnicas($branchId, $series);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['serie_boleta' => $exception->getMessage()]);
        }

        // Empresa, series y código de establecimiento de la sucursal se confirman juntos.
        $empresa = DB::transaction(function () use ($branchId, $datosEmpresa, $series, $request): Empresa {
            $empresa = $this->datosGenerales->empresa();
            $empresa ? $empresa->update($datosEmpresa) : $empresa = Empresa::create($datosEmpresa);

            $this->series->guardar($branchId, $series);

            Sucursal::query()
                ->whereKey($branchId)
                ->update(['codigo_establecimiento' => $request->validated('codigo_establecimiento')]);

            return $empresa;
        });

        if ($request->hasFile('logo')) {
            $this->reemplazarLogo($empresa, $request);
        }

        $this->datosGenerales->olvidarIdentidad();

        return back()->with('success', 'Datos generales actualizados correctamente.');
    }

    private function reemplazarLogo(Empresa $empresa, ActualizarDatosGeneralesRequest $request): void
    {
        $directory = 'empresa/logo';
        $newPath = $request->file('logo')->store($directory, 'public');

        // Solo se elimina el archivo que gestiona el propio sistema.
        if ($empresa->logo_path && str_starts_with($empresa->logo_path, $directory.'/')) {
            Storage::disk('public')->delete($empresa->logo_path);
        }

        $empresa->update(['logo_path' => $newPath]);
    }
}
