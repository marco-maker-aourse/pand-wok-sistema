<?php

namespace App\Http\Controllers\Operaciones;

use App\Http\Controllers\Controller;
use App\Models\TurnoCaja;
use App\Models\TerminalPos;
use App\Services\Caja\GestionarTurnoCajaService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TurnoCajaController extends Controller
{
    public function __construct(private readonly GestionarTurnoCajaService $caja) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $turno = TurnoCaja::query()
            ->where('id_usuario', $user->id_usuario)
            ->where('id_sucursal', $user->idSucursalActiva())
            ->where('estado', 'ABIERTO')
            ->latest('id_turno')
            ->first();
        $turnos = TurnoCaja::query()
            ->where('id_usuario', $user->id_usuario)
            ->where('id_sucursal', $user->idSucursalActiva())
            ->latest('id_turno')
            ->limit(10)
            ->get([
                'id_turno',
                'monto_apertura',
                'monto_cierre_declarado',
                'fecha_apertura',
                'fecha_cierre',
                'estado',
            ]);

        return Inertia::render('Caja/Index', [
            'turno' => $turno,
            'resumen' => $turno ? $this->caja->resumen($turno) : null,
            'turnos' => $this->caja->conDiferenciaDeCuadre($turnos),
            'terminales' => TerminalPos::query()
                ->where('id_sucursal', $user->idSucursalActiva())
                ->where('activo', true)
                ->whereHas('impresora', fn ($query) => $query->where('activo', true))
                ->with('impresora:id_impresora,nombre,estado_conexion,activo')
                ->orderBy('nombre')
                ->get(['id_terminal', 'id_sucursal', 'id_impresora', 'codigo', 'nombre', 'impresion_automatica']),
        ]);
    }

    public function apertura(Request $request)
    {
        $data = $request->validate([
            'monto_apertura' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'pin_cajero' => ['nullable', 'digits_between:4,6'],
            'id_terminal' => ['nullable', 'integer'],
            // Nunca aceptar URLs arbitrarias como destino tras una operación financiera.
            'redirect_to' => ['nullable', 'in:/caja,/ventas/create'],
        ]);

        $hayTerminalConImpresora = TerminalPos::query()
            ->where('id_sucursal', $request->user()->idSucursalActiva())
            ->where('activo', true)
            ->whereHas('impresora', fn ($query) => $query->where('activo', true))
            ->exists();

        if ($hayTerminalConImpresora && empty($data['id_terminal'])) {
            throw ValidationException::withMessages([
                'id_terminal' => 'Selecciona la terminal física que operará este turno para habilitar la impresión automática.',
            ]);
        }
        try {
            $this->validarPinCajero($request, (string) ($data['pin_cajero'] ?? ''));
            $this->caja->abrir(
                $request->user()->id_usuario,
                $request->user()->idSucursalActiva(),
                (float) $data['monto_apertura'],
                isset($data['id_terminal']) ? (int) $data['id_terminal'] : null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['monto_apertura' => $exception->getMessage()]);
        }

        return redirect($data['redirect_to'] ?? route('ventas.create'))
            ->with('success', 'Turno de caja abierto correctamente.');
    }

    /** Asocia una terminal solo al turno abierto actual; nunca modifica ventas ya registradas. */
    public function asignarTerminal(Request $request, int $idTurno)
    {
        $data = $request->validate([
            'id_terminal' => ['required', 'integer'],
            'pin_cajero' => ['required', 'digits_between:4,6'],
        ]);

        try {
            $this->validarPinCajero($request, $data['pin_cajero']);
            $this->caja->asignarTerminal(
                $idTurno,
                $request->user()->id_usuario,
                $request->user()->idSucursalActiva(),
                (int) $data['id_terminal'],
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['id_terminal' => $exception->getMessage()]);
        }

        return redirect()->route('caja.index')
            ->with('success', 'Terminal asignada al turno actual. Las próximas aceptaciones SUNAT se enviarán a su cola de impresión.');
    }

    public function registrarMovimiento(Request $request)
    {
        $data = $request->validate([
            'id_turno' => ['required', 'integer'],
            'tipo_movimiento' => ['required', 'in:INGRESO,EGRESO'],
            'monto' => ['required', 'numeric', 'min:0.10', 'max:999999999999.99'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);
        try {
            $this->caja->registrarMovimiento(
                (int) $data['id_turno'],
                $request->user()->id_usuario,
                $request->user()->idSucursalActiva(),
                $data['tipo_movimiento'],
                (float) $data['monto'],
                $data['motivo'],
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['monto' => $exception->getMessage()]);
        }

        return redirect()->route('caja.index')->with('success', 'Movimiento de caja registrado.');
    }

    public function resumenArqueo(Request $request, int $idTurno)
    {
        $turno = $this->turnoPropioAbierto($request, $idTurno);

        return response()->json($this->caja->resumen($turno));
    }

    public function cierre(Request $request, int $idTurno)
    {
        $data = $request->validate([
            'monto_cierre_declarado' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'pin_cajero' => ['nullable', 'digits_between:4,6'],
        ]);
        try {
            $this->validarPinCajero($request, (string) ($data['pin_cajero'] ?? ''));
            $diferencia = $this->caja->cerrar(
                $idTurno,
                $request->user()->id_usuario,
                $request->user()->idSucursalActiva(),
                (float) $data['monto_cierre_declarado'],
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['monto_cierre_declarado' => $exception->getMessage()]);
        }

        return redirect()->route('caja.index')
            ->with('success', 'Turno cerrado. Diferencia de cuadre: S/ '.number_format($diferencia, 2));
    }

    private function turnoPropioAbierto(Request $request, int $idTurno): TurnoCaja
    {
        return TurnoCaja::query()
            ->whereKey($idTurno)
            ->where('id_usuario', $request->user()->id_usuario)
            ->where('id_sucursal', $request->user()->idSucursalActiva())
            ->where('estado', 'ABIERTO')
            ->firstOrFail();
    }

    /** Revalida al cajero en acciones de dinero sin exponer ni almacenar su PIN en sesión. */
    private function validarPinCajero(Request $request, string $pin): void
    {
        $user = $request->user();
        if (blank($user?->pin_cajero)) {
            throw ValidationException::withMessages([
                'pin_cajero' => 'Tu usuario no tiene PIN de caja configurado. Solicita a un administrador que lo configure.',
            ]);
        }

        $key = 'caja-pin:'.$user->id_usuario.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'pin_cajero' => 'PIN temporalmente bloqueado. Solicita a un administrador que valide tu acceso.',
            ]);
        }

        $pinValido = Hash::isHashed($user->pin_cajero)
            ? Hash::check($pin, $user->pin_cajero)
            : hash_equals((string) $user->pin_cajero, $pin);

        if (! $pinValido) {
            // RateLimiter recibe segundos de expiración, no una fecha. Así los
            // tres intentos fallidos se bloquean efectivamente durante 5 min.
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['pin_cajero' => 'El PIN de caja no es válido.']);
        }

        // Migra de forma segura el PIN heredado en texto plano al primer uso correcto.
        if (! Hash::isHashed($user->pin_cajero)) {
            $user->forceFill(['pin_cajero' => $pin])->save();
        }

        RateLimiter::clear($key);
    }
}
