<?php

namespace App\Services\Clientes;

use DomainException;

/** La consulta de DNI/RUC no está disponible: hace falta que el cajero escriba el nombre. */
class RequiereNombreException extends DomainException {}
