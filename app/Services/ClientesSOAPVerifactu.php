<?php


namespace App\Services;

class ClientesSOAPVeriFactu
{
    public function enviar(string $xml, bool $modoPruebas = true): string
    {
        $wsdl = $modoPruebas
            ? 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP'
            : 'https://www1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

        $options = [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' =>  0, //\WSDL_CACHE_NONE,
            'connection_timeout' => 30,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'local_cert' => base_path('storage/certs/verifactu.pfx'),
            'passphrase' => env('PFX_CERT_PASSWORD', ''),
           // 'private_key' => env('PFX_CERT_PASSWORD', ''),
        ];

        $client = new \SoapClient(null, array_merge($options, [
            'location' => $wsdl,
            'uri' => 'verifactu.aeat.es'
        ]));

        $params = [
            'xmlFactura' => $xml
        ];

        try {
            $result = $client->__soapCall('verifactuEnviar', [$params]);
            return $result;
        } catch (\SoapFault $e) {
            return 'SOAP Fault: ' . $e->getMessage();
        }
    }
}
