<?php

namespace App\Contracts;

use App\Enums\Comportamiento;
use App\Exceptions\ServicioExternoNoDisponibleException;

/**
 * Puerto que el servicio de cartas necesita. No sabe que detrás hay SOAP.
 * Permite probar CartaService con un doble falso.
 */
interface ComportamientoGateway
{
    /**
     * @return Comportamiento solo BUENO o TRAVIESO
     * @throws ServicioExternoNoDisponibleException Fault, timeout o respuesta inválida
     */
    public function consultar(string $nombre, string $ciudad): Comportamiento;
}
