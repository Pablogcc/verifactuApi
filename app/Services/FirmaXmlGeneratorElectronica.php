<?php

namespace App\Services;

use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

class FirmaXmlGeneratorElectronica
{
    public function firmaXml(string $xmlContent, string $cif, string $passwordCert)
    {
        $keyPath  = storage_path("certs/{$cif}/key.pem");
        $certPath = storage_path("certs/{$cif}/cert.pem");
        $pfxPath  = storage_path("certs/{$cif}/temp_cert.pfx");

        // 🔹 Crear temporalmente el .pfx a partir de los .pem
        $privateKeyContent = file_get_contents($keyPath);
        $publicCertContent = file_get_contents($certPath);

        $certData = openssl_x509_read($publicCertContent);
        $pkeyData = openssl_pkey_get_private($privateKeyContent, $passwordCert);

        if (!$certData || !$pkeyData) {
            throw new \Exception("Error leyendo certificado o clave privada");
        }

        // Exportar PFX temporal (no lo guardamos de forma permanente)
        $pfxExport = '';
        if (!openssl_pkcs12_export($certData, $pfxExport, $pkeyData, $passwordCert)) {
            throw new \Exception("Error generando PFX temporal");
        }
        file_put_contents($pfxPath, $pfxExport);

        // 🔹 Cargar desde el .pfx para extraer certificado y clave
        $certs = [];
        if (!openssl_pkcs12_read($pfxExport, $certs, $passwordCert)) {
            throw new \Exception("No se pudo leer el archivo PFX temporal");
        }

        $privateKeyContent = $certs['pkey'];
        $publicCertContent = $certs['cert'];

        // 🔹 Extraer solo el certificado base64 sin cabeceras
        preg_match_all('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $publicCertContent, $matches);
        $certFirma = preg_replace('/\s+/', '', $matches[1][0]);

        // 🔹 Cargar XML
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        $doc->loadXML($xmlContent);

        // 🔹 Preparar IDs únicos
        $uuid = uniqid();
        $sigId         = "Signature-$uuid-Signature";
        $signedPropsId = "Signature-$uuid-SignedProperties";
        $qualifyingId  = "Signature-$uuid-QualifyingProperties";
        $keyInfoId     = "Signature-$uuid-KeyInfo";
        $refId         = "Reference-" . uniqid();

        // 🔹 Crear la firma
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);

        $objDSig->addReference(
            $doc->documentElement,
            XMLSecurityDSig::SHA1,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['uri' => '', 'id' => $refId]
        );

        // 🔹 Firmar con la clave privada del PFX
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $objKey->loadKey($privateKeyContent, false, false, $passwordCert);

        $objDSig->sign($objKey);
        $objDSig->add509Cert($certFirma, false, false);
        $objDSig->appendSignature($doc->documentElement);

        // 🔹 Añadir información de clave y certificado
        $sigNode = $doc->getElementsByTagName('Signature')->item(0);
        $sigNode->setAttribute('Id', $sigId);

        $keyInfo = $doc->getElementsByTagName('KeyInfo')->item(0);
        $keyInfo->setAttribute('Id', $keyInfoId);

        $pubKey = openssl_pkey_get_public($publicCertContent);
        $details = openssl_pkey_get_details($pubKey);
        $modulus  = base64_encode($details['rsa']['n']);
        $exponent = base64_encode($details['rsa']['e']);

        $keyValue = $doc->createElement('ds:KeyValue');
        $rsaKey   = $doc->createElement('ds:RSAKeyValue');
        $rsaKey->appendChild($doc->createElement('ds:Modulus', $modulus));
        $rsaKey->appendChild($doc->createElement('ds:Exponent', $exponent));
        $keyValue->appendChild($rsaKey);
        $keyInfo->insertBefore($keyValue, $keyInfo->firstChild);

        // 🔹 Información del certificado (IssuerSerial)
        $certInfo = openssl_x509_parse($publicCertContent);
        $issuerParts = [];
        foreach (['CN', 'serialNumber', 'OU', 'O', 'C'] as $field) {
            if (isset($certInfo['issuer'][$field])) {
                $val = $certInfo['issuer'][$field];
                if (is_array($val)) {
                    $val = implode('+', $val);
                }
                $issuerParts[] = $field . '=' . $val;
            }
        }
        $issuerNameStr = implode(',', $issuerParts);
        $serialNumber = $certInfo['serialNumber'] ?? '';

        // 🔹 Crear QualifyingProperties y SignedProperties
        $certDigestValue = base64_encode(sha1(base64_decode($certFirma), true));
        $qualifyingProps = $doc->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:QualifyingProperties');
        $qualifyingProps->setAttribute('Id', $qualifyingId);
        $qualifyingProps->setAttribute('Target', "#$sigId");

        $signedProps = $doc->createElement('xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        $signedSigProps = $doc->createElement('xades:SignedSignatureProperties');
        $signingTime = $doc->createElement('xades:SigningTime', gmdate('Y-m-d\TH:i:s\Z'));
        $signedSigProps->appendChild($signingTime);

        $signingCert = $doc->createElement('xades:SigningCertificate');
        $certEl = $doc->createElement('xades:Cert');
        $certDigest = $doc->createElement('xades:CertDigest');
        $dm = $doc->createElement('ds:DigestMethod');
        $dm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $dv = $doc->createElement('ds:DigestValue', $certDigestValue);
        $certDigest->appendChild($dm);
        $certDigest->appendChild($dv);
        $certEl->appendChild($certDigest);

        $issuerSerial = $doc->createElement('xades:IssuerSerial');
        $issuerSerial->appendChild($doc->createElement('ds:X509IssuerName', $issuerNameStr));
        $issuerSerial->appendChild($doc->createElement('ds:X509SerialNumber', $serialNumber));
        $certEl->appendChild($issuerSerial);
        $signingCert->appendChild($certEl);
        $signedSigProps->appendChild($signingCert);

        // Política de firma
        $sigPolicyId = $doc->createElement('xades:SignaturePolicyIdentifier');
        $sigPolicyIdEl = $doc->createElement('xades:SignaturePolicyId');
        $sigIdNode = $doc->createElement('xades:SigPolicyId');
        $identifier = $doc->createElement('xades:Identifier', 'http://www.facturae.es/politica_de_firma_formato_facturae/politica_de_firma_formato_facturae_v3_1.pdf');
        $sigIdNode->appendChild($identifier);
        $sigIdNode->appendChild($doc->createElement('xades:Description', 'facturae31'));
        $sigPolicyIdEl->appendChild($sigIdNode);

        $hash = $doc->createElement('xades:SigPolicyHash');
        $digestMethod = $doc->createElement('ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $digestValue = $doc->createElement('ds:DigestValue', 'ZLxA0XpqFzT1o01gXgh3R4Q4ph8=');
        $hash->appendChild($digestMethod);
        $hash->appendChild($digestValue);

        $sigPolicyIdEl->appendChild($hash);
        $sigPolicyId->appendChild($sigPolicyIdEl);
        $signedSigProps->appendChild($sigPolicyId);

        $signerRole = $doc->createElement('xades:SignerRole');
        $claimedRoles = $doc->createElement('xades:ClaimedRoles');
        $claimedRoles->appendChild($doc->createElement('xades:ClaimedRole', 'emisor'));
        $signerRole->appendChild($claimedRoles);
        $signedSigProps->appendChild($signerRole);

        $signedProps->appendChild($signedSigProps);

        $qualifyingProps->appendChild($signedProps);
        $objectNode = $doc->createElement('ds:Object');
        $objectNode->appendChild($qualifyingProps);
        $sigNode->appendChild($objectNode);

        if (file_exists($pfxPath)) {
            unlink($pfxPath);
        }

        return $doc->saveXML();
    }
}
