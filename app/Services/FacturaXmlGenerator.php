<?php

namespace App\Services;

use App\Models\Facturas;
use DOMDocument;

class FacturaXmlGenerator
{

    public function generateXml(Facturas $factura)
    {

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $facturaElement = $dom->createElement('Facturas');
        //Campos iniciales
        $facturaElement->appendChild($dom->createElement('idVersion', $factura->idVerison));
        $facturaElement->appendChild($dom->createElement('idEmisorFactura', $factura->idEmisorFactura));
        $facturaElement->appendChild($dom->createElement('numSerieFactura', $factura->numSerieFactura));
        $facturaElement->appendChild($dom->createElement('fechaExpedicionFactura', $factura->fechaExpedicionFactura));
        $facturaElement->appendChild($dom->createElement('refExterna', $factura->refExterna));
        $facturaElement->appendChild($dom->createElement('nombreRazonEmisor', $factura->nombreRazonEmisor));
        $facturaElement->appendChild($dom->createElement('subsanacion', $factura->subsanacion));
        $facturaElement->appendChild($dom->createElement('rechazoPrevio', $factura->rechazoPrevio));
        $facturaElement->appendChild($dom->createElement('tipoFactura', $factura->tipoFactura));

        //Facturas Rectificadas
        $facturaElement->appendChild($dom->createElement('idEmisorFacturaRectificada', $factura->idEmisorFacturaRectificada));
        $facturaElement->appendChild($dom->createElement('numSerieFacturaRectificada', $factura->numSerieFacturaRectificada));
        $facturaElement->appendChild($dom->createElement('fechaExpedicionFacturaRectificada', $factura->fechaExpedicionFacturaRectificada));

        //Facturas Sustituidas
        $facturaElement->appendChild($dom->createElement('idEmisorFacturaSustituida', $factura->idEmisorFacturaSustituida));
        $facturaElement->appendChild($dom->createElement('numSerieFacturaSustituida', $factura->numSerieFacturaSustituida));
        $facturaElement->appendChild($dom->createElement('fechaExpedicionFacturaSustituida', $factura->fechaExpedicionFacturaSustituida));
        $facturaElement->appendChild($dom->createElement('baseRectificada', $factura->baseRectificada));
        //Datos del destinatario
        
        //Datos fiscales

        //Registro adicional


    }
}
