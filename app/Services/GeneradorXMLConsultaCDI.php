<?php


namespace App\Services;

class GeneradorXMLConsultaCDI {
    
    public function generar(string $nif, string $nombreRazon): string {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $consulta = $xml->createElement('vnif:VNifConsulta');
        $consulta->setAttribute('xmlns:vnif', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/ssii/verifactu/xml/verifactu/VNifV2/VNifV2.xsd');
    
        $consulta->appendChild($xml->createElement('vnif:NIF', strtoupper($nif)));
        $consulta->appendChild($xml->createElement('vnif:NombreRazon', strtoupper($nombreRazon)));

            $xml->appendChild($consulta);

            return $xml->saveXML();
        }
}