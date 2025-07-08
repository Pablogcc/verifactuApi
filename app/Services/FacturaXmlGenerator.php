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

        $sum1 = $dom->getElementsByTagName('sum1:')->item(0);

        //Obligado emisión
        $facturaElement->appendChild($dom->createElement($sum1 . 'NombreRazon', $factura->nombreEmisor));
        $facturaElement->appendChild($dom->createElement('NIF', $factura->idEmisorFactura));

        $facturaElement->appendChild($dom->createElement('IDVersion', $factura->idVersion));

        //ID Factura
        $facturaElement->appendChild($dom->createElement('NIF', $factura->idEmisorFactura));
        $facturaElement->appendChild($dom->createElement('NumSerieFactura', $factura->numSerieFactura));
        $facturaElement->appendChild($dom->createElement('FechaExpedicionFactura', $factura->fechaExpedicionFactura));

        $facturaElement->appendChild($dom->createElement('NombreRazon', $factura->nombreEmisor));
        $facturaElement->appendChild($dom->createElement('TipoFactura', $factura->tipoFactura));
        $facturaElement->appendChild($dom->createElement('DescripcionOperacion', $factura->descripcionOperacion));

        //ID Destinatario(Cliente)
        $facturaElement->appendChild($dom->createElement('nombreRazon', $factura->nombreCliente));
        $facturaElement->appendChild($dom->createElement('NIF', $factura->nifCliente));

        //Detalle desglose 1
        $facturaElement->appendChild($dom->createElement('ClaveRegimen', $factura->claveRegimen));
        $facturaElement->appendChild($dom->createElement('CalificacionOperacion', $factura->calificacionOperacion));
        $facturaElement->appendChild($dom->createElement('TipoImpositivo', $factura->TipoImpositivo));
        $facturaElement->appendChild($dom->createElement('BaseImponibleOimporteNoSujeto', $factura->baseImponibleOimporteNoSujeto));

        //Detalle desglose 2
        $facturaElement->appendChild($dom->createElement('ClaveRegimen', $factura->claveRegimenSegundo));
        $facturaElement->appendChild($dom->createElement('CalificacionOperacion', $factura->calificacionOperacionSegundo));
        $facturaElement->appendChild($dom->createElement('TipoImpositivo', $factura->TipoImpositivoSegundo));
        $facturaElement->appendChild($dom->createElement('BaseImponibleOimporteNoSujeto', $factura->baseImponibleOimporteNoSujetoSegundo));

        $facturaElement->appendChild($dom->createElement('CuotaTotal', $factura->cuotaTotal));
        $facturaElement->appendChild($dom->createElement('ImporteTotal', $factura->importeTotal));

        //Registro Anterior
        $facturaElement->appendChild($dom->createElement('IDEmisorFactura', $factura->IDEmisorFacturaAnterior));
        $facturaElement->appendChild($dom->createElement('NumSerieFactura', $factura->numSerieFactura));
        $facturaElement->appendChild($dom->createElement('FechaExpedicionFactura', $factura->FechaExpedicionFactura));
        $facturaElement->appendChild($dom->createElement('Huella', $factura->huellaAnterior));

        //Sistema Informático
        $facturaElement->appendChild($dom->createElement('NombreRazon', $factura->nombreFabricanteSoftware));
        $facturaElement->appendChild($dom->createElement('NIF', $factura->nifFabricanteSoftware));
        $facturaElement->appendChild($dom->createElement('NombreSistemaInformatico', $factura->nombreSoftware));
        $facturaElement->appendChild($dom->createElement('IdSistemaInformatico', $factura->identificadorSoftware));
        $facturaElement->appendChild($dom->createElement('Version', $factura->versionSoftware));
        $facturaElement->appendChild($dom->createElement('NumeroInstalacion', $factura->numeroInstalacion));
        $facturaElement->appendChild($dom->createElement('TipoUsoPosibleSoloVerifactu', $factura->tipoUsoPosibleVerifactu));
        $facturaElement->appendChild($dom->createElement('TipoUsoPosibleMultiOT', $factura->tipoUsoPosibleMultiOT));
        $facturaElement->appendChild($dom->createElement('IndicadorMultiplesOT', $factura->indicadorMultiplesOT));

        $facturaElement->appendChild($dom->createElement('FechaHoraHusoGenRegistro', $factura->fechaHoraHusoGenRegistro));


        
    /*
        $facturaElement->appendChild($dom->createElement('refExterna', $factura->refExterna));
        $facturaElement->appendChild($dom->createElement('nombreEmisor', $factura->nombreEmisor));
        $facturaElement->appendChild($dom->createElement('cifEmisor', $factura->cifEmisor));
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
        $facturaElement->appendChild($dom->createElement('cuotaRectificada', $factura->cuotaRectificada));
        $facturaElement->appendChild($dom->createElement('cuotaRecargoRectificado', $factura->cuotaRecargoRectificado));
        $facturaElement->appendChild($dom->createElement('fechaOperacion', $factura->fechaOperacion));
        $facturaElement->appendChild($dom->createElement('descripcionOperacion', $factura->descripcionOperacion));
        $facturaElement->appendChild($dom->createElement('facturaSimplificadaArt7273', $factura->facturaSimplificadaArt7273));
        $facturaElement->appendChild($dom->createElement('facturaSinIdentifDestinatarioArt61d', $factura->facturaSinIdentifDestinatarioArt61d));
        $facturaElement->appendChild($dom->createElement('macrodato', $factura->macrodato));
        $facturaElement->appendChild($dom->createElement('emitidaPorTerceroODestinatario', $factura->emitidaPorTerceroODestinatario));

        //Datos del destinatario
        $facturaElement->appendChild($dom->createElement('nombreCliente', $factura->nombreCliente));
        $facturaElement->appendChild($dom->createElement('nifCliente', $factura->nifCliente));
        $facturaElement->appendChild($dom->createElement('codigoPais', $factura->codigoPais));
        $facturaElement->appendChild($dom->createElement('idType', $factura->idType));
        $facturaElement->appendChild($dom->createElement('id', $factura->id));

        //Datos fiscales
        $facturaElement->appendChild($dom->createElement('cupon', $factura->cupon));
        $facturaElement->appendChild($dom->createElement('impuesto', $factura->impuesto));
        $facturaElement->appendChild($dom->createElement('claveRegimen', $factura->claveRegimen));
        $facturaElement->appendChild($dom->createElement('calificacionOperacion', $factura->calificacionOperacion));
        $facturaElement->appendChild($dom->createElement('operacionExenta', $factura->operacionExenta));
        $facturaElement->appendChild($dom->createElement('tipoImpositivo', $factura->tipoImpositivo));
        $facturaElement->appendChild($dom->createElement('baseImponibleOimporteNoSujeto', $factura->baseImponibleOimporteNoSujeto));
        $facturaElement->appendChild($dom->createElement('baseImponibleACoste', $factura->baseImponibleACoste));
        $facturaElement->appendChild($dom->createElement('cuotaRepercutida', $factura->cuotaRepercutida));
        $facturaElement->appendChild($dom->createElement('tipoRecargoEquivalencia', $factura->tipoRecargoEquivalencia));
        $facturaElement->appendChild($dom->createElement('cuotaRecargoEquivalencia', $factura->cuotaRecargoEquivalencia));
        $facturaElement->appendChild($dom->createElement('cuotaTotal', $factura->cuotaTotal));
        $facturaElement->appendChild($dom->createElement('importeTotal', $factura->importeTotal));
        $facturaElement->appendChild($dom->createElement('primerRegistro', $factura->primerRegistro));

        //Registro adicional
        $facturaElement->appendChild($dom->createElement('huella', $factura->huella));
        $facturaElement->appendChild($dom->createElement('fechaHoraHusoGenRegistro', $factura->fechaHoraHusoGenRegistro));
        $facturaElement->appendChild($dom->createElement('numRegistroAcuerdoFacturacion', $factura->numRegistroAcuerdoFacturacion));
        $facturaElement->appendChild($dom->createElement('idAcuerdoSistemaInformatico', $factura->idAcuerdoSistemaInformatico));
        $facturaElement->appendChild($dom->createElement('tipoHuella', $factura->tipoHuella));
        */
    $dom->appendChild($facturaElement);

    return $dom->saveXML();
    }
}
