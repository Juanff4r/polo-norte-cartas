<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de la API con su código HTTP y su código de negocio.
 * El manejador global (bootstrap/app.php) la convierte al formato único de error.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $codigo,
        string $mensaje,
        public readonly array $detalles = [],
    ) {
        parent::__construct($mensaje);
    }

    public static function peticionInvalida(array $detalles): self
    {
        return new self(400, 'PETICION_INVALIDA', 'La petición tiene campos inválidos', $detalles);
    }

    public static function noAutenticado(string $codigo, string $mensaje): self
    {
        return new self(401, $codigo, $mensaje);
    }

    public static function rolInsuficiente(string $rol): self
    {
        return new self(403, 'ROL_INSUFICIENTE', "El rol {$rol} no tiene permiso para esta operación");
    }

    public static function noEncontrada(int $id): self
    {
        return new self(404, 'RECURSO_NO_ENCONTRADO', "No existe la carta con id {$id}");
    }

    public static function conflicto(string $codigo, string $mensaje): self
    {
        return new self(409, $codigo, $mensaje);
    }

    public static function reglaNegocio(string $codigo, string $mensaje): self
    {
        return new self(422, $codigo, $mensaje);
    }

    public static function servicioNoDisponible(string $mensaje, array $detalles = []): self
    {
        return new self(503, 'SERVICIO_EXTERNO_NO_DISPONIBLE', $mensaje, $detalles);
    }
}
