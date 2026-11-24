<?php

/**
 * Pruebas de la lógica de dominio SIN Laravel: validador, servicio, repositorio,
 * presenter y adaptador (este último contra el mock en 127.0.0.1:8081).
 *   php tests/run.php
 */

spl_autoload_register(function (string $clase) {
    if (str_starts_with($clase, 'App\\')) {
        $archivo = __DIR__ . '/../app/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
        if (is_file($archivo)) {
            require $archivo;
        }
    }
});

use App\Adapters\ListaBuenosTraviesosAdapter;
use App\Contracts\ComportamientoGateway;
use App\Enums\Comportamiento;
use App\Enums\EstadoCarta;
use App\Exceptions\ApiException;
use App\Exceptions\ServicioExternoNoDisponibleException;
use App\Repositories\CartaRepository;
use App\Services\CartaService;
use App\Support\CartaPresenter;
use App\Support\CartaValidator;
use App\Support\Reloj;

$ok = 0;
$fallos = 0;

function prueba(string $nombre, callable $fn): void
{
    global $ok, $fallos;
    try {
        $fn();
        $ok++;
        echo "  ✔ {$nombre}\n";
    } catch (Throwable $e) {
        $fallos++;
        echo "  ✘ {$nombre}\n      " . $e->getMessage() . "\n";
    }
}

function afirma(bool $condicion, string $mensaje = 'afirmación falsa'): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function lanza(int $status, string $codigo, callable $fn): void
{
    try {
        $fn();
    } catch (ApiException $e) {
        afirma($e->status === $status, "esperaba {$status}, fue {$e->status} ({$e->codigo})");
        afirma($e->codigo === $codigo, "esperaba {$codigo}, fue {$e->codigo}");

        return;
    }
    throw new RuntimeException("esperaba ApiException {$status} {$codigo}, no lanzó nada");
}

final class GatewayFalso implements ComportamientoGateway
{
    public function __construct(public ?Comportamiento $respuesta = Comportamiento::BUENO, public bool $cae = false)
    {
    }

    public function consultar(string $nombre, string $ciudad): Comportamiento
    {
        if ($this->cae) {
            throw new ServicioExternoNoDisponibleException('Lista en mantenimiento');
        }

        return $this->respuesta;
    }
}

function nuevoServicio(GatewayFalso $gw): CartaService
{
    $ruta = sys_get_temp_dir() . '/cartas_test_' . bin2hex(random_bytes(4)) . '.json';

    return new CartaService(new CartaRepository($ruta), $gw);
}

$valida = ['remitente' => 'Valentina Pérez', 'edad' => 9, 'ciudad' => 'Guayaquil', 'regalos' => ['Bici', 'Libro']];
$hoy = new DateTimeImmutable('2026-11-23 12:00', new DateTimeZone(Reloj::ZONA));

echo "Validador (400)\n";
prueba('carta válida pasa y se normaliza', function () use ($valida) {
    $r = CartaValidator::carta(['remitente' => '  Ana  '] + $valida);
    afirma($r['remitente'] === 'Ana');
});
foreach ([
    'remitente de 1 carácter' => ['remitente' => 'A'],
    'remitente de 61 caracteres' => ['remitente' => str_repeat('a', 61)],
    'edad 0' => ['edad' => 0],
    'edad 18' => ['edad' => 18],
    'edad como texto' => ['edad' => '7'],
    'edad decimal' => ['edad' => 7.5],
    'sin ciudad' => ['ciudad' => null],
    'regalos vacío' => ['regalos' => []],
    'regalos 6 elementos' => ['regalos' => ['a', 'b', 'c', 'd', 'e', 'f']],
    'regalos duplicados (sin importar mayúsculas)' => ['regalos' => ['Bici', 'bici']],
    'regalos no es lista' => ['regalos' => ['x' => 'Bici']],
    'regalo que no es texto' => ['regalos' => ['Bici', 5]],
] as $nombre => $cambio) {
    prueba("rechaza {$nombre}", fn () => lanza(400, 'PETICION_INVALIDA', fn () => CartaValidator::carta($cambio + $valida)));
}
prueba('acepta 5 regalos y edad 1 y 17', function () use ($valida) {
    CartaValidator::carta(['regalos' => ['a', 'b', 'c', 'd', 'e'], 'edad' => 1] + $valida);
    CartaValidator::carta(['edad' => 17] + $valida);
});
prueba('estado inválido → 400', fn () => lanza(400, 'PETICION_INVALIDA', fn () => CartaValidator::estado(['estado' => 'VOLANDO'])));
prueba('filtro estado inválido → 400', fn () => lanza(400, 'PETICION_INVALIDA', fn () => CartaValidator::filtros('XX', null)));

