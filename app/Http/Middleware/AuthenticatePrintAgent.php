<?php

namespace App\Http\Middleware;

use App\Models\TerminalPos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePrintAgent
{
    /** Autenticación de máquina: un token aleatorio, revocable y limitado a una terminal. */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! is_string($token) || strlen($token) < 40) {
            return response()->json(['message' => 'Credencial de agente no válida.'], 401);
        }

        $terminal = TerminalPos::withoutGlobalScopes()
            ->where('token_agente_hash', hash('sha256', $token))
            ->where('activo', true)
            ->first();

        if (! $terminal) {
            return response()->json(['message' => 'El agente no está vinculado o fue revocado.'], 401);
        }

        $request->attributes->set('terminal_pos', $terminal);

        return $next($request);
    }
}
