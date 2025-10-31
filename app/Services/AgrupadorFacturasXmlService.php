<?php

namespace App\Services;

use App\Models\Facturas;
use DOMDocument;
use DOMXPath;
use RuntimeException;

class AgrupadorFacturasXmlService
{
    protected $xmlGenerator;

    /**
     * Recibe una instancia de FacturaXmlGenerator (tu servicio actual)
     *
     * @param \App\Services\FacturaXmlGenerator $xmlGenerator
     */
    public function __construct($xmlGenerator)
    {
        $this->xmlGenerator = $xmlGenerator;
    }

    /**
     * Construye y devuelve un único XML (string) con un Envelope que contiene:
     *  - una sola vez <sum:Cabecera> (tomada de la primera factura)
     *  - N <sum:RegistroFactura> (uno por cada factura pasada)
     *
     * @param iterable $facturas Colección/array de modelos Facturas
     * @return string XML completo listo para enviar a la AEAT
     * @throws RuntimeException si no se puede construir el XML
     */
    public function buildGroupedXml(iterable $facturas): string
    {
        // Convertimos a array para poder acceder al primero y reutilizarlo
        $arr = is_array($facturas) ? $facturas : (method_exists($facturas, 'all') ? $facturas->all() : iterator_to_array($facturas));

        if (empty($arr)) {
            throw new RuntimeException('No se han pasado facturas para agrupar.');
        }

        // Namespaces usados por tu generator / AEAT
        $nsSoapenv = 'http://schemas.xmlsoap.org/soap/envelope/';
        $nsSum = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';
        $nsSum1 = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

        // Documento final
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Envelope raíz con namespaces (usamos qualified names con prefijo para mantener la misma apariencia)
        $envelope = $dom->createElementNS($nsSoapenv, 'soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sum', $nsSum);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sum1', $nsSum1);
        $dom->appendChild($envelope);

        // Header vacío y Body
        $envelope->appendChild($dom->createElement('soapenv:Header'));
        $body = $dom->createElement('soapenv:Body');
        $envelope->appendChild($body);

        // sum:RegFactuSistemaFacturacion
        $regFactu = $dom->createElementNS($nsSum, 'sum:RegFactuSistemaFacturacion');
        $body->appendChild($regFactu);

        $cabeceraAppended = false;

        // Recorremos y extraemos nodos RegistroFactura de cada XML generado por FacturaXmlGenerator
        foreach ($arr as $factura) {
            // Generamos el XML individual (tu generador produce Envelope con Cabecera + RegistroFactura)
            $xmlIndividual = $this->xmlGenerator->generateXml($factura);

            // Cargamos en DOMDocument temporal
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $loaded = $doc->loadXML($xmlIndividual);
            $errors = libxml_get_errors();
            libxml_clear_errors();
            if ($loaded === false || !empty($errors)) {
                // Si fallo al parsear, lanzamos excepción para que el controller decida qué hacer
                throw new RuntimeException('XML generado para una factura no es válido o no se pudo parsear. Revisa generateXml().');
            }

            // XPath para buscar nodos con nombres y namespaces
            $xpath = new DOMXPath($doc);

            // registrar namespaces (los prefijos en el XML generado por tu generator)
            $xpath->registerNamespace('soapenv', $nsSoapenv);
            $xpath->registerNamespace('sum', $nsSum);
            $xpath->registerNamespace('sum1', $nsSum1);

            // Extraemos (solo una vez) la Cabecera del primer documento
            if (! $cabeceraAppended) {
                // Ruta típica: /soapenv:Envelope/soapenv:Body/sum:RegFactuSistemaFacturacion/sum:Cabecera
                $cabNodes = $xpath->query('//sum:Cabecera');
                if ($cabNodes !== false && $cabNodes->length > 0) {
                    $cab = $cabNodes->item(0);
                    // importar y anexar la cabecera al documento final
                    $impCab = $dom->importNode($cab, true);
                    $regFactu->appendChild($impCab);
                    $cabeceraAppended = true;
                }
                // Si no encontramos cabecera no la añadimos (pero eso sería raro si tu generator la crea)
            }

            // Extraemos todos los sum:RegistroFactura del XML individual
            $registros = $xpath->query('//sum:RegistroFactura');

            if ($registros === false || $registros->length === 0) {
                // Si no hay RegistroFactura (por cualquier motivo), intentamos buscar sum1:RegistroAlta
                $altas = $xpath->query('//sum1:RegistroAlta');
                if ($altas !== false && $altas->length > 0) {
                    // Si solo hay RegistroAlta, lo encapsulamos dentro de un elemento sum:RegistroFactura nuevo
                    foreach ($altas as $alta) {
                        $newRegistro = $dom->createElementNS($nsSum, 'sum:RegistroFactura');
                        $importAlta = $dom->importNode($alta, true);
                        $newRegistro->appendChild($importAlta);
                        $regFactu->appendChild($newRegistro);
                    }
                    continue;
                }

                // Si no encontramos nada, como fallback añadimos el XML completo del individuo (no ideal)
                // Pero mejor lanzar error para que lo manejéis explícitamente
                throw new RuntimeException('No se encontraron nodos <sum:RegistroFactura> ni <sum1:RegistroAlta> en el XML individual generado.');
            }

            // Importar cada RegistroFactura al documento final
            foreach ($registros as $reg) {
                $imp = $dom->importNode($reg, true);
                $regFactu->appendChild($imp);
            }
        }

        // Devolver XML final
        return $dom->saveXML();
    }
}
