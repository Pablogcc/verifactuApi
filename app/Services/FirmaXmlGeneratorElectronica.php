<?php

namespace App\Services;

use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

class FirmaXmlGeneratorElectronica
{
    /**
     * Firma un XML Facturae con XAdES-BES usando el certificado del emisor.
     *
     * @param string $xmlContent   XML de factura SIN firmar.
     * @param string $cif          CIF del emisor (carpeta donde están key.pem y cert.pem).
     * @param string $passwordCert Contraseña de la clave privada.
     *
     * @return string XML firmado.
     * @throws \Exception
     */
    public function firmaXml(string $xmlContent, string $cif, string $passwordCert): string
    {
        $keyPath  = storage_path("certs/{$cif}/key.pem");
        $certPath = storage_path("certs/{$cif}/cert.pem");
        $pfxPath  = storage_path("certs/{$cif}/temp_cert.pfx");

        // Cargar clave privada y certificado en PEM
        $privateKeyContent = file_get_contents($keyPath);
        $publicCertContent = file_get_contents($certPath);

        $certData = openssl_x509_read($publicCertContent);
        $pkeyData = openssl_pkey_get_private($privateKeyContent, $passwordCert);

        if (!$certData || !$pkeyData) {
            throw new \Exception("Error leyendo certificado o clave privada");
        }

        // Exportar PFX temporal en memoria y en disco
        $pfxExport = '';
        if (!openssl_pkcs12_export($certData, $pfxExport, $pkeyData, $passwordCert)) {
            throw new \Exception("Error generando PFX temporal");
        }
        file_put_contents($pfxPath, $pfxExport);

        // Leer PFX para extraer clave y certificado
        $certs = [];
        if (!openssl_pkcs12_read($pfxExport, $certs, $passwordCert)) {
            throw new \Exception("No se pudo leer el archivo PFX temporal");
        }

        $privateKeyContent = $certs['pkey'];
        $publicCertContent = $certs['cert'];

        // Extraer sólo el certificado base64 sin cabeceras (para X509Certificate y digest)
        preg_match_all('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $publicCertContent, $matches);
        $certFirma = preg_replace('/\s+/', '', $matches[1][0] ?? '');

        // Cargar XML de factura
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        $doc->loadXML($xmlContent);

        // Aseguramos que el nodo raíz Facturae NO tenga atributo Id (no permitido en 3.2.1)
        $doc->documentElement->removeAttribute('Id');

        // IDs únicos para la firma (sólo para nodos de firma)
        $uuid          = uniqid();
        $sigId         = "Signature-$uuid-Signature";
        $signedPropsId = "Signature-$uuid-SignedProperties";
        $qualifyingId  = "Signature-$uuid-QualifyingProperties";
        $keyInfoId     = "Signature-$uuid-KeyInfo";

        // Crear la firma
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);

        // Referencia al documento completo (enveloped signature sobre el root, URI vacía)
        $objDSig->addReference(
            $doc->documentElement,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['uri' => '']
        );

        // Firmar con la clave privada del PFX
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($privateKeyContent, false, false, $passwordCert);

        $objDSig->sign($objKey);
        // Añadimos el certificado en X509 (contenido base64)
        $objDSig->add509Cert($certFirma, false, false);
        $objDSig->appendSignature($doc->documentElement);

        // Añadir información de clave y certificado
        /** @var \DOMElement $sigNode */
        $sigNode = $doc->getElementsByTagName('Signature')->item(0);
        $sigNode->setAttribute('Id', $sigId);

        /** @var \DOMElement $keyInfo */
        $keyInfo = $doc->getElementsByTagName('KeyInfo')->item(0);
        $keyInfo->setAttribute('Id', $keyInfoId);

        // Modulus y Exponent de la clave pública
        $pubKey  = openssl_pkey_get_public($publicCertContent);
        $details = openssl_pkey_get_details($pubKey);
        $modulus  = base64_encode($details['rsa']['n']);
        $exponent = base64_encode($details['rsa']['e']);

        $keyValue = $doc->createElement('ds:KeyValue');
        $rsaKey   = $doc->createElement('ds:RSAKeyValue');
        $rsaKey->appendChild($doc->createElement('ds:Modulus', $modulus));
        $rsaKey->appendChild($doc->createElement('ds:Exponent', $exponent));
        $keyValue->appendChild($rsaKey);
        $keyInfo->insertBefore($keyValue, $keyInfo->firstChild);

        $certParsed = openssl_x509_parse($publicCertContent);

        /**
         * 1) X509IssuerName
         * Debe ser EXACTAMENTE el Issuer del certificado (CA emisora)
         */
        $dnOrder = ['CN', 'OU', 'O', 'L', 'ST', 'C'];
        $issuerParts = [];

        foreach ($dnOrder as $key) {
            if (!empty($certParsed['issuer'][$key])) {
                $issuerParts[] = $key . '=' . $certParsed['issuer'][$key];
            }
        }

        $issuerNameStr = implode(',', $issuerParts);

        /**
         * 2) X509SerialNumber
         * SIEMPRE string, decimal, y sin GMP
         */
        $serialNumber = (string) $certParsed['serialNumber'];


        // Crear QualifyingProperties y SignedProperties (XAdES-BES)
        $certDigestValue = base64_encode(hash('sha256', base64_decode($certFirma), true));
        $qualifyingProps = $doc->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:QualifyingProperties');
        $qualifyingProps->setAttribute('Id', $qualifyingId);
        $qualifyingProps->setAttribute('Target', "#$sigId");

        $signedProps = $doc->createElement('xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        $signedSigProps = $doc->createElement('xades:SignedSignatureProperties');
        $signingTime    = $doc->createElement('xades:SigningTime', gmdate('Y-m-d\TH:i:s\Z'));
        $signedSigProps->appendChild($signingTime);

        // xades:SigningCertificate
        $signingCert  = $doc->createElement('xades:SigningCertificate');
        $certEl       = $doc->createElement('xades:Cert');
        $certDigest   = $doc->createElement('xades:CertDigest');
        $dm           = $doc->createElement('ds:DigestMethod');
        $dm->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $dv           = $doc->createElement('ds:DigestValue', $certDigestValue);
        $certDigest->appendChild($dm);
        $certDigest->appendChild($dv);
        $certEl->appendChild($certDigest);

        $issuerSerial = $doc->createElement('xades:IssuerSerial');
        $issuerSerial->appendChild($doc->createElement('ds:X509IssuerName', $issuerNameStr));
        $issuerSerial->appendChild($doc->createElement('ds:X509SerialNumber', $serialNumber));
        $certEl->appendChild($issuerSerial);
        $signingCert->appendChild($certEl);
        $signedSigProps->appendChild($signingCert);

        // Política de firma Facturae 3.1
        $sigPolicyId   = $doc->createElement('xades:SignaturePolicyIdentifier');
        $sigPolicyIdEl = $doc->createElement('xades:SignaturePolicyId');
        $sigIdNode     = $doc->createElement('xades:SigPolicyId');
        $identifier    = $doc->createElement(
            'xades:Identifier',
            'http://www.facturae.es/politica_de_firma_formato_facturae/politica_de_firma_formato_facturae_v3_1.pdf'
        );
        $sigIdNode->appendChild($identifier);
        $sigIdNode->appendChild($doc->createElement('xades:Description', 'facturae31'));
        $sigPolicyIdEl->appendChild($sigIdNode);

        $hash         = $doc->createElement('xades:SigPolicyHash');
        $digestMethod = $doc->createElement('ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        // Huella (SHA-1 en Base64) de la política de firma Facturae v3.1
        $digestValue  = $doc->createElement('ds:DigestValue', 'Ohixl6upD6av8N7pEvDABhEL6hM=');
        $hash->appendChild($digestMethod);
        $hash->appendChild($digestValue);

        $sigPolicyIdEl->appendChild($hash);
        $sigPolicyId->appendChild($sigPolicyIdEl);
        $signedSigProps->appendChild($sigPolicyId);

        // Rol del firmante
        $signerRole   = $doc->createElement('xades:SignerRole');
        $claimedRoles = $doc->createElement('xades:ClaimedRoles');
        $claimedRoles->appendChild($doc->createElement('xades:ClaimedRole', 'emisor'));
        $signerRole->appendChild($claimedRoles);
        $signedSigProps->appendChild($signerRole);

        $signedProps->appendChild($signedSigProps);

        // Opcional: SignedDataObjectProperties para indicar formato del objeto firmado
        $signedDataObjProps = $doc->createElement('xades:SignedDataObjectProperties');
        $dataObjFormat      = $doc->createElement('xades:DataObjectFormat');
        $dataObjFormat->setAttribute('ObjectReference', '#Reference');
        $mimeType           = $doc->createElement('xades:MimeType', 'text/xml');
        $encoding           = $doc->createElement('xades:Encoding', 'UTF-8');
        $dataObjFormat->appendChild($mimeType);
        $dataObjFormat->appendChild($encoding);
        $signedDataObjProps->appendChild($dataObjFormat);

        $signedProps->appendChild($signedDataObjProps);

        $qualifyingProps->appendChild($signedProps);
        $objectNode = $doc->createElement('ds:Object');
        $objectNode->appendChild($qualifyingProps);
        $sigNode->appendChild($objectNode);

        // ------------------------------------------------------------------
        // Referencias XAdES (SignedProperties + KeyInfo) y recálculo firma
        // ------------------------------------------------------------------
        /** @var \DOMElement $signedInfo */
        $signedInfo = $sigNode->getElementsByTagName('SignedInfo')->item(0);

        if ($signedInfo instanceof \DOMElement) {
            // 1) Referencia a SignedProperties (obligatoria para que la política sea válida)
            $signedPropsC14N = $signedProps->C14N(false, false);
            $signedPropsDigest = base64_encode(hash('sha256', $signedPropsC14N, true));

            $refSignedProps = $doc->createElement('ds:Reference');
            $refSignedProps->setAttribute('Id', 'SignedPropertiesID');
            $refSignedProps->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
            $refSignedProps->setAttribute('URI', "#$signedPropsId");

            $spDigestMethod = $doc->createElement('ds:DigestMethod');
            $spDigestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
            $spDigestValue = $doc->createElement('ds:DigestValue', $signedPropsDigest);

            $refSignedProps->appendChild($spDigestMethod);
            $refSignedProps->appendChild($spDigestValue);

            // Insertar la referencia de SignedProperties como primera referencia
            $firstRef = $signedInfo->getElementsByTagName('Reference')->item(0);
            if ($firstRef) {
                $signedInfo->insertBefore($refSignedProps, $firstRef);
            } else {
                $signedInfo->appendChild($refSignedProps);
            }

            // 2) Referencia al KeyInfo (como en el ejemplo oficial)
            $keyInfoC14N = $keyInfo->C14N(false, false);
            $keyInfoDigest = base64_encode(hash('sha256', $keyInfoC14N, true));

            $refKeyInfo = $doc->createElement('ds:Reference');
            $refKeyInfo->setAttribute('URI', "#$keyInfoId");

            $kiDigestMethod = $doc->createElement('ds:DigestMethod');
            $kiDigestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
            $kiDigestValue = $doc->createElement('ds:DigestValue', $keyInfoDigest);

            $refKeyInfo->appendChild($kiDigestMethod);
            $refKeyInfo->appendChild($kiDigestValue);

            $signedInfo->appendChild($refKeyInfo);

            // 3) Recalcular SignatureValue porque hemos cambiado SignedInfo
            $signedInfoC14N = $signedInfo->C14N(false, false);

            $pkeyResource = openssl_pkey_get_private($privateKeyContent, $passwordCert);
            if ($pkeyResource === false) {
                throw new \Exception('No se pudo obtener la clave privada para recalcular la firma');
            }

            $signatureBin = '';
            if (!openssl_sign($signedInfoC14N, $signatureBin, $pkeyResource, OPENSSL_ALGO_SHA256)) {
                throw new \Exception('Error al recalcular SignatureValue');
            }

            /** @var \DOMElement $sigValueNode */
            $sigValueNode = $sigNode->getElementsByTagName('SignatureValue')->item(0);
            if ($sigValueNode) {
                while ($sigValueNode->firstChild) {
                    $sigValueNode->removeChild($sigValueNode->firstChild);
                }
                $sigValueNode->appendChild($doc->createTextNode(base64_encode($signatureBin)));
            }
        }

        if (file_exists($pfxPath)) {
            unlink($pfxPath);
        }

        // Garantizamos de nuevo que el nodo raíz Facturae NO lleve atributo Id
        $doc->documentElement->removeAttribute('Id');

        return $doc->saveXML();
    }
}
