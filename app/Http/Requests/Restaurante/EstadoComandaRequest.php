<?php

namespace App\Http\Requests\Restaurante;

use App\Models\Restaurante\Comanda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstadoComandaRequest extends FormRequest
{
    public function rules(): array
    {
        return ['estado' => ['required', Rule::in([Comanda::EN_PREPARACION, Comanda::LISTA, Comanda::ENTREGADA])]];
    }
}
