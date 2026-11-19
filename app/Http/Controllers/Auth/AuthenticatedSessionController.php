<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Sucursales\ContextoSucursalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response { return Inertia::render('Auth/Login'); }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt([...$credentials, 'estado' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Las credenciales no son válidas.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        // Cada inicio de sesión empieza en la sede principal del usuario.
        $request->session()->forget(ContextoSucursalService::CLAVE_SESION);
        $usuario = $request->user();
        $ruta = $usuario->hasPermission('cocina.ver') && ! $usuario->hasPermission('dashboard.ver')
            ? 'restaurante.cocina'
            : 'dashboard';

        return to_route($ruta);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return to_route('login');
    }
}
