<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lo que el ADAPTADOR lanza cuando el SOAP responde Fault, no responde en 3 s,
 * o devuelve algo ilegible. Es una excepción de dominio: no menciona XML ni HTTP.
 * El servicio decide qué hacer con ella (guardar PENDIENTE_VERIFICACION y responder 503).
 */
class ServicioExternoNoDisponibleException extends RuntimeException
{
}
