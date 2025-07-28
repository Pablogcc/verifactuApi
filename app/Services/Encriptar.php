<?php

namespace App\Services;

class Encriptar
{

    /**
     * Método para desencriptar un Base64 que contiene IV + ciphertext
     * 
     * @param string $encryptedBase64
     * @param string $cif
     * @return array
     */
    public function decryptBase64AndDownloadPfx($passwordCert, $encryptedBase64, $cif)
    {

        // 1) Decodificar el Base64 que contiene IV + ciphertext
        $full = base64_decode($encryptedBase64);
        if ($full === false) {
            http_response_code(400);
            echo "Error: Base64 inválido.";
            return;
        }

        // 2) Separar IV (primeros 16 bytes) y ciphertext (resto)
        $iv = substr($full, 0, 16);
        $ciphertext = substr($full, 16);

        // 3) Derivar clave con SHA-256 de la contraseña (igual que en Dart)
        $password = 'verifactu1234';
        $key = hash('sha256', $password, true); // raw output

        // 4) Desencriptar con openssl
        $decryptedBase64 = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($decryptedBase64 === false) {
            http_response_code(500);
            echo "Error al desencriptar.";
            return;
        }

        // 5) Decodificar Base64 para obtener el binario original del archivo .pfx
        $binaryContent = base64_decode($decryptedBase64);
        if ($binaryContent === false) {
            http_response_code(500);
            echo "Error al decodificar contenido original.";
            return;
        }
        // 6) Convertir el .pfx a .pem usando openssl_pkcs12_read
        $certs = [];
        $pfxPassword = $passwordCert; // Pon la contraseña del PFX si tiene una
        $ok = openssl_pkcs12_read($binaryContent, $certs, $pfxPassword);
        if (!$ok) {
            http_response_code(500);
            echo "Error al leer el archivo .pfx con openssl_pkcs12_read.";
            return;
        }

        // 7) Separar clave privada, certificado y CA (si existen)
        $privateKeyContent = $certs['pkey'];
        $certContent = $certs['cert'];
        $caContent = '';

        if (!empty($certs['extracerts'])) {
            foreach ($certs['extracerts'] as $ca) {
                $caContent .= "\n" . $ca;
            }
        }

        // 8) Guardar por separado key.pem y cert.pem
        $uploadDir = storage_path('certs/' . $cif . '/');

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $keyPath = $uploadDir . 'key.pem';
        $certPath = $uploadDir . 'cert.pem';

        if (file_put_contents($keyPath, $privateKeyContent) === false) {
            http_response_code(500);
            echo "Error al guardar key.pem";
            return;
        }

        if (file_put_contents($certPath, $certContent . $caContent) === false) {
            http_response_code(500);
            echo "Error al guardar cert.pem";
            return;
        }

        // Puedes retornar las rutas si las necesitas
        return [
            'key' => $keyPath,
            'cert' => $certPath
        ];
    }

    /**
     * Método similar pero para encriptar un simple string
     * 
     * @param string $string
     * @return string
     */
    public function encryptString($string)
    {
        $password = 'verifactu1234';
        $key = hash('sha256', $password, true); // raw output
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encryptedString = openssl_encrypt($string, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encryptedString);
    }

    /**
     * Método similar pero para desencriptar un simple string
     * 
     * @param string $encryptedString
     * @return string
     */
    public function decryptString($encryptedString)
    {
        // 1) Decodificar el Base64 que contiene IV + ciphertext
        $full = base64_decode($encryptedString);
        if ($full === false) {
            http_response_code(400);
            echo "Error: Base64 inválido.";
            return;
        }

        // 2) Separar IV (primeros 16 bytes) y ciphertext (resto)
        $iv = substr($full, 0, 16);
        $ciphertext = substr($full, 16);

        // 3) Derivar clave con SHA-256 de la contraseña (igual que en Dart)
        $password = 'verifactu1234';
        $key = hash('sha256', $password, true); // raw output

        // 4) Desencriptar con openssl
        $decryptedString = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($decryptedString === false) {
            http_response_code(500);
            echo "Error al desencriptar.";
            return;
        }

        return $decryptedString;
    }
}
