<?php

namespace App\Enums;

/** Veredicto de la Lista de Buenos y Traviesos. Lo llena el sistema, nunca el cliente. */
enum Comportamiento: string
{
    case BUENO = 'BUENO';
    case TRAVIESO = 'TRAVIESO';
    case PENDIENTE_VERIFICACION = 'PENDIENTE_VERIFICACION';
}
