<?php


namespace App\Services;

class ClientesSOAPVerifactu
{
    public function enviarFacturaAEAT(string $xmlFactura): string
    {

      
        $wsdl = "https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl";

        $options = [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ];

        $client = new \SoapClient($wsdl, $options);

        $xmlObject = simplexml_load_string($xmlFactura);
        $namespaces = $xmlObject->getNamespaces(true);



        $params = [
            'xmlFactura' => $namespaces
        ];

        try {
            $result = $client->__soapCall('verifactuEnviar', [$params]);
            return $result;
        } catch (\SoapFault $e) {
            return 'SOAP Fault: ' . $e->getMessage();
        }
    }
}
