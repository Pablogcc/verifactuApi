<?php


namespace App\Services;

class ClienteSOAPConsultaCDI
{

    public function consultar(string $xml, bool $modoPruebas = true): string
    {
        $wsdl = $modoPruebas
            ? 'https://prewww1.aeat.es/wlpl/SSR/CdiConsultaDatosIdentificacion.wsdl'
            : 'https://www1.aeat.es/wlpl/SSR/CdiConsultaDatosIdentificacion.wsdl';

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

        $params = [
            'xmlConsulta' => $xml
        ];

        try {
            $result = $client->__soapCall('consultaCDI', [$params]);
            return $result;
        } catch (\SoapFault $e) {
            return 'SOAP Fault: ' . $e->getMessage();
        }
    }
}
