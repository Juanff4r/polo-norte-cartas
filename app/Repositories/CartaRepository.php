<?php

namespace App\Repositories;

use RuntimeException;

/**
 * Persistencia en un archivo JSON con bloqueo (flock).
 * Se usa archivo y no un array en memoria porque `php artisan serve` atiende cada
 * petición en un proceso nuevo: un array en memoria se perdería entre peticiones.
 * Esta capa NO tiene reglas de negocio: solo guarda y busca.
 */
class CartaRepository
{
    public function __construct(private readonly string $ruta)
    {
    }

    /** @return array[] ordenadas por id */
    public function todas(): array
    {
        return $this->conBloqueo(fn (array &$d) => array_values($d['cartas']));
    }

    public function buscar(int $id): ?array
    {
        return $this->conBloqueo(fn (array &$d) => $d['cartas'][$id] ?? null);
    }

    /** Asigna el id y guarda. */
    public function insertar(array $carta): array
    {
        return $this->conBloqueo(function (array &$d) use ($carta) {
            $carta['id'] = $d['siguienteId']++;
            $d['cartas'][$carta['id']] = $carta;

            return $carta;
        });
    }

    public function actualizar(array $carta): array
    {
        return $this->conBloqueo(function (array &$d) use ($carta) {
            $d['cartas'][$carta['id']] = $carta;

            return $carta;
        });
    }

    public function eliminar(int $id): void
    {
        $this->conBloqueo(function (array &$d) use ($id) {
            unset($d['cartas'][$id]);
        });
    }

    private function conBloqueo(callable $operacion): mixed
    {
        $dir = dirname($this->ruta);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear {$dir}");
        }

        $fh = fopen($this->ruta, 'c+');
        if ($fh === false) {
            throw new RuntimeException("No se pudo abrir {$this->ruta}");
        }

        try {
            flock($fh, LOCK_EX);
            $crudo = stream_get_contents($fh);
            $datos = $crudo ? json_decode($crudo, true) : null;
            if (!is_array($datos) || !isset($datos['cartas'], $datos['siguienteId'])) {
                $datos = ['siguienteId' => 1, 'cartas' => []];
            }
            $original = $datos;

            $resultado = $operacion($datos);

            if ($datos !== $original) {
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                fflush($fh);
            }

            return $resultado;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
