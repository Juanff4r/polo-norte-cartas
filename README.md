# 🎅 API de Cartas a Santa y Gestión de Regalos (Escenario A)

Parcial 1 · Desarrollo Basado en Plataformas · Backend en **Laravel (PHP)**.
Escenario resuelto: **A · Cartas a Santa y gestión de regalos**.

Antes de aprobar una carta, la API consulta la **Lista de Buenos y Traviesos**, un servicio SOAP heredado. La API sobrevive cuando ese servicio falla.

---

## 1. Cómo levantar el proyecto

Requisitos: PHP ≥ 8.2 (con `curl`, `dom`, `mbstring`), Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate

# Terminal 1: mock del servicio SOAP (solo si no usas el del docente)
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8081 mock-soap/server.php

# Terminal 2: la API
php artisan serve        # http://127.0.0.1:8000
```

Para usar el mock del docente, cambia `SOAP_LISTA_URL` en `.env` por su endpoint.

**Persistencia:** archivo JSON en `storage/app/cartas.json` (se crea solo; está ignorado por git). Se usa un archivo y no un array en memoria porque `php artisan serve` procesa cada petición en un proceso nuevo y un array se perdería entre peticiones. Para reiniciar los datos, borra ese archivo.

## 2. Variables de entorno

Ver `.env.example`. Las propias del proyecto:

| Variable | Para qué sirve | Valor por defecto |
|---|---|---|
| `TOKEN_ELFO_JEFE` | Token del rol `ELFO_JEFE` (puede borrar y cambiar estado) | `token-elfo-jefe-demo` |
| `TOKEN_ELFO` | Token del rol `ELFO` (autenticado, pero sin permiso → 403) | `token-elfo-demo` |
| `SOAP_LISTA_URL` | Endpoint del servicio SOAP | `http://127.0.0.1:8081` |
| `SOAP_TIMEOUT` | Segundos máximos de espera por intento | `3` |
| `SOAP_INTENTOS` | Intentos totales antes de declarar caído el servicio (backoff exponencial) | `2` |
| `SOAP_LISTA_ACTION` | Valor del header `SOAPAction` | `ConsultarComportamiento` |
| `PERMITIR_FECHA_SIMULADA` | Si es `true`, se acepta el header `X-Fecha-Simulada` (ver §6) | `true` en `.env.example` |

No hay secretos en el repositorio: `.env` está en `.gitignore` y los tokens del `.env.example` son valores de demostración que debes cambiar fuera del examen.

## 3. Usuarios / tokens de prueba

Se envían en el header `Authorization: Bearer <token>`.

| Rol | Token de prueba | Qué puede hacer |
|---|---|---|
| `ELFO_JEFE` | `token-elfo-jefe-demo` | `DELETE /api/cartas/{id}`, `PATCH /api/cartas/{id}/estado` |
| `ELFO` | `token-elfo-demo` | Nada protegido → recibe **403** |
| (sin token) | — | Solo endpoints públicos; en los protegidos recibe **401** |

## 4. Endpoints

| Método | Ruta | Descripción | Seguridad | Respuestas |
|---|---|---|---|---|
| POST | `/api/cartas` | Registrar carta | pública | 201, 400, 422 |
| GET | `/api/cartas` | Listar (`?estado=`, `?ciudad=`) | pública | 200, 400 |
| GET | `/api/cartas/{id}` | Detalle | pública | 200, 404 |
| PUT | `/api/cartas/{id}` | Editar (solo en `RECIBIDA`) | pública | 200, 400, 404, 409 |
| DELETE | `/api/cartas/{id}` | Eliminar | `ELFO_JEFE` | 204, 401, 403, 404, 409 |
| PATCH | `/api/cartas/{id}/estado` | Cambiar estado `{"estado": "APROBADA"}` | `ELFO_JEFE` | 200, 400, 401, 403, 404, 409, 503 |

El listado público devuelve el remitente abreviado (nombre + inicial del último apellido) y **oculta la edad** (extra A, minimización de datos de menores).

### Reglas de negocio y su código HTTP

| Regla | Código |
|---|---|
| Carta creada después del 24 de diciembre | 422 `FECHA_LIMITE_SUPERADA` |
| Transición no permitida (`RECIBIDA→APROBADA\|RECHAZADA`, `APROBADA→EN_PRODUCCION`, `EN_PRODUCCION→ENTREGADA`) | 409 `TRANSICION_INVALIDA` |
| Editar una carta que no está en `RECIBIDA` | 409 `ESTADO_NO_EDITABLE` |
| Modificar o eliminar una carta `ENTREGADA` | 409 `CARTA_ENTREGADA` |
| Al aprobar, la Lista responde `TRAVIESO` | 200, la carta queda `RECHAZADA` |
| Al aprobar, la Lista falla (Fault o > 3 s) | 503 `SERVICIO_EXTERNO_NO_DISPONIBLE`; la carta sigue `RECIBIDA` con `comportamiento = PENDIENTE_VERIFICACION` |

### Formato único de error

