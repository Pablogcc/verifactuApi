<?php


namespace App\Services;

use SoapVar;
use Illuminate\Support\Facades\Log;

class ClienteSOAPConsultaCDI
{

    public function consultar(string $nif, string $nombre): string
    {
        $wsdl = base_path('storage/wsdl/VNIFV2.wsdl'); // 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2.wsdl'

        $pfxPath = base_path(env('PFX_CERT_PATH'));
        $pfxPassword = env('PFX_CERT_PASSWORD');

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


        $params = new SoapVar([
            'vnif:Contribuyente' => new SoapVar([
                'vnif:Nif' => new SoapVar($nif, XSD_STRING, null, null, null, $ns),
                'vnif:Nombre' => new SoapVar($nombre, XSD_STRING, null, null, null, $ns),
            ], SOAP_ENC_OBJECT, null, null, null, $ns)
        ], SOAP_ENC_OBJECT, null, null, 'vnif:VNifV2Ent', $ns);

        /*$params = [
            'VNifV2Ent' => [
                'Contribuyente' => [
                    'Nif' => $nif,
                    'Nombre' => $nombre
                ]
            ]
        ];*/

        // $soapVar = new SoapVar($xml, XSD_ANYXML);

        

        try {
            $result = $client->__soapCall('VNifV2', [$params]);

            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\SoapFault $e) {
            Log::error('SOAP Fault: ' . $e->getMessage());
            Log::error('Request: ' . $client->__getLastRequest());
            Log::error('Response: ' . $client->__getLastResponse());

            return 'SOAP Fault: ' . $e->getMessage();
        }
    }

    private function sanitizeUtf8(String $string): string
    {
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }
}
