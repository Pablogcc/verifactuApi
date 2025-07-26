<?php

namespace App\Services;

use App\Models\Estado_procesos;
use DOMDocument;

class BloqueoXmlGenerator
{

    public function generateXml(Estado_procesos $factura)
    {

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Crear el nodo raíz Envelope con namespaces
        $envelope = $dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sum', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sum1', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd');
        $dom->appendChild($envelope);

        // Añadir Header vacío y Body
        $envelope->appendChild($dom->createElement('soapenv:Header'));
        $body = $dom->createElement('soapenv:Body');
        $envelope->appendChild($body);

        // sum:RegFactuSistemaFacturacion
        $regFactu = $dom->createElement('sum:RegFactuSistemaFacturacion');
        $body->appendChild($regFactu);

        // sum:Cabecera > sum1:ObligadoEmision
        $cabecera = $dom->createElement('sum:Cabecera');
        $obligado = $dom->createElement('sum1:ObligadoEmision');
        $obligado->appendChild($dom->createElement('sum1:NombreRazon', $factura->nombreEmisor));
        $obligado->appendChild($dom->createElement('sum1:NIF', $factura->idEmisorFactura));
        $cabecera->appendChild($obligado);
        $regFactu->appendChild($cabecera);

        // sum:RegistroFactura > sum1:RegistroAlta
        $registroFactura = $dom->createElement('sum:RegistroFactura');
        $registroAlta = $dom->createElement('sum1:RegistroAlta');

        // sum1:IDVersion
        $registroAlta->appendChild($dom->createElement('sum1:IDVersion', $factura->idVersion));

        // sum1:IDFactura
        $idFactura = $dom->createElement('sum1:IDFactura');
        $idFactura->appendChild($dom->createElement('sum1:IDEmisorFactura', $factura->idEmisorFactura));
        $idFactura->appendChild($dom->createElement('sum1:NumSerieFactura', $factura->numSerieFactura));
        $idFactura->appendChild($dom->createElement('sum1:FechaExpedicionFactura', $factura->fechaExpedicionFactura));
        $registroAlta->appendChild($idFactura);

        // sum1:NombreRazonEmisor y demás campos...
        $registroAlta->appendChild($dom->createElement('sum1:NombreRazonEmisor', $factura->nombreEmisor));
        $registroAlta->appendChild($dom->createElement('sum1:TipoFactura', $factura->tipoFactura));
        $registroAlta->appendChild($dom->createElement('sum1:DescripcionOperacion', $factura->descripcionOperacion));

        // sum1:Destinatarios
        $destinatarios = $dom->createElement('sum1:Destinatarios');
        $idDest = $dom->createElement('sum1:IDDestinatario');
        $idDest->appendChild($dom->createElement('sum1:NombreRazon', $factura->nombreCliente));
        $idDest->appendChild($dom->createElement('sum1:NIF', $factura->nifCliente));
        $destinatarios->appendChild($idDest);
        $registroAlta->appendChild($destinatarios);

        // sum1:Desglose > DetalleDesglose x2
        $desglose = $dom->createElement('sum1:Desglose');

        foreach (
            [
                [
                    'ClaveRegimen' => $factura->claveRegimen,
                    'CalificacionOperacion' => $factura->calificacionOperacion,
                    'TipoImpositivo' => $factura->tipoImpositivo,
                    'BaseImponibleOimporteNoSujeto' => $factura->baseImponibleACoste,
                    'CuotaRepercutida' => $factura->cuotaRepercutida,
                ]
            ] as $detalleData
        ) {
            $detalle = $dom->createElement('sum1:DetalleDesglose');
            foreach ($detalleData as $tag => $val) {
                $detalle->appendChild($dom->createElement("sum1:$tag", $val));
            }
            $desglose->appendChild($detalle);
        }

        $registroAlta->appendChild($desglose);

        // Totales
        $registroAlta->appendChild($dom->createElement('sum1:CuotaTotal', $this->formatearImporte($factura->cuotaTotal)));
        $registroAlta->appendChild($dom->createElement('sum1:ImporteTotal', $this->formatearImporte($factura->importeTotal)));

        // Encadenamiento
        $encadenamiento = $dom->createElement('sum1:Encadenamiento');
        $registroAnterior = $dom->createElement('sum1:RegistroAnterior');
        $registroAnterior->appendChild($dom->createElement('sum1:IDEmisorFactura', $factura->IDEmisorFacturaAnterior));
        $registroAnterior->appendChild($dom->createElement('sum1:NumSerieFactura', $factura->numSerieFacturaAnterior));
        $registroAnterior->appendChild($dom->createElement('sum1:FechaExpedicionFactura', $factura->FechaExpedicionFacturaAnterior));
        $registroAnterior->appendChild($dom->createElement('sum1:Huella', $factura->huellaAnterior));
        $encadenamiento->appendChild($registroAnterior);
        $registroAlta->appendChild($encadenamiento);

        // Sistema Informático
        $sistema = $dom->createElement('sum1:SistemaInformatico');
        $sistema->appendChild($dom->createElement('sum1:NombreRazon', $factura->nombreFabricanteSoftware));
        $sistema->appendChild($dom->createElement('sum1:NIF', $factura->nifFabricanteSoftware));
        $sistema->appendChild($dom->createElement('sum1:NombreSistemaInformatico', $factura->nombreSoftware));
        $sistema->appendChild($dom->createElement('sum1:IdSistemaInformatico', $factura->identificadorSoftware));
        $sistema->appendChild($dom->createElement('sum1:Version', $factura->versionSoftware));
        $sistema->appendChild($dom->createElement('sum1:NumeroInstalacion', $factura->numeroInstalacion));
        $sistema->appendChild($dom->createElement('sum1:TipoUsoPosibleSoloVerifactu', $factura->tipoUsoPosibleVerifactu));
        $sistema->appendChild($dom->createElement('sum1:TipoUsoPosibleMultiOT', $factura->tipoUsoPosibleMultiOT));
        $sistema->appendChild($dom->createElement('sum1:IndicadorMultiplesOT', $factura->indicadorMultiplesOT));
        $registroAlta->appendChild($sistema);

        // Fecha y huella
        $registroAlta->appendChild($dom->createElement('sum1:FechaHoraHusoGenRegistro', $factura->fechaHoraHusoGenRegistro));
        $registroAlta->appendChild($dom->createElement('sum1:TipoHuella', $factura->tipoHuella));
        $registroAlta->appendChild($dom->createElement('sum1:Huella', $factura->huella));

        $registroFactura->appendChild($registroAlta);
        $regFactu->appendChild($registroFactura);

        return $dom->saveXML();
    }

    private function formatearImporte($valor)
    {
        $valor = floatval($valor);
        $decimales = strlen(substr(strrchr((string)$valor, "."), 1));

        if ($decimales === 0) {
            return number_format($valor, 1, '.', '');
        } elseif ($decimales === 1) {
            return number_format($valor, 2, '.', '');
        } else {
            return number_format($valor, 2, '.', '');
        }
    }
}