echo "\nServicio: creación y fecha límite\n";
prueba('nace RECIBIDA con PENDIENTE_VERIFICACION y fecha UTC', function () use ($valida, $hoy) {
    $c = nuevoServicio(new GatewayFalso())->crear($valida, $hoy);
    afirma($c['estado'] === 'RECIBIDA' && $c['comportamiento'] === 'PENDIENTE_VERIFICACION');
    afirma(str_ends_with($c['fechaRecepcion'], 'Z') && $c['id'] === 1);
});
prueba('25 de diciembre → 422', function () use ($valida) {
    $f = new DateTimeImmutable('2026-12-25 00:30', new DateTimeZone(Reloj::ZONA));
    lanza(422, 'FECHA_LIMITE_SUPERADA', fn () => nuevoServicio(new GatewayFalso())->crear($valida, $f));
});
prueba('24 de diciembre 23:59 todavía se acepta', function () use ($valida) {
    $f = new DateTimeImmutable('2026-12-24 23:59', new DateTimeZone(Reloj::ZONA));
    nuevoServicio(new GatewayFalso())->crear($valida, $f);
});
prueba('Reloj respeta la fecha simulada solo si está permitida', function () {
    afirma(Reloj::ahora('2026-12-25', true)->format('Y-m-d') === '2026-12-25');
    afirma(Reloj::ahora('2026-12-25', false)->format('Y-m-d') !== '2026-12-25');
});

echo "\nServicio: transiciones (409) y SOAP\n";
prueba('RECIBIDA → ENTREGADA es 409', function () use ($valida, $hoy) {
    $s = nuevoServicio(new GatewayFalso());
    $c = $s->crear($valida, $hoy);
    lanza(409, 'TRANSICION_INVALIDA', fn () => $s->cambiarEstado($c['id'], EstadoCarta::ENTREGADA));
});
prueba('aprobar con BUENO → APROBADA', function () use ($valida, $hoy) {
    $s = nuevoServicio(new GatewayFalso(Comportamiento::BUENO));
    $c = $s->crear($valida, $hoy);
    $r = $s->cambiarEstado($c['id'], EstadoCarta::APROBADA);
    afirma($r['estado'] === 'APROBADA' && $r['comportamiento'] === 'BUENO');
});
prueba('aprobar con TRAVIESO → RECHAZADA', function () use ($valida, $hoy) {
    $s = nuevoServicio(new GatewayFalso(Comportamiento::TRAVIESO));
    $c = $s->crear($valida, $hoy);
    $r = $s->cambiarEstado($c['id'], EstadoCarta::APROBADA);
    afirma($r['estado'] === 'RECHAZADA' && $r['comportamiento'] === 'TRAVIESO');
});
prueba('SOAP caído → 503, sigue RECIBIDA y PENDIENTE_VERIFICACION', function () use ($valida, $hoy) {
    $s = nuevoServicio(new GatewayFalso(cae: true));
    $c = $s->crear($valida, $hoy);
    lanza(503, 'SERVICIO_EXTERNO_NO_DISPONIBLE', fn () => $s->cambiarEstado($c['id'], EstadoCarta::APROBADA));
    $despues = $s->obtener($c['id']);
    afirma($despues['estado'] === 'RECIBIDA' && $despues['comportamiento'] === 'PENDIENTE_VERIFICACION');
});
prueba('RECHAZADA es estado final', function () use ($valida, $hoy) {
    $s = nuevoServicio(new GatewayFalso(Comportamiento::TRAVIESO));
    $c = $s->crear($valida, $hoy);
    $s->cambiarEstado($c['id'], EstadoCarta::APROBADA);
    lanza(409, 'TRANSICION_INVALIDA', fn () => $s->cambiarEstado($c['id'], EstadoCarta::APROBADA));
});
prueba('flujo completo hasta ENTREGADA y luego es intocable', function () use ($valida, $hoy) {
    $s = nuevoServicio(new GatewayFalso());
    $c = $s->crear($valida, $hoy);
    foreach ([EstadoCarta::APROBADA, EstadoCarta::EN_PRODUCCION, EstadoCarta::ENTREGADA] as $e) {
        $s->cambiarEstado($c['id'], $e);
    }
    lanza(409, 'CARTA_ENTREGADA', fn () => $s->actualizar($c['id'], $valida));
    lanza(409, 'CARTA_ENTREGADA', fn () => $s->eliminar($c['id']));
    lanza(409, 'TRANSICION_INVALIDA', fn () => $s->cambiarEstado($c['id'], EstadoCarta::RECIBIDA));
});

