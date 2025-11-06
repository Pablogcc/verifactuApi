<?php

namespace App\Services;

class ClientesSOAPConsultaVIES
{

    public function consultar(string $vat): string|array
    {
        $vat = strtoupper(trim($vat));
        $countryCode = substr($vat, 0, 2);
        $vatNumber = substr($vat, 2);

        // Si las dos primeras "letras" no son alfabéticas, asumimos que el usuario pasó countryCode y vatNumber juntos con un separador
        // (pero en tu caso normalmente vendrá con prefijo, así que esto cubre la mayoría de casos).
        if (!ctype_alpha($countryCode)) {
            // intentar separar por espacio o guión: "ES B54027545" o "ES-B54027545"
            $parts = preg_split('/[\s\-]+/', $vat, 2);
            if (count($parts) === 2) {
                $countryCode = strtoupper($parts[0]);
                $vatNumber = strtoupper($parts[1]);
            } else {
                // fallback: devuelvo error
                return response()->json([
                    'error' => 'Formato VAT inválido. Debe incluir el prefijo país '
                ]);
            }
        }

        // Endpoint público del servicio VIES (WSDL existe en la UE)
        $url = "https://ec.europa.eu/taxation_customs/vies/services/checkVatService";

        // Construimos el XML SOAP (no tocar estructura)
        $xml = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:urn="urn:ec.europa.eu:taxud:vies:services:checkVat:types">
  <soapenv:Header/>
  <soapenv:Body>
    <urn:checkVat>
      <urn:countryCode>{$countryCode}</urn:countryCode>
      <urn:vatNumber>{$vatNumber}</urn:vatNumber>
    </urn:checkVat>
  </soapenv:Body>
</soapenv:Envelope>
XML;

        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'Content-Length: ' . strlen($xml),
        ];

        // Preparamos el cURL para enviar la solicitud
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // Opciones de seguridad y tiempo estimado para la enviar la solicitud
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);

        if ($response === false) {
            $err = curl_error($ch);
            $code = curl_errno($ch);
            curl_close($ch);
            return response()->json([
                'error' => $err,
                'code' => $code
            ]);
        }

        // Obtenemos el código de respuesta HTTP
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Para detectar errores del servicio si algo sale mal (opcional)
        if ($httpStatus >= 400) {
            return response()->json([
                'error' => 'HTTP ' . $httpStatus,
                'raw' => $response
            ]);
        }

        // Devuelve la respuesta SOAP completa (xml) — igual forma que tu otro servicio
        return $response;
    }
}
