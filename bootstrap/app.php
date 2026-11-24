<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\RequiereRol;
use App\Support\RespuestaError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['rol' => RequiereRol::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Manejador global: TODO error sale con el mismo formato JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);
        $exceptions->dontReport(ApiException::class);

        $exceptions->render(fn (ApiException $e, Request $r) => RespuestaError::json(
            $r, $e->status, $e->codigo, $e->getMessage(), $e->detalles
        ));

        $exceptions->render(fn (NotFoundHttpException $e, Request $r) => RespuestaError::json(
            $r, 404, 'RECURSO_NO_ENCONTRADO', 'El recurso solicitado no existe'
        ));

        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $r) => RespuestaError::json(
            $r, 405, 'METODO_NO_PERMITIDO', 'Método HTTP no permitido para esta ruta'
        ));

        $exceptions->render(fn (HttpExceptionInterface $e, Request $r) => RespuestaError::json(
            $r, $e->getStatusCode(), 'ERROR_HTTP', 'La petición no pudo procesarse'
        ));

        // Red de seguridad: nunca se filtra un stack trace al cliente.
        $exceptions->render(fn (\Throwable $e, Request $r) => RespuestaError::json(
            $r, 500, 'ERROR_INTERNO', 'Error interno del servidor'
        ));
    })->create();