echo "\nServicio: CRUD\n";
prueba('editar solo en RECIBIDA', function () use ($valida, $hoy) {
    $s = nuevoServicio(new GatewayFalso());
    $c = $s->crear($valida, $hoy);
    $r = $s->actualizar($c['id'], ['ciudad' => 'Daule'] + $valida);
    afirma($r['ciudad'] === 'Daule' && $r['estado'] === 'RECIBIDA');
    $s->cambiarEstado($c['id'], EstadoCarta::APROBADA);
    lanza(409, 'ESTADO_NO_EDITABLE', fn () => $s->actualizar($c['id'], $valida));
});
prueba('inexistente → 404', function () {
    $s = nuevoServicio(new GatewayFalso());
    lanza(404, 'RECURSO_NO_ENCONTRADO', fn () => $s->obtener(999));
    lanza(404, 'RECURSO_NO_ENCONTRADO', fn () => $s->eliminar(999));
    lanza(404, 'RECURSO_NO_ENCONTRADO', fn () => $s->cambiarEstado(999, EstadoCarta::APROBADA));
});
prueba('eliminar una carta RECIBIDA funciona', function () use ($valida, $hoy) {
    $s = nuevoServicio(new GatewayFalso());
    $c = $s->crear($valida, $hoy);
    $s->eliminar($c['id']);
    lanza(404, 'RECURSO_NO_ENCONTRADO', fn () => $s->obtener($c['id']));
});
prueba('filtros por estado y ciudad (ciudad sin distinguir mayúsculas)', function () use ($valida, $hoy) {
    $s = nuevoServicio(new GatewayFalso());
    $s->crear($valida, $hoy);
    $b = $s->crear(['ciudad' => 'Quito'] + $valida, $hoy);
    $s->cambiarEstado($b['id'], EstadoCarta::APROBADA);
    afirma(count($s->listar(null, null)) === 2);
    afirma(count($s->listar(EstadoCarta::APROBADA, null)) === 1);
    afirma(count($s->listar(null, 'guayaquil')) === 1);
    afirma(count($s->listar(EstadoCarta::APROBADA, 'Guayaquil')) === 0);
});
prueba('los ids no se reutilizan tras eliminar', function () use ($valida, $hoy) {
    $s = nuevoServicio(new GatewayFalso());
    $a = $s->crear($valida, $hoy);
    $s->eliminar($a['id']);
    afirma($s->crear($valida, $hoy)['id'] === 2);
});

echo "\nPresenter (extra A)\n";
prueba('listado público oculta edad y abrevia apellido', function () use ($valida, $hoy) {
    $c = nuevoServicio(new GatewayFalso())->crear($valida, $hoy);
    $p = CartaPresenter::publico($c);
    afirma(!array_key_exists('edad', $p) && $p['remitente'] === 'Valentina P.');
    $p4 = CartaPresenter::publico(['remitente' => 'Juan Fernando Carchi Ronquillo'] + $c);
    afirma($p4['remitente'] === 'Juan R.');
    afirma(array_key_exists('edad', CartaPresenter::completo($c)));
});

echo "\nAdaptador SOAP (contra el mock en 127.0.0.1:8081)\n";
$mockVivo = @fsockopen('127.0.0.1', 8081, $en, $es, 1);
if (!$mockVivo) {
    echo "  (omitido: no hay mock en 8081)\n";
} else {
    fclose($mockVivo);
    $adaptador = new ListaBuenosTraviesosAdapter('http://127.0.0.1:8081', 3.0, 2, backoffMs: 50);

    prueba('BUENO', fn () => afirma($adaptador->consultar('Valentina', 'Guayaquil') === Comportamiento::BUENO));
    prueba('TRAVIESO', fn () => afirma($adaptador->consultar('Travieso Tomás', 'Quito') === Comportamiento::TRAVIESO));
    prueba('escapa XML en el nombre (no rompe la petición)', fn () => afirma($adaptador->consultar('<a&b>', 'X') === Comportamiento::BUENO));
    prueba('Fault ("Grinch") → ServicioExternoNoDisponibleException', function () use ($adaptador) {
        try {
            $adaptador->consultar('Grinch', 'Polo');
        } catch (ServicioExternoNoDisponibleException $e) {
            afirma(str_contains($e->getMessage(), 'Lista en mantenimiento'));

            return;
        }
        throw new RuntimeException('no lanzó');
    });
    prueba('timeout: no pasa de ~3 s por intento y lanza la excepción de dominio', function () {
        $a = new ListaBuenosTraviesosAdapter('http://127.0.0.1:8081', 1.0, 1);
        $t = microtime(true);
        try {
            $a->consultar('Lento', 'X');
        } catch (ServicioExternoNoDisponibleException) {
            afirma(microtime(true) - $t < 2.5, 'tardó demasiado');

            return;
        }
        throw new RuntimeException('no lanzó');
    });
    prueba('servicio apagado (puerto cerrado) → excepción de dominio, no error genérico', function () {
        $a = new ListaBuenosTraviesosAdapter('http://127.0.0.1:1', 1.0, 2, backoffMs: 10);
        try {
            $a->consultar('Ana', 'X');
        } catch (ServicioExternoNoDisponibleException) {
            return;
        }
        throw new RuntimeException('no lanzó');
    });
}

echo "\n" . ($fallos === 0 ? "TODO OK" : "HAY FALLOS") . " — {$ok} pasaron, {$fallos} fallaron\n";
exit($fallos === 0 ? 0 : 1);
