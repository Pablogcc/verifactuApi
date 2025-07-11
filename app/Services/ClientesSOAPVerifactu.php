<?php


namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;


class ClientesSOAPVerifactu
{
    protected string $endpoint;
    protected string $certPath;
    protected string $certPassword;
    protected string $keyPem;
    protected string $crtPem;
    protected string $pass;

    public function __construct()
    {
        $this->endpoint = "https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP";
        //$this->certPath = storage_path(env('PFX_CERT_PATH'));
        //$this->certPassword = env('PFX_CERT_PASSWORD');
        $this->keyPem = base_path("storage/certs/verifactu-key.pem");
        $this->crtPem = base_path("storage/certs/verifactu-cert.pem");
        $this->pass = env('PFX_CERT_PASSWORD');
    }



    public function enviarFactura(string $xml)
    {
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => '',
            'Content-Length: ' . strlen($xml)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

       
        curl_setopt($ch, CURLOPT_SSLCERT, $this->crtPem);
        curl_setopt($ch, CURLOPT_SSLKEY, $this->keyPem);
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->pass);
        curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $this->pass);

        // Seguridad
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return response()->json([
                'status' => 'error',
                'message' => "Error de conexión con la AEAT: $error"
            ], 500);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return response()->json([
                'status' => 'error',
                'message' => "Respuesta HTTP no exitosa: $httpCode",
                'body' => $response
            ], $httpCode);
        }

        return response()->json([
            'status' => 'ok',
            'response' => $response
        ]);

    }
}
