<?php

namespace App\Http\Controllers;

use App\Services\CartaService;
use App\Support\CartaPresenter;
use App\Support\CartaValidator;
use App\Support\Reloj;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Controlador delgado: valida formato (400), delega en CartaService y da forma a la respuesta.
 * Ninguna regla de negocio vive aquí.
 */
class CartaController extends Controller
{
    public function __construct(private readonly CartaService $cartas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        [$estado, $ciudad] = CartaValidator::filtros($request->query('estado'), $request->query('ciudad'));

        $lista = $this->cartas->listar($estado, $ciudad);

        // Listado público: minimización de datos de menores (extra A).
        return response()->json(array_map([CartaPresenter::class, 'publico'], $lista));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = CartaValidator::carta($request->all());
        $ahora = Reloj::ahora(
            $request->header('X-Fecha-Simulada'),
            (bool) config('polonorte.permitir_fecha_simulada')
        );

        $carta = $this->cartas->crear($datos, $ahora);

        return response()
            ->json(CartaPresenter::completo($carta), 201)
            ->header('Location', url('/api/cartas/' . $carta['id']));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(CartaPresenter::completo($this->cartas->obtener($id)));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $datos = CartaValidator::carta($request->all());

        return response()->json(CartaPresenter::completo($this->cartas->actualizar($id, $datos)));
    }

    public function destroy(int $id): Response
    {
        $this->cartas->eliminar($id);

        return response()->noContent();
    }

    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $destino = CartaValidator::estado($request->all());

        return response()->json(CartaPresenter::completo($this->cartas->cambiarEstado($id, $destino)));
    }
}
