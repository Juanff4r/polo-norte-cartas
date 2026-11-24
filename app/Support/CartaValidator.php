<?php

namespace App\Support;

use App\Enums\EstadoCarta;
use App\Exceptions\ApiException;

/**
 * Validación de FORMATO (400). Las reglas de negocio (409/422) viven en CartaService.
 * Sin dependencias de Laravel: se puede probar con PHP puro.
 */
final class CartaValidator
{
    /** @return array{remitente:string,edad:int,ciudad:string,regalos:string[]} */
    public static function carta(array $in): array
    {
        $errores = [];

        $remitente = $in['remitente'] ?? null;
        if (!is_string($remitente) || !self::entre(mb_strlen(trim($remitente)), 2, 60)) {
            $errores[] = ['campo' => 'remitente', 'mensaje' => 'Obligatorio, texto de 2 a 60 caracteres'];
        }

        $edad = $in['edad'] ?? null;
        if (!is_int($edad) || !self::entre($edad, 1, 17)) {
            $errores[] = ['campo' => 'edad', 'mensaje' => 'Obligatorio, entero entre 1 y 17'];
        }

        $ciudad = $in['ciudad'] ?? null;
        if (!is_string($ciudad) || !self::entre(mb_strlen(trim($ciudad)), 1, 80)) {
            $errores[] = ['campo' => 'ciudad', 'mensaje' => 'Obligatorio, texto de 1 a 80 caracteres'];
        }

        $regalos = $in['regalos'] ?? null;
        if (!is_array($regalos) || !array_is_list($regalos) || !self::entre(count($regalos), 1, 5)) {
            $errores[] = ['campo' => 'regalos', 'mensaje' => 'Obligatorio, lista de 1 a 5 textos'];
        } else {
            $limpios = [];
            foreach ($regalos as $r) {
                if (!is_string($r) || !self::entre(mb_strlen(trim($r)), 1, 100)) {
                    $errores[] = ['campo' => 'regalos', 'mensaje' => 'Cada regalo debe ser un texto de 1 a 100 caracteres'];
                    break;
                }
                $limpios[] = trim($r);
            }
            if (count(array_unique(array_map('mb_strtolower', $limpios))) !== count($limpios)) {
                $errores[] = ['campo' => 'regalos', 'mensaje' => 'No se permiten regalos duplicados'];
            }
        }

        if ($errores) {
            throw ApiException::peticionInvalida($errores);
        }

        return [
            'remitente' => trim($remitente),
            'edad' => $edad,
            'ciudad' => trim($ciudad),
            'regalos' => array_map('trim', $regalos),
        ];
    }

    public static function estado(array $in): EstadoCarta
    {
        $valor = $in['estado'] ?? null;
        $estado = is_string($valor) ? EstadoCarta::tryFrom($valor) : null;

        if ($estado === null) {
            throw ApiException::peticionInvalida([[
                'campo' => 'estado',
                'mensaje' => 'Obligatorio, uno de: ' . implode(', ', EstadoCarta::valores()),
            ]]);
        }

        return $estado;
    }

    /** @return array{0:?EstadoCarta,1:?string} */
    public static function filtros(mixed $estado, mixed $ciudad): array
    {
        $errores = [];
        $estadoEnum = null;

        if ($estado !== null) {
            $estadoEnum = is_string($estado) ? EstadoCarta::tryFrom($estado) : null;
            if ($estadoEnum === null) {
                $errores[] = ['campo' => 'estado', 'mensaje' => 'Debe ser uno de: ' . implode(', ', EstadoCarta::valores())];
            }
        }
        if ($ciudad !== null && !is_string($ciudad)) {
            $errores[] = ['campo' => 'ciudad', 'mensaje' => 'Debe ser un texto'];
        }

        if ($errores) {
            throw ApiException::peticionInvalida($errores);
        }

        return [$estadoEnum, $ciudad !== null ? trim($ciudad) : null];
    }

    private static function entre(int $valor, int $min, int $max): bool
    {
        return $valor >= $min && $valor <= $max;
    }
}
