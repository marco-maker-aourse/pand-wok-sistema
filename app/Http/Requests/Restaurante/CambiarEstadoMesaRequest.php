<?php

namespace App\Http\Requests\Restaurante;

use App\Models\Restaurante\Mesa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** OCUPADA no se acepta: solo la asigna una cuenta. */
class CambiarEstadoMesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['estado' => ['required', Rule::in([Mesa::DISPONIBLE, Mesa::RESERVADA, Mesa::FUERA_SERVICIO])]];
    }
}
