<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * RUC peruano: 11 dígitos con un prefijo válido de SUNAT
 * (10, 15, 16, 17 = persona natural; 20 = persona jurídica).
 *
 * Es la regla única para clientes, proveedores y Datos Generales. El dígito
 * verificador (módulo 11) no se exige todavía para no bloquear registros
 * existentes; ver docs de auditoría.
 */
class Ruc implements ValidationRule
{
    public const PATRON = '/^(10|15|16|17|20)\d{9}$/';

    public const MENSAJE = 'El RUC debe tener 11 dígitos y empezar con 10, 15, 16, 17 o 20.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! preg_match(self::PATRON, (string) $value)) {
            $fail(self::MENSAJE);
        }
    }
}
