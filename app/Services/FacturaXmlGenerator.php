<?php

namespace App\Services;

use App\Models\Facturas;
use DOMDocument;

class FacturaXmlGenerator
{

    public function generateXml(Facturas $factura)
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
        if ($factura->tipoFactura === 'R1' || $factura->tipoFactura === 'R2' || $factura->tipoFactura === 'R3' || $factura->tipoFactura === 'R4') {
            $remisionvoluntaria = $dom->createElement('sum1:RemisionVoluntaria');
            $remisionvoluntaria->appendChild($dom->createElement('sum1:Incidencia', 'N'));
            $cabecera->appendChild($remisionvoluntaria);
        }
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
        if ($factura->tipoFactura === 'R1' || $factura->tipoFactura === 'R2' || $factura->tipoFactura === 'R3' || $factura->tipoFactura === 'R4') {
            $registroAlta->appendChild($dom->createElement('sum1:RechazoPrevio'));
        }
        $registroAlta->appendChild($dom->createElement('sum1:TipoFactura', $factura->tipoFactura));


        if ($factura->tipoFactura === 'R1' || $factura->tipoFactura === 'R2' || $factura->tipoFactura === 'R3' || $factura->tipoFactura === 'R4') {
            $registroAlta->appendChild($dom->createElement('sum1:TipoRectificativa', 'I'));
        }

        if ($factura->tipoFactura === 'R1' || $factura->tipoFactura === 'R2' || $factura->tipoFactura === 'R3' || $factura->tipoFactura === 'R4') {
            $facturasRectificadas = $dom->createElement('sum1:FacturasRectificadas');
            $idFacturaRectificada = $dom->createElement('sum1:IDFacturaRectificada');
            $idFacturaRectificada->appendChild($dom->createElement('sum1:IDEmisorFactura', $factura->idEmisorFacturaRectificada));
            $idFacturaRectificada->appendChild($dom->createElement('sum1:NumSerieFactura', $factura->numSerieFacturaRectificada));
            $idFacturaRectificada->appendChild($dom->createElement('sum1:FechaExpedicionFactura', $factura->fechaExpedicionFacturaRectificada));
            $facturasRectificadas->appendChild($idFacturaRectificada);
            $registroAlta->appendChild($facturasRectificadas);
        }

        $registroAlta->appendChild($dom->createElement('sum1:DescripcionOperacion', $factura->descripcionOperacion));

        $registroAlta->appendChild($dom->createElement('sum1:FacturaSimplificadaArt7273'));
        $registroAlta->appendChild($dom->createElement('sum1:Macrodato'));

        // sum1:Destinatarios
        $destinatarios = $dom->createElement('sum1:Destinatarios');
        $idDest = $dom->createElement('sum1:IDDestinatario');
        $idDest->appendChild($dom->createElement('sum1:NombreRazon', $factura->nombreCliente));
        if ($factura->idTypeNum === '02' || $factura->idTypeNum === '03') {
            $idOtro = $dom->createElement('sum1:IDOtro');
            $idOtro->appendChild($dom->createElement('sum1:CodigoPais', $factura->codigoPais));
            $idOtro->appendChild($dom->createElement('sum1:IDType', $factura->idTypeNum));
            $idOtro->appendChild($dom->createElement('sum1:ID', $factura->nifCliente));
            $idDest->appendChild($idOtro);
        } elseif ($factura->idTypeNum === '01') {
            $idDest->appendChild($dom->createElement('sum1:NIF', $factura->nifCliente));
        }

        $destinatarios->appendChild($idDest);
        $registroAlta->appendChild($destinatarios);

        $registroAlta->appendChild($dom->createElement('sum1:Cupon'));

        // sum1:Desglose
        $esRectificativa = in_array($factura->tipoFactura, ['R1', 'R2', 'R3', 'R4']);

        $desglose = $dom->createElement('sum1:Desglose');

        for ($i = 1; $i <= 4; $i++) {
            $tipoKey = $i === 1 ? 'tipoImpositivo' : "tipoImpositivo{$i}";
            $baseKey = $i === 1 ? 'baseImponibleACoste' : "baseImponibleACoste{$i}";
            $cuotaKey = $i === 1 ? 'cuotaRepercutida' : "cuotaRepercutida{$i}";


            if ($factura->$tipoKey !== null && $factura->$baseKey !== null && $factura->$cuotaKey !== null) {
                $detalle = $dom->createElement('sum1:DetalleDesglose');

                if ($esRectificativa) {
                    $detalle->appendChild($dom->createElement('sum1:Impuesto', '01'));
                }

                $detalle->appendChild($dom->createElement('sum1:ClaveRegimen', '01'));
                $detalle->appendChild($dom->createElement('sum1:CalificacionOperacion', 'S1'));
                $detalle->appendChild($dom->createElement('sum1:TipoImpositivo', number_format($factura->$tipoKey, 2, '.', '')));
                $detalle->appendChild($dom->createElement('sum1:BaseImponibleOimporteNoSujeto', number_format($factura->$baseKey, 2, '.', '')));
                $detalle->appendChild($dom->createElement('sum1:CuotaRepercutida', number_format($factura->$cuotaKey, 2, '.', '')));

                $desglose->appendChild($detalle);
            }
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
