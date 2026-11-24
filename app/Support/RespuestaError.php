<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Único constructor del formato de error. Todo error de la API pasa por aquí. */
final class RespuestaError
{
    public static function json(Request $request, int $status, string $codigo, string $mensaje, array $detalles = []): JsonResponse
    {
        return response()->json([
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'detalles' => $detalles,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'ruta' => '/' . ltrim($request->path(), '/'),
        ], $status);
    }
}
