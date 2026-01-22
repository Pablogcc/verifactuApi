<?php

namespace App\Services;

use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

class FirmaXmlGeneratorElectronica
{
    public function firmaXml(string $xmlContent, string $cif, string $passwordCert): string
    {
        $keyPath  = storage_path("certs/{$cif}/key.pem");
        $certPath = storage_path("certs/{$cif}/cert.pem");

        $privateKeyPem = @file_get_contents($keyPath);
        $publicCertPem = @file_get_contents($certPath);

        if ($privateKeyPem === false || $publicCertPem === false) {
            throw new \Exception("No se pudo leer key.pem o cert.pem");
        }

        $pkey = openssl_pkey_get_private($privateKeyPem, $passwordCert);
        if ($pkey === false) {
            throw new \Exception("No se pudo abrir la clave privada (passphrase incorrecta o PEM inválido)");
        }

        if (!preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $publicCertPem, $m)) {
            throw new \Exception("No se pudo extraer el certificado del PEM");
        }
        $certB64 = preg_replace('/\s+/', '', $m[1]);
        $certDer = base64_decode($certB64, true);
        if ($certDer === false) {
            throw new \Exception("Certificado Base64 inválido");
        }

        $certParsed = openssl_x509_parse($publicCertPem);
        if (!$certParsed || empty($certParsed['issuer']) || !isset($certParsed['serialNumber'])) {
            throw new \Exception("No se pudo parsear el certificado");
        }

        // Issuer: CN=...,OU=...,O=...,L=...,ST=...,C=...
        $dnOrder = ['CN', 'OU', 'O', 'L', 'ST', 'C'];
        $issuerParts = [];
        foreach ($dnOrder as $k) {
            if (!empty($certParsed['issuer'][$k])) {
                $issuerParts[] = $k . '=' . $certParsed['issuer'][$k];
            }
        }
        $issuerNameStr = implode(',', $issuerParts);
        $serialNumber  = (string) $certParsed['serialNumber'];

        // XML
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        $doc->loadXML($xmlContent);

        // Evitar Id en root
        $doc->documentElement->removeAttribute('Id');

        // Namespaces como en el ejemplo
        $dsNS   = 'http://www.w3.org/2000/09/xmldsig#';
        $etsiNS = 'http://uri.etsi.org/01903/v1.3.2#';

        // IDs tipo ejemplo
        $rnd = fn(int $n = 6) => (string) random_int(10 ** ($n - 1), (10 ** $n) - 1);

        $sigBaseId        = 'Signature-' . $rnd();
        $sigId            = $sigBaseId . '-Signature';
        $signedInfoId     = $sigBaseId . '-SignedInfo';
        $signedPropsId    = $sigBaseId . '-SignedProperties';
        $keyInfoId        = $sigBaseId . '-KeyInfo';
        $docRefId         = 'Reference-' . $rnd();
        $sigValueId       = $sigBaseId . '-SignatureValue';