Todo error (incluidos 404 de ruta, 405 y cualquier excepción inesperada) sale así:

```json
{
  "codigo": "TRANSICION_INVALIDA",
  "mensaje": "No se puede pasar de RECIBIDA a ENTREGADA",
  "detalles": [],
  "timestamp": "2026-11-23T20:15:00Z",
  "ruta": "/api/cartas/7/estado"
}
```

## 5. Arquitectura

```
Cliente (Postman)
      │  HTTP
      ▼
 routes/api.php ──► Middleware RequiereRol (401 / 403)
      │
      ▼
 CartaController        valida FORMATO (400) y delega
      │
      ▼
 CartaService           ⭐ TODAS las reglas de negocio (404/409/422)
   │        │
   │        └──► ComportamientoGateway (interfaz)
   │                    │
   │                    ▼
   │             ListaBuenosTraviesosAdapter   único lugar que conoce XML
   │                    │   ⚠ aquí se detecta Fault / timeout 3 s / respuesta ilegible
   │                    ▼
   │             ListaBuenosTraviesosService (SOAP)
   ▼
 CartaRepository ──► storage/app/cartas.json

 Manejador global de errores (bootstrap/app.php) ──► formato único JSON
```

| Capa | Archivo | Responsabilidad |
|---|---|---|
| Rutas | `routes/api.php` | Qué URL va a qué método y con qué rol |
| Seguridad | `app/Http/Middleware/RequiereRol.php` | 401 sin token o inválido, 403 con rol distinto |
| Controlador | `app/Http/Controllers/CartaController.php` | Formato de entrada, delegar, dar forma a la respuesta |
| Validación | `app/Support/CartaValidator.php` | Reglas de formato → 400 |
| Servicio | `app/Services/CartaService.php` | Reglas de negocio, transiciones, qué hacer si el SOAP falla |
| Adaptador | `app/Adapters/ListaBuenosTraviesosAdapter.php` | Dominio ↔ XML, timeout, Fault, reintentos |
| Repositorio | `app/Repositories/CartaRepository.php` | Guardar y buscar, sin reglas |
| Errores | `bootstrap/app.php`, `app/Exceptions/ApiException.php` | Convertir cualquier excepción al formato único |

**Dónde ocurre el fallo del SOAP:** el adaptador lo convierte en `ServicioExternoNoDisponibleException`; `CartaService::aprobar()` la captura, guarda `PENDIENTE_VERIFICACION` sin cambiar el estado y lanza `ApiException` 503. La API nunca devuelve 500 por culpa del servicio externo.

Diagrama: `docs/arquitectura.png` *(pendiente de entregar hasta 24 h después del examen)*.

## 6. Decisiones que conviene conocer

- **Zona horaria:** "24 de diciembre" se evalúa en hora de Ecuador (`America/Guayaquil`), no en UTC. `fechaRecepcion` se guarda en UTC.
- **Header `X-Fecha-Simulada: 2026-12-25`:** solo funciona con `PERMITIR_FECHA_SIMULADA=true`. Existe para demostrar el 422 de la fecha límite sin esperar a diciembre. **Debe estar en `false` en cualquier entorno real.**
- **Reintento con backoff** (extra): 2 intentos, 200 ms de espera entre ellos. En el peor caso de timeout, la petición tarda unos 6 s.
- **Mock propio del SOAP** (`mock-soap/server.php`): el nombre `Grinch` provoca un Fault, un nombre que contenga `Travies` responde `TRAVIESO`, uno que contenga `Lento` tarda 6 s (prueba de timeout). Cualquier otro responde `BUENO`. El mock del docente puede comportarse distinto.

## 7. Pruebas

```bash
# Lógica de dominio sin Laravel (reglas, validación, adaptador contra el mock)
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8081 mock-soap/server.php &
php tests/run.php
```

Colección de Postman: `postman/PoloNorte-Cartas.postman_collection.json`. Importarla y ejecutarla en orden (Runner). Cubre 201, 200, 400, 404, 401, 403, 409, 422 y 503 (SOAP caído).

## 8. Si el Polo Norte separara el sistema en microservicios

> ⚠️ Borrador: reescríbelo con tus palabras antes de entregar (máx. 150 palabras).

Separaría tres servicios: **Cartas** (ciclo de vida: id, remitente, edad, ciudad, regalos, estado, fecha), **Comportamiento** (dueño de la Lista de Buenos y Traviesos y de su caché de veredictos por nombre y ciudad) y **Producción/Entrega** (estados posteriores a `APROBADA`). Cada uno tendría su propia base de datos y se comunicarían por API o eventos, nunca compartiendo tablas. Si Comportamiento cae el 24 de diciembre a las 23:00, Cartas sigue recibiendo y consultando cartas, porque crear no depende de él. Solo falla la aprobación: responde 503, la carta queda en `RECIBIDA` con `PENDIENTE_VERIFICACION` y no se pierde nada. Un circuit breaker evitaría saturarlo y, al recuperarse, se reprocesarían las pendientes. Hoy ese límite ya existe dentro del monolito: es el adaptador.
