<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seguridad por API key en el header Authorization.
 *   sin token o token desconocido -> 401
 *   token válido pero rol distinto -> 403
 * Los tokens vienen de variables de entorno (config/polonorte.php), nunca del repositorio.
 */
class RequiereRol
{
    public function handle(Request $request, Closure $next, string ...$rolesPermitidos): Response
    {
        $token = $this->extraerToken($request);

        if ($token === null) {
            throw ApiException::noAutenticado('TOKEN_AUSENTE', 'Falta el header Authorization con el token');
        }

        $rol = $this->rolDe($token);

        if ($rol === null) {
            throw ApiException::noAutenticado('TOKEN_INVALIDO', 'El token no es válido');
        }

        if (!in_array($rol, $rolesPermitidos, true)) {
            throw ApiException::rolInsuficiente($rol);
        }

        $request->attributes->set('rol', $rol);

        return $next($request);
    }

    private function extraerToken(Request $request): ?string
    {
        $cabecera = trim((string) $request->header('Authorization', ''));
        if ($cabecera === '') {
            return null;
        }

        $token = trim(preg_replace('/^Bearer\s+/i', '', $cabecera));

        return $token === '' ? null : $token;
    }

    private function rolDe(string $token): ?string
    {
        foreach ((array) config('polonorte.tokens') as $rol => $esperado) {
            // Un token sin configurar (vacío) jamás da acceso.
            if (is_string($esperado) && $esperado !== '' && hash_equals($esperado, $token)) {
                return $rol;
            }
        }

        return null;
    }
}
