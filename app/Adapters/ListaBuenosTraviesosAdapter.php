<?php

namespace App\Adapters;

use App\Contracts\ComportamientoGateway;
use App\Enums\Comportamiento;
use App\Exceptions\ServicioExternoNoDisponibleException;
use DOMDocument;

/**
 * Único lugar del sistema que conoce XML/SOAP (traduce objeto de dominio <-> XML).
 * Manejo de fallos: Fault, timeout (3 s), conexión rechazada o respuesta ilegible
 * terminan TODOS en ServicioExternoNoDisponibleException. Nunca se filtra un error genérico.
 * Reintento con backoff exponencial (extra de la rúbrica): $intentos intentos en total.
 */
final class ListaBuenosTraviesosAdapter implements ComportamientoGateway
{
    private const NS = 'http://polonorte.beatlech/lista';

    public function __construct(
        private readonly string $endpoint,
        private readonly float $timeoutSegundos = 3.0,
        private readonly int $intentos = 2,
        private readonly string $soapAction = 'ConsultarComportamiento',
        private readonly int $backoffMs = 200,
    ) {
    }

    public function consultar(string $nombre, string $ciudad): Comportamiento
    {
        $ultimo = null;

        for ($intento = 1; $intento <= max(1, $this->intentos); $intento++) {
            try {
                return $this->consultarUnaVez($nombre, $ciudad);
            } catch (ServicioExternoNoDisponibleException $e) {
                $ultimo = $e;
                if ($intento < $this->intentos) {
                    usleep($this->backoffMs * 1000 * (2 ** ($intento - 1)));
                }
            }
        }

        throw $ultimo;
    }

    private function consultarUnaVez(string $nombre, string $ciudad): Comportamiento
    {
        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->construirSobre($nombre, $ciudad),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => (int) ($this->timeoutSegundos * 1000),
            CURLOPT_TIMEOUT_MS => (int) ($this->timeoutSegundos * 1000),
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $this->soapAction . '"',
            ],
        ]);

        $cuerpo = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($cuerpo === false || $cuerpo === '') {
            throw new ServicioExternoNoDisponibleException('Sin respuesta del servicio SOAP: ' . ($error ?: 'cuerpo vacío'));
        }

        return $this->interpretarRespuesta($cuerpo);
    }

    /** Dominio -> XML. Los valores se escapan: un remitente con "<" no puede romper ni inyectar XML. */
    private function construirSobre(string $nombre, string $ciudad): string
    {
        $esc = fn (string $v) => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:pn="' . self::NS . '">'
            . '<soapenv:Body><pn:ConsultarComportamientoRequest>'
            . '<pn:nombre>' . $esc($nombre) . '</pn:nombre>'
            . '<pn:ciudad>' . $esc($ciudad) . '</pn:ciudad>'
            . '</pn:ConsultarComportamientoRequest></soapenv:Body></soapenv:Envelope>';
    }

    /** XML -> dominio. Se parsea el cuerpo aunque el HTTP sea 500: los Fault SOAP suelen venir así. */
    private function interpretarRespuesta(string $xml): Comportamiento
    {
        $doc = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        if (!$ok) {
            throw new ServicioExternoNoDisponibleException('Respuesta SOAP ilegible');
        }

        if ($doc->getElementsByTagNameNS('*', 'Fault')->length > 0) {
            $texto = trim($doc->getElementsByTagName('faultstring')->item(0)?->textContent ?? 'desconocido');
            throw new ServicioExternoNoDisponibleException('SOAP Fault: ' . $texto);
        }

        $nodo = $doc->getElementsByTagNameNS(self::NS, 'resultado')->item(0)
            ?? $doc->getElementsByTagNameNS('*', 'resultado')->item(0);
        $valor = Comportamiento::tryFrom(trim($nodo?->textContent ?? ''));

        if ($valor === null || $valor === Comportamiento::PENDIENTE_VERIFICACION) {
            throw new ServicioExternoNoDisponibleException('La respuesta SOAP no trae un resultado válido');
        }

        return $valor;
    }
}
