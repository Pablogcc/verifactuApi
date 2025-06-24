<?php


namespace App\Services;

class ClienteSOAPConsultaCDI
{

    public function consultar(string $nif, string $nombreRazon): string
    {
        $wsdl = base_path('storage/wsdl/VNIFV2.wsdl'); // 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2.wsdl'

        $options = [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' =>  0,
            'connection_timeout' => 30,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'local_cert' => base_path('storage/certs/verifactu.pfx'),
            'passphrase' => env('PFX_CERT_PASSWORD', ''),
        ];

        $client = new \SoapClient($wsdl, $options);

        $xml = '<VNifV2Ent><Contribuyente><Nif>' . $nif . '</Nif><Nombre>' . $nombreRazon . '</Nombre></Contribuyente></VNifV2Ent>';
        $params = [new \SoapVar($xml, XSD_ANYXML)];

        try {
            $result = $client->__soapCall('VNifV2', [$params]);
            return $result;
        } catch (\SoapFault $e) {
            return 'SOAP Fault: ' . $e->getMessage();
        }
    }
}
