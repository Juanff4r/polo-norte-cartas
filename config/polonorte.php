<?php

return [
    // rol => token. Un token vacío nunca da acceso (ver RequiereRol).
    'tokens' => [
        'ELFO_JEFE' => env('TOKEN_ELFO_JEFE', ''),
        'ELFO' => env('TOKEN_ELFO', ''),
    ],

    'soap' => [
        'lista_url' => env('SOAP_LISTA_URL', 'http://127.0.0.1:8081'),
        'timeout' => (float) env('SOAP_TIMEOUT', 3),
        'intentos' => (int) env('SOAP_INTENTOS', 2),
        'action' => env('SOAP_LISTA_ACTION', 'ConsultarComportamiento'),
    ],

    'cartas_path' => env('CARTAS_STORAGE_PATH', storage_path('app/cartas.json')),

    // Solo para demostrar el 422 de la fecha límite. false fuera del examen.
    'permitir_fecha_simulada' => (bool) env('PERMITIR_FECHA_SIMULADA', false),
];
