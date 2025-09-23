<?php

namespace App\Services;

use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

class FirmaXmlGeneratorElectronica
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
        // Prueba
        $doc = new \DOMDocument();
        $doc->loadXML($xmlContent);

        // ---
        $matches = [];
        preg_match_all(
            '/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s',
            $publicCertContent,
            $matches
        );

        if (empty($matches[1][0])) {
            throw new \Exception("No se pudo extraer el certificado principal de {$certPath}");
        }

        $certFirma = preg_replace('/\s+/', '', $matches[1][0]);

        // 4) Cargar el XML
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        $doc->loadXML($xmlContent);

        // 5) Configurar la firma
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $objDSig->addReference(
            $doc,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['uri' => '']
        );

        // 6) Crear la clave privada y cargarla con su contraseña
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($privateKeyContent, false, false, $passwordCert);

        // 7) Firmar y añadir certificado
        $objDSig->sign($objKey);
        $objDSig->add509Cert($certFirma, false, false);
        $objDSig->appendSignature($doc->documentElement);

        // --- Añadir política de firma obligatoria XAdES ---
        $sigNode = $doc->getElementsByTagName('Signature')->item(0);

        $qualifyingProps = $doc->createElementNS(
            'http://uri.etsi.org/01903/v1.3.2#',
            'xades:QualifyingProperties'
        );
        $qualifyingProps->setAttribute('Target', '#' . $sigNode->getAttribute('Id'));

        $signedProps = $doc->createElement('xades:SignedProperties');
        $signedProps->setAttribute('Id', 'SignedProperties');

        $signedSigProps = $doc->createElement('xades:SignedSignatureProperties');

        // Política de firma
        $sigPolicyId = $doc->createElement('xades:SignaturePolicyIdentifier');
        $sigPolicyIdEl = $doc->createElement('xades:SignaturePolicyId');
        $sigId = $doc->createElement('xades:SigPolicyId');
        $identifier = $doc->createElement('xades:Identifier', 'http://www.facturae.gob.es/politica_de_firma_formato_facturae/politica_de_firma_formato_facturae_v3_1.pdf');
        $identifier->setAttribute('Qualifier', 'OIDAsURI');
        $sigId->appendChild($identifier);
        $sigPolicyIdEl->appendChild($sigId);

        $hash = $doc->createElement('xades:SigPolicyHash');
        $digestMethod = $doc->createElement('ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $digestValue = $doc->createElement('ds:DigestValue', 'Ohixl6upD6av8N7pEvDABhEL6hM=');
        $hash->appendChild($digestMethod);
        $hash->appendChild($digestValue);

        $sigPolicyIdEl->appendChild($hash);
        $sigPolicyId->appendChild($sigPolicyIdEl);
        $signedSigProps->appendChild($sigPolicyId);

        $signedProps->appendChild($signedSigProps);
        $qualifyingProps->appendChild($signedProps);

        $objectNode = $doc->createElement('ds:Object');
        $objectNode->appendChild($qualifyingProps);
        $sigNode->appendChild($objectNode);
        // --- Fin de política de firma ---

        // 8) Devolver XML firmado
        return $doc->saveXML();
    }
}
