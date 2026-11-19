<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\StoreImpresoraRequest;
use App\Http\Requests\Catalogos\StoreTerminalPosRequest;
use App\Models\Impresora;
use App\Models\TerminalPos;
use App\Services\Impresion\CrearTrabajoImpresionService;
use App\Services\Impresion\GestionarVinculacionAgenteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImpresoraTerminalController extends Controller
{
    public function index(Request $request): Response
    {
        $branchId = $request->user()->idSucursalActiva();

        return Inertia::render('Configuracion/Impresoras', [
            // El binario se publica por el proceso de despliegue, nunca desde una ruta que ejecute PHP.
            'installerUrl' => filter_var(config('print_agent.installer_url'), FILTER_VALIDATE_URL) ? config('print_agent.installer_url') : null,
            'installerVersion' => config('print_agent.installer_version'),
            'installerSha256' => preg_match('/^[a-f0-9]{64}$/i', (string) config('print_agent.installer_sha256')) ? config('print_agent.installer_sha256') : null,
            'impresoras' => Impresora::query()->where('id_sucursal', $branchId)->orderBy('nombre')->get(),
            'terminales' => TerminalPos::query()
                ->where('id_sucursal', $branchId)
                ->with('impresora:id_impresora,nombre,estado_conexion,activo')
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function storeImpresora(StoreImpresoraRequest $request): RedirectResponse
    {
        Impresora::create($this->printerData($request) + ['id_sucursal' => $request->user()->idSucursalActiva()]);
        return back()->with('success', 'Impresora registrada. Verifica la conexión desde su ficha.');
    }

    public function updateImpresora(StoreImpresoraRequest $request, Impresora $impresora): RedirectResponse
    {
        $this->authorizePrinterBranch($request, $impresora);
        $impresora->update($this->printerData($request));
        return back()->with('success', 'Configuración de impresora actualizada.');
    }

    public function diagnosticar(Request $request, Impresora $impresora, CrearTrabajoImpresionService $impresion): RedirectResponse
    {
        $this->authorizePrinterBranch($request, $impresora);
        $trabajo = $impresion->prueba($impresora, 'DIAGNOSTICO');
        return $trabajo
            ? back()->with('success', 'Diagnóstico en cola. Se actualizará cuando el agente vinculado responda.')
            : back()->withErrors(['impresora' => 'Asigna esta impresora a una terminal activa antes de diagnosticarla.']);
    }

    public function prueba(Request $request, Impresora $impresora, CrearTrabajoImpresionService $impresion): RedirectResponse
    {
        $this->authorizePrinterBranch($request, $impresora);
        $trabajo = $impresion->prueba($impresora, 'PRUEBA');
        return $trabajo
            ? back()->with('success', 'Prueba de impresión en cola. El resultado llegará desde el agente vinculado.')
            : back()->withErrors(['impresora' => 'Asigna esta impresora a una terminal activa antes de enviar una prueba.']);
    }

    public function storeTerminal(StoreTerminalPosRequest $request): RedirectResponse
    {
        $branchId = $request->user()->idSucursalActiva();
        TerminalPos::create($this->terminalData($request, $branchId) + ['id_sucursal' => $branchId]);
        return back()->with('success', 'Terminal POS registrada.');
    }

    public function updateTerminal(StoreTerminalPosRequest $request, TerminalPos $terminalPos): RedirectResponse
    {
        abort_unless((int) $terminalPos->id_sucursal === $request->user()->idSucursalActiva(), 404);
        $terminalPos->update($this->terminalData($request, $terminalPos->id_sucursal));
        return back()->with('success', 'Terminal POS actualizada.');
    }

    /** El código se muestra una sola vez y caduca; no se guarda ni se reenvía al frontend. */
    public function crearCodigoVinculacion(Request $request, TerminalPos $terminalPos, GestionarVinculacionAgenteService $vinculacion): RedirectResponse
    {
        abort_unless((int) $terminalPos->id_sucursal === $request->user()->idSucursalActiva(), 404);
        $codigo = $vinculacion->crearCodigo($terminalPos, $request->user());

        return back()->with('codigo_vinculacion_agente', [
            'terminal' => $terminalPos->nombre,
            'codigo' => $codigo,
            'expira_en' => now()->addMinutes(10)->toIso8601String(),
        ]);
    }

    public function revocarAgente(Request $request, TerminalPos $terminalPos, GestionarVinculacionAgenteService $vinculacion): RedirectResponse
    {
        abort_unless((int) $terminalPos->id_sucursal === $request->user()->idSucursalActiva(), 404);
        $vinculacion->revocar($terminalPos);

        return back()->with('success', 'Agente revocado. La terminal no podrá reclamar más trabajos hasta vincularse otra vez.');
    }

    private function printerData(StoreImpresoraRequest $request): array
    {
        $data = $request->validated();
        foreach (['soporta_escpos', 'corte_automatico', 'abre_cajon', 'activo'] as $field) {
            $data[$field] = $request->boolean($field);
        }
        return $data;
    }

    private function terminalData(StoreTerminalPosRequest $request, int $branchId): array
    {
        $data = $request->validated();
        if (filled($data['id_impresora'] ?? null)) {
            Impresora::query()->whereKey($data['id_impresora'])->where('id_sucursal', $branchId)->firstOrFail();
        }
        foreach (['impresion_automatica', 'activo'] as $field) {
            $data[$field] = $request->boolean($field);
        }
        return $data;
    }

    private function authorizePrinterBranch(Request $request, Impresora $impresora): void
    {
        abort_unless((int) $impresora->id_sucursal === $request->user()->idSucursalActiva(), 404);
    }

}
