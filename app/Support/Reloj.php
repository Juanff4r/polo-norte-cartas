<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Fuente única de "ahora". La zona es America/Guayaquil: "24 de diciembre" es hora local,
 * no UTC (si no, a las 19:00 del 24 ya se rechazarían cartas).
 * La fecha simulada solo se respeta si PERMITIR_FECHA_SIMULADA=true; existe para poder
 * demostrar el 422 de la regla 1 desde Postman sin esperar a diciembre.
 */
final class Reloj
{
    public const ZONA = 'America/Guayaquil';

    public static function ahora(?string $simulada = null, bool $permitirSimulada = false): DateTimeImmutable
    {
        $zona = new DateTimeZone(self::ZONA);

        if ($permitirSimulada && $simulada !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $simulada)) {
            $f = DateTimeImmutable::createFromFormat('!Y-m-d', $simulada, $zona);
            if ($f !== false && $f->format('Y-m-d') === $simulada) {
                return $f->setTime(12, 0);
            }
        }

        return new DateTimeImmutable('now', $zona);
    }
}
