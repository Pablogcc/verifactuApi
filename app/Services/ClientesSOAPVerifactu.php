<?php


namespace App\Services;
use Illuminate\Support\Facades\Http;


class ClientesSOAPVerifactu
{


    protected string $endpoint;

    public function __construct()
    {
        $this->endpoint = "https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP";

    }

    

    public function enviarFactura(string $xmlFirmado)
    {

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => ''
        ])->withBody($xmlFirmado, 'text/xml')
        ->post($this->endpoint);

        if (!$response->successful()) {
            throw new \Exception("Error AEAT: HTTP " . $response->status() . "\n" . $response->body());
        }

        return $response->body();

    }
}
