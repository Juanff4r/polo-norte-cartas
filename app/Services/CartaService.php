<?php

namespace App\Services;

use App\Contracts\ComportamientoGateway;
use App\Enums\Comportamiento;
use App\Enums\EstadoCarta;
use App\Exceptions\ApiException;
use App\Exceptions\ServicioExternoNoDisponibleException;
use App\Repositories\CartaRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * AQUÍ viven TODAS las reglas de negocio de las cartas.
 * El controlador solo valida formato y delega; el repositorio solo guarda.
 */
class CartaService
{
    public function __construct(
        private readonly CartaRepository $cartas,
        private readonly ComportamientoGateway $lista,
    ) {
    }

    /** Regla 1: no se aceptan cartas después del 24 de diciembre (422). Regla 2: nace RECIBIDA. */
    public function crear(array $datos, DateTimeImmutable $ahora): array
    {
        if ($this->superaFechaLimite($ahora)) {
            throw ApiException::reglaNegocio(
                'FECHA_LIMITE_SUPERADA',
                'No se aceptan cartas creadas después del 24 de diciembre'
            );
        }

        return $this->cartas->insertar([
            'remitente' => $datos['remitente'],
            'edad' => $datos['edad'],
            'ciudad' => $datos['ciudad'],
            'regalos' => $datos['regalos'],
            'estado' => EstadoCarta::RECIBIDA->value,
            'comportamiento' => Comportamiento::PENDIENTE_VERIFICACION->value,
            'fechaRecepcion' => $ahora->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ]);
    }

    public function listar(?EstadoCarta $estado, ?string $ciudad): array
    {
        return array_values(array_filter($this->cartas->todas(), function (array $c) use ($estado, $ciudad) {
            if ($estado !== null && $c['estado'] !== $estado->value) {
                return false;
            }
            if ($ciudad !== null && mb_strtolower($c['ciudad']) !== mb_strtolower($ciudad)) {
                return false;
            }

            return true;
        }));
    }

    public function obtener(int $id): array
    {
        return $this->cartas->buscar($id) ?? throw ApiException::noEncontrada($id);
    }

    /** Solo se edita en RECIBIDA. ENTREGADA es intocable (regla 5). */
    public function actualizar(int $id, array $datos): array
    {
        $carta = $this->obtener($id);
        $this->exigirEditable($carta);

        foreach (['remitente', 'edad', 'ciudad', 'regalos'] as $campo) {
            $carta[$campo] = $datos[$campo];
        }

        return $this->cartas->actualizar($carta);
    }

    /** Regla 5: una carta ENTREGADA no se puede eliminar. */
    public function eliminar(int $id): void
    {
        $carta = $this->obtener($id);
        $this->exigirNoEntregada($carta, 'eliminar');
        $this->cartas->eliminar($id);
    }

    /** Reglas 3 y 4. */
    public function cambiarEstado(int $id, EstadoCarta $destino): array
    {
        $carta = $this->obtener($id);
        $actual = EstadoCarta::from($carta['estado']);

        if (!$actual->puedePasarA($destino)) {
            throw ApiException::conflicto(
                'TRANSICION_INVALIDA',
                "No se puede pasar de {$actual->value} a {$destino->value}"
            );
        }

        if ($destino === EstadoCarta::APROBADA) {
            return $this->aprobar($carta);
        }

        $carta['estado'] = $destino->value;

        return $this->cartas->actualizar($carta);
    }

    /**
     * Regla 4: aprobar exige consultar la Lista. TRAVIESO => RECHAZADA.
     * Fallo del SOAP => la carta sigue en RECIBIDA con PENDIENTE_VERIFICACION y se responde 503.
     */
    private function aprobar(array $carta): array
    {
        try {
            $veredicto = $this->lista->consultar($carta['remitente'], $carta['ciudad']);
        } catch (ServicioExternoNoDisponibleException $e) {
            $carta['comportamiento'] = Comportamiento::PENDIENTE_VERIFICACION->value;
            $this->cartas->actualizar($carta); // estado NO cambia: sigue RECIBIDA

            throw ApiException::servicioNoDisponible(
                'La Lista de Buenos y Traviesos no está disponible; la carta sigue en RECIBIDA',
                [$e->getMessage()]
            );
        }

        $carta['comportamiento'] = $veredicto->value;
        $carta['estado'] = $veredicto === Comportamiento::TRAVIESO
            ? EstadoCarta::RECHAZADA->value
            : EstadoCarta::APROBADA->value;

        return $this->cartas->actualizar($carta);
    }

    /** "Después del 24 de diciembre" = del 25 al 31. El 24 completo todavía se acepta. */
    private function superaFechaLimite(DateTimeImmutable $ahora): bool
    {
        return (int) $ahora->format('n') === 12 && (int) $ahora->format('j') > 24;
    }

    private function exigirEditable(array $carta): void
    {
        $this->exigirNoEntregada($carta, 'modificar');
        if ($carta['estado'] !== EstadoCarta::RECIBIDA->value) {
            throw ApiException::conflicto(
                'ESTADO_NO_EDITABLE',
                "Solo se puede editar una carta en estado RECIBIDA (estado actual: {$carta['estado']})"
            );
        }
    }

    private function exigirNoEntregada(array $carta, string $accion): void
    {
        if ($carta['estado'] === EstadoCarta::ENTREGADA->value) {
            throw ApiException::conflicto('CARTA_ENTREGADA', "Una carta ENTREGADA no se puede {$accion}");
        }
    }
}
