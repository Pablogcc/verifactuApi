<?php

namespace App\Services;

use soapVar;
use Illuminate\Support\Facades\Log;

class ClientesSOAPConsultaCDI
{
    public function consultar(string $nif, string $nombre): string|array
    {

        $crtPem = base_path('storage/certs/verifactu-cert.pem');
        $keyPem = base_path('storage/certs/verifactu-key.pem');
        $pass = env('PFX_CERT_PASSWORD');

        $url = "https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP";
        // xmlns:LocalPart="http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Ent.xsd"
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope
    xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:vnif="http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Ent.xsd"
    >
    <soapenv:Header/>
    <soapenv:Body>
        <vnif:VNifV2Ent>
            <vnif:Contribuyente>
                <vnif:Nif>{$nif}</vnif:Nif>
                <vnif:Nombre>{$nombre}</vnif:Nombre>
            </vnif:Contribuyente>
        </vnif:VNifV2Ent>
    </soapenv:Body>
</soapenv:Envelope>
XML;

        $headers = [
            'Content-type: text/xml; charset=utf-8',
            'Content-length: ' . strlen($xml),
            'SOAPAction: "VNifV2"'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Certificados
        curl_setopt($ch, CURLOPT_SSLCERT, $crtPem);
        curl_setopt($ch, CURLOPT_SSLKEY, $keyPem);
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $pass);
        curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $pass);

        

        $response = curl_exec($ch);

        return "Respuesta:\n" . $response;

        return response()->json([
            'success' => true,
            'message' => 'Respuesta de la AEAT',
            'data' => $response
        ]);

        $error = curl_error($ch);
        curl_close($ch);

        Log::info("REQUEST XML\n" . $xml);
        Log::info("RESPONSE\n" . $response);

        if ($response === false) {
            return "Error en el curl: " . $error;
        }

        libxml_use_internal_errors(true);
        $xmlObject = simplexml_load_string($response);

        if ($xmlObject === false) {
            return "Error al parsear el xml";
        }

        $namespaces = $xmlObject->getNamespaces(true);
        $body = $xmlObject->children($namespaces['env'])->Body ?? null;

        if (!$body) {
            return "No hay ningún cuerpo en el XML";
        }

        $vnifResp = $body->children($namespaces['vnif'])->VNifV2Resp ?? null;

        if (!$vnifResp) {
            return "No hay ningún nodeo en el xml";
        }

        $contribuyente = $vnifResp->Contribuyente ?? null;

        if (!$contribuyente) {
            return "No hay ningún contribuyente";
        }

        $resultado = (string) $contribuyente->ResultadoIdentificacion ?? '';
        $nifResp = (string) $contribuyente->Nif ?? '';
        $nombreResp = (string) $contribuyente->Nombre ?? '';

        return response()->json([
            'success' => true,
            'message' => "Respuesta de la AEAT",
            'data' => [
                'NIF' => $nifResp,
                'Nombre' => $nombreResp,
                'resultado' => $resultado,
                'VNifV2Sal' => 'IDENTIFICADO'
            ]
        ]);
            
    
    }
}
