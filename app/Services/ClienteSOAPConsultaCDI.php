<?php


namespace App\Services;

use SoapVar;
use Illuminate\Support\Facades\Log;

class ClienteSOAPConsultaCDI
{

    public function consultar(string $nif, string $nombre): string
    {
        $wsdl = base_path('storage/wsdl/VNIFV2.wsdl'); // 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2.wsdl'

        $options = [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' =>  0,
            'connection_timeout' => 30,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'local_cert' => base_path('storage/certs/verifactu-combined.pem'),
            //'passphrase' => env('PFX_CERT_PASSWORD', ''),
        ];


        $client = new \SoapClient($wsdl, $options);

        $nif = $this->sanitizeUtf8($nif);
        $nombre = $this->sanitizeUtf8($nombre);

        $xml = <<<XML
<VNifV2Ent xmlns="http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Ent.xsd">
    <Contribuyente>
        <Nif>{$nif}</Nif>
        <Nombre>{$nombre}</Nombre>
    </Contribuyente>
</VNifV2Ent>
XML;

        // $params = [new \SoapVar($xml, XSD_ANYXML)];

        //$namespace = 'http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Ent.xsd';

        /*$params = [
            'Contribuyente' => [
                'Nif' => $nif,
                'Nombre' => $nombre
            ]
        ];*/

        $soapVar = new SoapVar($xml, XSD_ANYXML);

        try {
            $result = $client->__soapCall('VNifV2', [$soapVar]);

            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\SoapFault $e) {
            return 'SOAP Fault: ' . $e->getMessage();
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