        // 1) Firmado base con xmlseclibs (RSA-SHA1)
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);

        // Referencia inicial al documento para que cree la estructura Signature
        $objDSig->addReference(
            $doc->documentElement,
            XMLSecurityDSig::SHA1,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['uri' => '']
        );

        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $objKey->passphrase = $passwordCert;
        $objKey->loadKey($privateKeyPem, false, false);

        $objDSig->sign($objKey);
        $objDSig->add509Cert($certB64, false, false);
        $objDSig->appendSignature($doc->documentElement);

        /** @var \DOMElement $sigNode */
        $sigNode = $doc->getElementsByTagName('Signature')->item(0);
        if (!$sigNode instanceof \DOMElement) {
            throw new \Exception("No se generó ds:Signature");
        }

        // En tu ejemplo: ds:Signature Id="Signature..."
        $sigNode->setAttribute('Id', $sigId);

        /** @var \DOMElement $signedInfo */
        $signedInfo = $sigNode->getElementsByTagName('SignedInfo')->item(0);
        if (!$signedInfo instanceof \DOMElement) {
            throw new \Exception("No se encontró ds:SignedInfo");
        }
        $signedInfo->setAttribute('Id', $signedInfoId);

        /** @var \DOMElement $sigValueNode */
        $sigValueNode = $sigNode->getElementsByTagName('SignatureValue')->item(0);
        if (!$sigValueNode instanceof \DOMElement) {
            throw new \Exception("No se encontró ds:SignatureValue");
        }
        $sigValueNode->setAttribute('Id', $sigValueId);

        /** @var \DOMElement $keyInfo */
        $keyInfo = $sigNode->getElementsByTagName('KeyInfo')->item(0);
        if (!$keyInfo instanceof \DOMElement) {
            throw new \Exception("No se encontró ds:KeyInfo");
        }
        $keyInfo->setAttribute('Id', $keyInfoId);

        // 2) Ajustar X509Certificate con saltos y asegurarnos de que está dentro de X509Data
        $x509Data = $keyInfo->getElementsByTagName('X509Data')->item(0);
        if (!$x509Data instanceof \DOMElement) {
            throw new \Exception("No se encontró ds:X509Data dentro de KeyInfo");
        }

        $x509CertNodes = $x509Data->getElementsByTagName('X509Certificate');
        if ($x509CertNodes->length === 0) {
            throw new \Exception("No se encontró ds:X509Certificate dentro de X509Data");
        }
        /** @var \DOMElement $x509CertEl */
        $x509CertEl = $x509CertNodes->item(0);

        while ($x509CertEl->firstChild) {
            $x509CertEl->removeChild($x509CertEl->firstChild);
        }
        $this->appendWrappedBase64($doc, $x509CertEl, $certB64);

        // 3) Insertar KeyValue antes de X509Data (como en la factura buena)
        $pubKeyDetails = openssl_pkey_get_details(openssl_pkey_get_public($publicCertPem));
        if (empty($pubKeyDetails['rsa']['n']) || empty($pubKeyDetails['rsa']['e'])) {
            throw new \Exception("No se pudieron obtener parámetros RSA públicos del certificado");
        }

        $modulusB64  = base64_encode($pubKeyDetails['rsa']['n']);
        $exponentB64 = base64_encode($pubKeyDetails['rsa']['e']);

        $keyValue = $doc->createElementNS($dsNS, 'ds:KeyValue');
        $rsaKey   = $doc->createElementNS($dsNS, 'ds:RSAKeyValue');

        $modEl = $doc->createElementNS($dsNS, 'ds:Modulus');
        $this->appendWrappedBase64($doc, $modEl, $modulusB64);

        $expEl = $doc->createElementNS($dsNS, 'ds:Exponent', $exponentB64);

        $rsaKey->appendChild($modEl);
        $rsaKey->appendChild($expEl);
        $keyValue->appendChild($rsaKey);

        // aquí forzamos el orden: KeyValue primero, luego X509Data
        $keyInfo->insertBefore($keyValue, $x509Data);


        // 4) Crear ds:Object + xades:QualifyingProperties
        $certDigestB64 = base64_encode(hash('sha1', $certDer, true));

        $objectNode = $doc->createElementNS($dsNS, 'ds:Object');

        $qualProps = $doc->createElementNS($etsiNS, 'xades:QualifyingProperties');
        $qualProps->setAttribute('Id', $sigBaseId . '-QualifyingProperties');
        $qualProps->setAttribute('Target', "#{$sigId}");

        $etsiSignedProps = $doc->createElementNS($etsiNS, 'xades:SignedProperties');
        $etsiSignedProps->setAttribute('Id', $signedPropsId);

        $etsiSignedSigProps = $doc->createElementNS($etsiNS, 'xades:SignedSignatureProperties');

        // SigningTime
        $now = new \DateTime();
        $etsiSignedSigProps->appendChild(
            $doc->createElementNS($etsiNS, 'xades:SigningTime', $now->format('Y-m-d\TH:i:sP'))
        );

        // SigningCertificate
        $signingCert = $doc->createElementNS($etsiNS, 'xades:SigningCertificate');
        $certEl      = $doc->createElementNS($etsiNS, 'xades:Cert');

        $certDigest  = $doc->createElementNS($etsiNS, 'xades:CertDigest');
        $dm = $doc->createElementNS($dsNS, 'ds:DigestMethod');
        $dm->setAttribute('Algorithm', $dsNS . 'sha1');
        $dv = $doc->createElementNS($dsNS, 'ds:DigestValue', $certDigestB64);
        $certDigest->appendChild($dm);
        $certDigest->appendChild($dv);

        $issuerSerial = $doc->createElementNS($etsiNS, 'xades:IssuerSerial');
        $issuerSerial->appendChild($doc->createElementNS($dsNS, 'ds:X509IssuerName', $issuerNameStr));
        $issuerSerial->appendChild($doc->createElementNS($dsNS, 'ds:X509SerialNumber', $serialNumber));

        $certEl->appendChild($certDigest);
        $certEl->appendChild($issuerSerial);
        $signingCert->appendChild($certEl);
        $etsiSignedSigProps->appendChild($signingCert);

        $policyUrl = 'http://www.facturae.es/politica_de_firma_formato_facturae/politica_de_firma_formato_facturae_v3_1.pdf';
        $policySha1B64 = 'Ohixl6upD6av8N7pEvDABhEL6hM=';

        $spi  = $doc->createElement('xades:SignaturePolicyIdentifier');
        $spid = $doc->createElement('xades:SignaturePolicyId');

        $sigPolicyIdNode = $doc->createElement('xades:SigPolicyId');
        $sigPolicyIdNode->appendChild($doc->createElement('xades:Identifier', $policyUrl));
        $sigPolicyIdNode->appendChild($doc->createElement('xades:Description', 'facturae31'));

        $sigPolicyHash = $doc->createElement('xades:SigPolicyHash');

        $pdm = $doc->createElement('ds:DigestMethod');
        $pdm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $pdv = $doc->createElement('ds:DigestValue', $policySha1B64);

        $sigPolicyHash->appendChild($pdm);
        $sigPolicyHash->appendChild($pdv);

        $spid->appendChild($sigPolicyIdNode);
        $spid->appendChild($sigPolicyHash);

        $spi->appendChild($spid);
        $etsiSignedSigProps->appendChild($spi);

        // SignerRole
        $signerRole = $doc->createElementNS($etsiNS, 'xades:SignerRole');
        $claimedRoles = $doc->createElementNS($etsiNS, 'xades:ClaimedRoles');
        $claimedRoles->appendChild($doc->createElementNS($etsiNS, 'xades:ClaimedRole', 'emisor'));
        $signerRole->appendChild($claimedRoles);
        $etsiSignedSigProps->appendChild($signerRole);

        $etsiSignedProps->appendChild($etsiSignedSigProps);
        $qualProps->appendChild($etsiSignedProps);
        $objectNode->appendChild($qualProps);

        // Adjuntar ds:Object al ds:Signature
        $sigNode->appendChild($objectNode);

        // 5) Recalcular digests (SHA1) y reconstruir SignedInfo con el ORDEN del ejemplo bueno:
        //   (1) Documento (URI=""), (2) SignedProperties, (3) Certificate(KeyInfo)
        $signedPropsC14N = $etsiSignedProps->C14N(false, false);
        $signedPropsDigestB64 = base64_encode(hash('sha1', $signedPropsC14N, true));

        $keyInfoC14N = $keyInfo->C14N(false, false);
        $keyInfoDigestB64 = base64_encode(hash('sha1', $keyInfoC14N, true));

        // Digest del documento (root sin Signature)
        $tmpDoc = new \DOMDocument();
        $tmpDoc->preserveWhiteSpace = false;
        $tmpDoc->formatOutput = false;
        $tmpDoc->loadXML($doc->saveXML($doc->documentElement));

        $tmpRoot = $tmpDoc->documentElement;
        $tmpSig  = $tmpRoot->getElementsByTagName('Signature')->item(0);
        if ($tmpSig instanceof \DOMElement) {
            $tmpRoot->removeChild($tmpSig);
        }
        $docDigestB64 = base64_encode(hash('sha1', $tmpDoc->C14N(false, false), true));

        // Limpiar SignedInfo
        while ($signedInfo->firstChild) $signedInfo->removeChild($signedInfo->firstChild);

        // CanonicalizationMethod
        $canon = $doc->createElementNS($dsNS, 'ds:CanonicalizationMethod');
        $canon->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canon);

        // SignatureMethod
        $sm = $doc->createElementNS($dsNS, 'ds:SignatureMethod');
        $sm->setAttribute('Algorithm', $dsNS . 'rsa-sha1');
        $signedInfo->appendChild($sm);

        // (1) Reference Documento (URI="")
        $refDoc = $doc->createElementNS($dsNS, 'ds:Reference');
        $refDoc->setAttribute('Id', $docRefId);
        $refDoc->setAttribute('URI', '');

        $transforms = $doc->createElementNS($dsNS, 'ds:Transforms');
        $t = $doc->createElementNS($dsNS, 'ds:Transform');
        $t->setAttribute('Algorithm', $dsNS . 'enveloped-signature');
        $transforms->appendChild($t);
        $refDoc->appendChild($transforms);

        $dmDoc = $doc->createElementNS($dsNS, 'ds:DigestMethod');
        $dmDoc->setAttribute('Algorithm', $dsNS . 'sha1');
        $dvDoc = $doc->createElementNS($dsNS, 'ds:DigestValue', $docDigestB64);

        $refDoc->appendChild($dmDoc);
        $refDoc->appendChild($dvDoc);
        $signedInfo->appendChild($refDoc);

        // (2) Reference SignedProperties
        $refSP = $doc->createElementNS($dsNS, 'ds:Reference');
        $refSP->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $refSP->setAttribute('URI', "#$signedPropsId");

        $dmSP = $doc->createElementNS($dsNS, 'ds:DigestMethod');
        $dmSP->setAttribute('Algorithm', $dsNS . 'sha1');
        $dvSP = $doc->createElementNS($dsNS, 'ds:DigestValue', $signedPropsDigestB64);

        $refSP->appendChild($dmSP);
        $refSP->appendChild($dvSP);
        $signedInfo->appendChild($refSP);

        // (3) Reference KeyInfo (Certificate...)
        $refKI = $doc->createElementNS($dsNS, 'ds:Reference');
        $refKI->setAttribute('URI', "#$keyInfoId");

        $dmKI = $doc->createElementNS($dsNS, 'ds:DigestMethod');
        $dmKI->setAttribute('Algorithm', $dsNS . 'sha1');
        $dvKI = $doc->createElementNS($dsNS, 'ds:DigestValue', $keyInfoDigestB64);

        $refKI->appendChild($dmKI);
        $refKI->appendChild($dvKI);
        $signedInfo->appendChild($refKI);

        // 6) Recalcular SignatureValue firmando C14N(SignedInfo) con SHA1
        $signedInfoC14N = $signedInfo->C14N(false, false);
        $signatureBin = '';
        if (!openssl_sign($signedInfoC14N, $signatureBin, $pkey, OPENSSL_ALGO_SHA1)) {
            throw new \Exception("Error al recalcular SignatureValue (SHA1)");
        }

        while ($sigValueNode->firstChild) $sigValueNode->removeChild($sigValueNode->firstChild);
        $this->appendWrappedBase64($doc, $sigValueNode, base64_encode($signatureBin), 76); // 76 suele verse en ejemplos

        // De nuevo: root sin Id
        $doc->documentElement->removeAttribute('Id');

        return $doc->saveXML();
    }

    private function appendWrappedBase64(\DOMDocument $doc, \DOMElement $element, string $b64, int $chunkLen = 64): void
    {
        $b64 = preg_replace('/\s+/', '', trim($b64));
        if ($b64 === '') return;

        $chunks = str_split($b64, $chunkLen);
        $last = count($chunks) - 1;

        foreach ($chunks as $i => $chunk) {
            $text = ($i === $last) ? $chunk : ($chunk . "\n");
            $element->appendChild($doc->createTextNode($text));
        }
    }
}
