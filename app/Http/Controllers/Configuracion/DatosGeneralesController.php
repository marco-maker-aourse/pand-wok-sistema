<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ActualizarDatosGeneralesRequest;
use App\Models\DatosGen;
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
        $data = $this->datosGenerales->paraEdicion($branchId);
        $usaDatosDeEmpresa = $data->exists && (int) $data->id_sucursal !== $branchId;

        return Inertia::render('Configuracion/DatosGenerales', [
            // Las series siempre son las de la sucursal activa, aunque comparta los datos de la empresa.
            'datos' => [...$data->only(DatosGeneralesService::CAMPOS_EDITABLES), ...($usaDatosDeEmpresa ? $this->series->deSucursal($branchId) : [])],
            'logoUrl' => $this->datosGenerales->logoUrl($data->logo_path),
            'sucursalActual' => $branch->only(['id_sucursal', 'nombre', 'codigo_establecimiento']),
            // La sucursal comparte los datos de la empresa principal (nombre, logo, colores, RUC).
            'usaDatosDeEmpresa' => $usaDatosDeEmpresa,
            'sucursalEmpresa' => $usaDatosDeEmpresa ? Sucursal::query()->whereKey($data->id_sucursal)->value('nombre') : null,
        ]);
    }

    public function update(ActualizarDatosGeneralesRequest $request): RedirectResponse
    {
        // La sucursal se toma exclusivamente de la sesión; un formulario no puede alterar otra sede.
        $branchId = $request->user()->idSucursalActiva();
        $data = collect($request->validated())->except(['codigo_establecimiento', 'logo'])->all();
        $registro = $this->datosGenerales->empresaDe($branchId);
        $compartido = $registro && (int) $registro->id_sucursal !== $branchId;

        // Cada sucursal emite con sus propias series: se validan antes de guardar nada.
        $series = collect($request->validated())->only(SeriesSucursalService::CAMPOS)->all();
        try {
            $this->series->validarUnicas($branchId, $series);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['serie_boleta' => $exception->getMessage()]);
        }

        // Datos de la empresa y código de establecimiento de la sucursal se confirman juntos.
        $registro = DB::transaction(function () use ($registro, $compartido, $branchId, $data, $request, $series): DatosGen {
            if (! $registro) {
                // Primera configuración del sistema: nace el registro de la empresa.
                $registro = DatosGen::create(['id_sucursal' => $branchId, ...$data]);
            } else {
                // Desde otra sucursal se editan los datos globales de la empresa,
                // nunca la numeración de comprobantes de la sucursal principal.
                $registro->update($compartido ? collect($data)->except(DatosGeneralesService::CAMPOS_NUMERACION)->all() : $data);
            }

            // Una sucursal que comparte los datos de la empresa guarda sus series aparte.
            if ($compartido) {
                $this->series->guardar($branchId, $series);
            }

            Sucursal::query()
                ->whereKey($branchId)
                ->update(['codigo_establecimiento' => $request->validated('codigo_establecimiento')]);

            return $registro;
        });

        if ($request->hasFile('logo')) {
            $this->reemplazarLogo($registro, $request);
        }

        // Nombre, logo y colores se comparten entre sucursales: se invalida la identidad de todas.
        $this->datosGenerales->olvidarIdentidad();

        return back()->with('success', $compartido
            ? 'Datos de la empresa actualizados para todas sus sucursales.'
            : 'Datos generales actualizados correctamente.');
    }

    private function reemplazarLogo(DatosGen $generalData, ActualizarDatosGeneralesRequest $request): void
    {
        $directory = "organizaciones/{$generalData->id_sucursal}/logo";
        $newPath = $request->file('logo')->store($directory, 'public');

        // Solo se elimina el archivo gestionado por esta misma sucursal.
        if ($generalData->logo_path && str_starts_with($generalData->logo_path, $directory.'/')) {
            Storage::disk('public')->delete($generalData->logo_path);
        }

        $generalData->update(['logo_path' => $newPath]);
    }
}
