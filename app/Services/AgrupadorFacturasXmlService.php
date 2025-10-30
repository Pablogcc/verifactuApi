<?php

namespace App\Services;

use App\Models\Facturas;
use DOMDocument;
use Illuminate\Support\Collection;
use DOMXPath;

class AgrupadorFacturasXmlService
{

    protected FacturaXmlGenerator $xmlGenerator;

    public function __construct(FacturaXmlGenerator $xmlGenerator)
    {
        $this->xmlGenerator = $xmlGenerator;
    }

     public function buildGroupedXml(iterable $facturas): string
    {
        // Normalizar a Collection para comodidad
        $coll = $facturas instanceof Collection ? $facturas : collect($facturas);

        if ($coll->isEmpty()) {
            throw new \InvalidArgumentException('La colección de facturas está vacía.');
        }

        $primera = $coll->first();

        // Documento destino (agrupado)
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Envelope + namespaces (idénticos a los que usa tu generateXml)
        $envelope = $dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sum', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sum1', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd');
        $dom->appendChild($envelope);

        // Header vacío
        $envelope->appendChild($dom->createElement('soapenv:Header'));

        // Body
        $body = $dom->createElement('soapenv:Body');
        $envelope->appendChild($body);

        // sum:RegFactuSistemaFacturacion
        $regFactu = $dom->createElement('sum:RegFactuSistemaFacturacion');
        $body->appendChild($regFactu);

        // Cabecera -> ObligadoEmision (tomada del primer elemento)
        $cabecera = $dom->createElement('sum:Cabecera');
        $obligado = $dom->createElement('sum1:ObligadoEmision');
        $obligado->appendChild($dom->createElement('sum1:NombreRazon', $primera->nombreEmisor));
        $obligado->appendChild($dom->createElement('sum1:NIF', $primera->idEmisorFactura));
        $cabecera->appendChild($obligado);
        $regFactu->appendChild($cabecera);

        // Por cada factura: generar XML, extraer RegistroFactura e importarlo
        foreach ($coll as $factura) {
            // Obtenemos el XML completo de la factura con tu generador
            $xmlFactura = $this->xmlGenerator->generateXml($factura);

            // Parsear XML temporalmente
            $tmp = new DOMDocument();
            $tmp->preserveWhiteSpace = false;
            $tmp->formatOutput = false;

            libxml_use_internal_errors(true);
            $ok = @$tmp->loadXML($xmlFactura);
            if (!$ok) {
                $errs = libxml_get_errors();
                libxml_clear_errors();
                $messages = array_map(function ($e) {
                    return trim($e->message) . " (line {$e->line})";
                }, $errs);
                throw new \RuntimeException('XML inválido generado para factura ' . ($factura->numSerieFactura ?? 'N/A') . ': ' . implode('; ', $messages));
            }
            libxml_clear_errors();

            // XPath para encontrar nodos <RegistroFactura> por local-name (no dependemos del prefijo)
            $xpath = new DOMXPath($tmp);
            $nodes = $xpath->query("//*[local-name() = 'RegistroFactura']");

            if ($nodes->length === 0) {
                throw new \RuntimeException('No se encontró <RegistroFactura> en el XML generado para ' . ($factura->numSerieFactura ?? 'N/A'));
            }

            // Puede haber más de uno, importamos todos (normalmente hay uno)
            foreach ($nodes as $node) {
                $imported = $dom->importNode($node, true);
                $regFactu->appendChild($imported);
            }
        }

        return $dom->saveXML();
    }
}
