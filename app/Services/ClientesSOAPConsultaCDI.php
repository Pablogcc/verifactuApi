<?php

namespace App\Services;

use soapVar;
use Illuminate\Support\Facades\Log;

class ClientesSOAPConsultaCDI
{
    public function consultar(string $nif, string $nombre): string|array
    {

        //Guardamos el verifactu en un .pem, la clave en otro .pem y guardada en el .env(Verifactu), y la url de acceso al servicio
        $crtPem = base_path('storage/certs/verifactu-cert.pem');
        $keyPem = base_path('storage/certs/verifactu-key.pem');
        $pass = env('PFX_CERT_PASSWORD');
        $url = "https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP";

        /*Hemos quitado el: <?xml version="1.0" encoding="UTF-8"?> */

        //Estructura necesaria en XML para pasarle el NIF y NOMBRE a la AEAT(NO SE PUEDE CAMBIAR LA ESTRUCTURA DE LA AEAT)
        $xml = <<<XML
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

        //Headers HTTP para el cURL del XML
        $headers = [
            'Content-type: text/xml; charset=utf-8',
            'Content-length: ' . strlen($xml),
            'SOAPAction: "VNifV2"'
        ];

        //Configuración para el cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Certificados y claves para el cURL
        curl_setopt($ch, CURLOPT_SSLCERT, $crtPem);
        curl_setopt($ch, CURLOPT_SSLKEY, $keyPem);
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $pass);
        curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $pass);

        
        //Aquí devolvemos las respuesta de la AEAT(NO SE PUEDE CAMBIAR LA ESTRUCTURA DE LA AEAT)
        $response = curl_exec($ch);
        
        return $response;

    }
}
