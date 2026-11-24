<?php

namespace App\Support;

/** Cómo se muestra una carta hacia afuera. Sin reglas de negocio. */
final class CartaPresenter
{
    public static function completo(array $c): array
    {
        return [
            'id' => $c['id'],
            'remitente' => $c['remitente'],
            'edad' => $c['edad'],
            'ciudad' => $c['ciudad'],
            'regalos' => $c['regalos'],
            'estado' => $c['estado'],
            'comportamiento' => $c['comportamiento'],
            'fechaRecepcion' => $c['fechaRecepcion'],
        ];
    }

    /**
     * Extra A (minimización de datos de menores) para el listado público:
     * nombre + inicial del último apellido, y sin edad.
     */
    public static function publico(array $c): array
    {
        $p = self::completo($c);
        unset($p['edad']);
        $p['remitente'] = self::abreviar($p['remitente']);

        return $p;
    }

    private static function abreviar(string $nombre): string
    {
        $partes = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY);
        if (count($partes) < 2) {
            return $partes[0] ?? '';
        }

        return $partes[0] . ' ' . mb_strtoupper(mb_substr(end($partes), 0, 1)) . '.';
    }
}
