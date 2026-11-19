<?php

namespace App\Services\Impresion;

use App\Models\CodigoVinculacionAgente;
use App\Models\TerminalPos;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GestionarVinculacionAgenteService
{
    /** El código de seis dígitos vive diez minutos y se guarda únicamente como hash. */
    public function crearCodigo(TerminalPos $terminal, User $usuario): string
    {
        $codigo = (string) random_int(100000, 999999);

        CodigoVinculacionAgente::withoutGlobalScopes()
            ->where('id_terminal', $terminal->id_terminal)
            ->whereNull('usado_en')
            ->where('expira_en', '>', now())
            ->update(['expira_en' => now()]);

        CodigoVinculacionAgente::create([
            'id_terminal' => $terminal->id_terminal,
            'id_sucursal' => $terminal->id_sucursal,
            'id_usuario_creador' => $usuario->id_usuario,
            'codigo_hash' => hash('sha256', $codigo),
            'expira_en' => now()->addMinutes(10),
        ]);

        return $codigo;
    }

    /** Retorna el secreto solo para la respuesta de vínculo; la BD conserva su hash. */
    public function confirmar(string $codigo, string $identificadorAgente, ?string $version): array
    {
        return DB::transaction(function () use ($codigo, $identificadorAgente, $version): array {
            $vinculacion = CodigoVinculacionAgente::withoutGlobalScopes()
                ->where('codigo_hash', hash('sha256', $codigo))
                ->whereNull('usado_en')
                ->where('expira_en', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $vinculacion) {
                throw ValidationException::withMessages(['codigo' => 'El código de vinculación no es válido, ya fue utilizado o expiró.']);
            }

            $terminal = TerminalPos::withoutGlobalScopes()->lockForUpdate()->findOrFail($vinculacion->id_terminal);
            $token = Str::random(64);

            $terminal->update([
                'token_agente_hash' => hash('sha256', $token),
                'token_agente_ultimo4' => substr($token, -4),
                'identificador_equipo' => $identificadorAgente,
                'version_agente' => $version,
                'vinculado_en' => now(),
                'ultimo_contacto_en' => now(),
            ]);
            $vinculacion->update(['usado_en' => now(), 'identificador_agente' => $identificadorAgente]);

            return ['token' => $token, 'terminal' => $terminal];
        });
    }

    public function revocar(TerminalPos $terminal): void
    {
        $terminal->update([
            'token_agente_hash' => null,
            'token_agente_ultimo4' => null,
            'vinculado_en' => null,
            'ultimo_contacto_en' => null,
            'version_agente' => null,
        ]);
    }
}
