<?php

namespace App\Services;

use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

class FirmaXmlGenerator
{
    public function firmaXml(string $xmlContent, string $cif, string $passwordCert)
{
    // 2) Definir rutas de los .pem en storage
    $keyPath  = storage_path("certs/{$cif}/key.pem");
    $certPath = storage_path("certs/{$cif}/cert.pem");

    if (!file_exists($keyPath) || !file_exists($certPath)) {
        throw new \Exception("No se encontraron los archivos PEM para el CIF {$cif}");
    }

    // 3) Leer clave privada y certificado
    $privateKeyContent = file_get_contents($keyPath);
    $publicCertContent = file_get_contents($certPath);

    if ($privateKeyContent === false || $publicCertContent === false) {
        throw new \Exception("Error al leer los archivos PEM para el CIF {$cif}");
    }

    // 4) Cargar el XML
    $doc = new \DOMDocument();
    $doc->loadXML($xmlContent);

    // 5) Configurar la firma
    $objDSig = new XMLSecurityDSig();
    $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
    $objDSig->addReference(
        $doc,
        XMLSecurityDSig::SHA256,
        ['http://www.w3.org/2000/09/xmldsig#enveloped-signature']
    );

    // 6) Crear la clave privada y cargarla con su contraseña
    $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
    $objKey->loadKey($privateKeyContent, false, false, $passwordCert);

    // 7) Firmar y añadir certificado
    $objDSig->sign($objKey);
    $objDSig->add509Cert($publicCertContent);
    $objDSig->appendSignature($doc->documentElement);

    // 8) Devolver XML firmado
    return $doc->saveXML();
}

}