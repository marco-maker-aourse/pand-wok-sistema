<?php

namespace App\Http\Requests\Restaurante;

use Illuminate\Foundation\Http\FormRequest;

class EnviarComandaRequest extends FormRequest
{
    public function rules(): array
    {
        return ['clave_envio' => ['required', 'alpha_num:ascii', 'min:16', 'max:80']];
    }
}
