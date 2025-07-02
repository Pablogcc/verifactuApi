<?php

namespace App\Services;

use App\Models\Facturas;
use App\Services\DOMDocument;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

class FirmaXmlGenerator
{

    public function firmaXml(string $xmlContent)
    {
        //Guardamos el certificado y su contraseña, y comprobamos que se pueda leer
        $pfxPath = storage_path(env('PFX_CERT_PATH'));
        $pfxPassword = env('PFX_CERT_PASSWORD');
        $certStore = [];

        if (!openssl_pkcs12_read(file_get_contents($pfxPath), $certStore, $pfxPassword)) {
            throw new \Exception("Error al leer el archivo");
        }

        //Cargamos el XML y creamos un nuevo Security Object
        $privateKey = $certStore['pkey'];
        $publicCert = $certStore['cert'];

        $doc = new \DOMDocument();
        $doc->loadXML($xmlContent);
        //Quitamos los saltos de línea, espacios, orden de atributos. Así normaliza el XML y no lo deja inválido
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $objDSig->addReference(
            $doc,
            XMLSecurityDSig::SHA256,
            array('http://www.w3.org/2000/09/xmldsig#enveloped-signature')
        );

        //Creamos una nueva (private) Security key
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type' => 'private'));
        //Cargamos la private key
        $objKey->loadKey($privateKey, false);
        //Aquí cargamos el XML y lo firmamos
        $objDSig->sign($objKey);
        $objDSig->add509Cert($publicCert);
        $objDSig->appendSignature($doc->documentElement);

        //Y devlovemos el XML firmado
        return $doc->saveXML();
    }
}
