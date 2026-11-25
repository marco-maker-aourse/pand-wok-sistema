<?php

namespace App\Http\Middleware;

use App\Models\Sucursal;
use App\Models\User;
use App\Services\Configuracion\DatosGeneralesService;
use App\Services\External\DocumentLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $organizationData = null;

        $organization = function () use ($request, &$organizationData) {
            if ($organizationData !== null) {
                return $organizationData;
            }

            if (app()->environment('testing')) {
                return null;
            }

            return $organizationData = app(DatosGeneralesService::class)->identidad();
        };

        // Consulta directa a seguridad.rol_permisos y seguridad.permisos
        $permisos = [];
        $user = $request->user();

        if ($user && $user->id_rol) {
            $permisos = DB::table('seguridad.rol_permisos as rp')
                ->join('seguridad.permisos as p', 'p.id_permiso', '=', 'rp.id_permiso')
                ->where('rp.id_rol', $user->id_rol)
                ->where('p.estado', true)
                ->pluck('p.codigo')
                ->toArray();
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id_usuario'  => $user->id_usuario,
                    'id_rol'      => $user->id_rol,
                    'nombres'     => $user->nombres,
                    'email'       => $user->email,
                    'rol_nombre'  => $user->rol?->nombre ?? ($user->id_rol == 3 ? 'ALMACENERO' : ($user->id_rol == 2 ? 'CAJERO' : 'Administrador')),
                    'permissions' => $permisos,
                    'permisos'    => $permisos,
                ] : null,
                'permissions' => $permisos,
                'permisos'    => $permisos,
            ],
            'organization' => $organization,
            'empresa'      => $organization,
            // Contexto multisucursal: sucursal de trabajo y sucursales a las que puede cambiar.
            'sucursal' => fn () => $user && ! app()->environment('testing') ? $this->contextoSucursal($user) : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                // Se muestra una sola respuesta después de generar el código temporal.
                'codigo_vinculacion_agente' => fn () => $request->session()->get('codigo_vinculacion_agente'),
            ],
            'features' => [
                'assistant' => false,
                'document_lookup' => [
                    'dni' => app(DocumentLookupService::class)->isConfigured('dni'),
                    'ruc' => app(DocumentLookupService::class)->isConfigured('ruc'),
                ],
            ],
        ]);
    }

    private function contextoSucursal(User $user): array
    {
        $disponibles = $user->sucursalesDisponibles();
        $activaId = $user->idSucursalActiva();
        $activa = $disponibles->firstWhere('id_sucursal', $activaId)
            ?? Sucursal::query()->find($activaId, ['id_sucursal', 'nombre', 'codigo_establecimiento']);

        return [
            'activa' => $activa?->only(['id_sucursal', 'nombre', 'codigo_establecimiento']),
            'disponibles' => $disponibles->map->only(['id_sucursal', 'nombre'])->values(),
            'puede_cambiar' => $disponibles->count() > 1,
        ];
    }
}