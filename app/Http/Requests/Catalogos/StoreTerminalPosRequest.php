<?php

namespace App\Http\Requests\Catalogos;

use App\Models\TerminalPos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTerminalPosRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $terminal = $this->route('terminalPos');

        return [
            'codigo' => ['required', 'string', 'max:30', Rule::unique(TerminalPos::class, 'codigo')->ignore($terminal?->id_terminal, 'id_terminal')],
            'nombre' => ['required', 'string', 'max:100'],
            'identificador_equipo' => ['nullable', 'string', 'max:150'],
            'id_impresora' => ['nullable', 'integer'],
            'impresion_automatica' => ['sometimes', 'boolean'],
            'copias_ticket' => ['required', 'integer', 'between:1,5'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
