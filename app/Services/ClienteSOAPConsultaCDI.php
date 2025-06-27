<?php


namespace App\Services;

use SoapVar;
use Illuminate\Support\Facades\Log;

class ClienteSOAPConsultaCDI
{

    public function consultar(string $nif, string $nombre): string|array
    {
        $wsdl = base_path('storage/wsdl/VNIFV2.wsdl'); // 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2.wsdl'

        $pfxPath = base_path('storage/certs/verifactu-cert.pem');
        $pfxPassword = base_path('storage/certs/verifactu-key.pem');

        $contextOptions = [
            'ssl' => [
                'local_cert' => $pfxPath,
                'passphrase' => $pfxPassword,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ];

        $streamContext = stream_context_create($contextOptions);

        $options = [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' =>  0,
            'connection_timeout' => 30,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'local_cert' => $streamContext,
            //'passphrase' => env('PFX_CERT_PASSWORD', ''),
        ];


        $client = new \SoapClient($wsdl, $options);

        $nif = $this->sanitizeUtf8($nif);
        $nombre = $this->sanitizeUtf8($nombre);

        $ns = 'http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Ent.xsd';

        // $soapNif = new SoapVar($nif, XSD_STRING, null, null, 'vnif:Nif', $ns);
        // $soapNombre = new SoapVar($nombre, XSD_STRING, null, null, 'vnif:Nombre', $ns);

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
        </vnif:VnifV2Ent>
    </soapenv:Body>
</soapenv:Envelope>
XML;

        $headers = [
            'Content-type: text/xml; charset=utf-8',
            'Content-length: ' . strlen($xml),
            'SOAPAction: "VNifV2"'
        ];

        $params = new SoapVar([
            'vnif:Contribuyente' => new SoapVar([
                'vnif:Nif' => new SoapVar($nif, XSD_STRING, null, null, null, $ns),
                'vnif:Nombre' => new SoapVar($nif, XSD_STRING, null, null, null, $ns),
            ], SOAP_ENC_OBJECT, null, null, null, $ns)
        ], SOAP_ENC_OBJECT, null, null, 'vnif:VNifV2Ent', $ns);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $wsdl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_SSLCERT, $pfxPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $pfxPassword);
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $pfxPassword);
        curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $pfxPassword);

        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);

        // $soapVar = new SoapVar($xml, XSD_ANYXML);

        try {
            $result = $client->__soapCall('VNifV2', [$headers]);

            return response()->json([
                'success' => true,
                'message' => 'Respuesta de la AEAT',
                'data' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ]);
        } catch (\SoapFault $e) {
            return response()->json([
                'success' => false,
                'message' => 'NIF y NOMBRE incorrectos',
                'data' => [
                    'logs' => Log::error('Soap Fault: ' . $e->getMessage()),
                    Log::error('Request: ' . $e->getMessage()),
                    Log::error('Response: ' . $e->getMessage())
                ]
            ]);
            

            // return 'SOAP Fault: ' . $e->getMessage();
        }

        try {
            $result2 = $client->__soapCall('VNifV2', [$params]);

            return response()->json([
                'success' => true,
                'message' => 'Respuesta de la AEAT',
                'data' => json_encode($result2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ]);
        } catch (\SoapFault $e) {

            return response()->json([
                'success' => false,
                'message' => 'NIF y NOMBRE incorrectos',
                'data' =>  [
                    'logs' => Log::error('Soap Fault: ' . $e->getMessage()),
                    Log::error('Request: ' . $e->getMessage()),
                    Log::error('Response: ' . $e->getMessage())
                ]
            ]);
        }

        $error = curl_error($ch);
        curl_close($ch);

        Log::info("Request\n" . $xml);
        Log::info("RESPONSE\n" . $response);
        
        if($response === false) {
            return "Error en el curl: " . $error;
        }

        libxml_use_internal_errors(true);
        $xmlOject = simplexml_load_string($response);

        if (!$xmlOject) {
            return response()->json([
                'success' => false,
                'message' => 'El XML es incorrecto'
            ]);
        }

        $namespaces = $xmlOject->getNamespaces(true);
        $body = $xmlOject->children($namespaces);
        
        if (!$body) {
            return "No hay ningun cuerpo en el XML";
        }

        $vnifResp = $body->children($namespaces['vnif'])->VNifV2Resp ?? null;

        if (!$vnifResp) {
            return "No hay ningún nodo en el XML";
        }

        $contribuyente = $vnifResp->Contribuyente ?? null;

        if (!$contribuyente) {
            return "No hay ningún Contribuyente";
        }

        $resultado = (string) $contribuyente->ResultadoIdentificación ?? '';
        $nifResp = (string) $contribuyente->Niff ?? '';
        $nombreResp = (string) $contribuyente->Nombre ?? '';

        return [
            'nif' => $nombreResp,
            'nombre' => $nifResp,
            'resultado' => $resultado,
            'VNifV2Sal' => 'IDENTIFICADO'
        ];

    }
    private function sanitizeUtf8(String $string): string
    {
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }
}
