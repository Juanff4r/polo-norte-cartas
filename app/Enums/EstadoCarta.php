<?php

namespace App\Enums;

/**
 * Estados de una carta y su máquina de transiciones.
 * La tabla de transiciones vive aquí (dato del dominio); quien la APLICA es CartaService.
 */
enum EstadoCarta: string
{
    case RECIBIDA = 'RECIBIDA';
    case APROBADA = 'APROBADA';
    case EN_PRODUCCION = 'EN_PRODUCCION';
    case ENTREGADA = 'ENTREGADA';
    case RECHAZADA = 'RECHAZADA';

    /** @return EstadoCarta[] */
    public function siguientes(): array
    {
        return match ($this) {
            self::RECIBIDA => [self::APROBADA, self::RECHAZADA],
            self::APROBADA => [self::EN_PRODUCCION],
            self::EN_PRODUCCION => [self::ENTREGADA],
            self::ENTREGADA, self::RECHAZADA => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->siguientes(), true);
    }

    /** @return string[] */
    public static function valores(): array
    {
        return array_map(fn (self $e) => $e->value, self::cases());
    }
}
