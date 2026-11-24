<?php

/**
 * Mock local de ListaBuenosTraviesosService para desarrollo.
 *   PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8081 mock-soap/server.php
 * "Grinch"  -> soap:Fault "Lista en mantenimiento"
 * "Lento"   -> responde a los 6 s (prueba de timeout)
 * "Travies" -> TRAVIESO
 * otro      -> BUENO
 */

$xml = file_get_contents('php://input');
$doc = new DOMDocument();
@$doc->loadXML($xml ?: '<x/>', LIBXML_NONET);
$nombre = trim($doc->getElementsByTagNameNS('*', 'nombre')->item(0)?->textContent ?? '');

header('Content-Type: text/xml; charset=utf-8');

if (stripos($nombre, 'grinch') !== false) {
    http_response_code(500);
    echo '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
        . '<soap:Fault><faultcode>soap:Server</faultcode><faultstring>Lista en mantenimiento</faultstring></soap:Fault>'
        . '</soap:Body></soap:Envelope>';
    exit;
}

if (stripos($nombre, 'lento') !== false) {
    sleep(6);
}

$resultado = stripos($nombre, 'travies') !== false ? 'TRAVIESO' : 'BUENO';

echo '<?xml version="1.0"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
    . 'xmlns:pn="http://polonorte.beatlech/lista"><soapenv:Body><pn:ConsultarComportamientoResponse>'
    . '<pn:resultado>' . $resultado . '</pn:resultado>'
    . '</pn:ConsultarComportamientoResponse></soapenv:Body></soapenv:Envelope>';
