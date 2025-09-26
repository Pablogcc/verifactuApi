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

        $privateKeyContent = file_get_contents($keyPath);
        $publicCertContent = file_get_contents($certPath);

        preg_match_all('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $publicCertContent, $matches);
        $certFirma = preg_replace('/\s+/', '', $matches[1][0]);

        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        $doc->loadXML($xmlContent);

        $uuid = uniqid();
        $sigId         = "Signature-$uuid";
        $signedPropsId = "$sigId-SignedProperties";
        $qualifyingId  = "$sigId-QualifyingProperties";
        $keyInfoId     = "$sigId-KeyInfo";

        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);

        $facturaNode = $doc->documentElement;

        $refId = "Reference-" . uniqid();
        $objDSig->addReference(
            $facturaNode,
            XMLSecurityDSig::SHA1,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['uri' => '', 'id' => $refId]
        );

        $qualifyingProps = $doc->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:QualifyingProperties');
        $qualifyingProps->setAttribute('Target', "#$sigId");
        $qualifyingProps->setAttribute('Id', $qualifyingId);

        $signedProps = $doc->createElement('xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        $signedSigProps = $doc->createElement('xades:SignedSignatureProperties');
        $signingTime = $doc->createElement('xades:SigningTime', gmdate('Y-m-d\TH:i:s\Z'));
        $signedSigProps->appendChild($signingTime);

        $certDigestValue = base64_encode(sha1(base64_decode($certFirma), true));
        $signingCert = $doc->createElement('xades:SigningCertificate');
        $certEl = $doc->createElement('xades:Cert');
        $certDigest = $doc->createElement('xades:CertDigest');
        $dm = $doc->createElement('ds:DigestMethod');
        $dm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $dv = $doc->createElement('ds:DigestValue', $certDigestValue);
        $certDigest->appendChild($dm);
        $certDigest->appendChild($dv);
        $certEl->appendChild($certDigest);
        $signingCert->appendChild($certEl);
        $signedSigProps->appendChild($signingCert);

        $sigPolicyId = $doc->createElement('xades:SignaturePolicyIdentifier');
        $sigPolicyIdEl = $doc->createElement('xades:SignaturePolicyId');
        $sigIdNode = $doc->createElement('xades:SigPolicyId');
        $identifier = $doc->createElement('xades:Identifier', 'http://www.facturae.es/politica_de_firma_formato_facturae/politica_de_firma_formato_facturae_v3_1.pdf');
        $sigIdNode->appendChild($identifier);
        $description = $doc->createElement('xades:Description', 'facturae31');
        $sigIdNode->appendChild($description);
        $sigPolicyIdEl->appendChild($sigIdNode);
        $hash = $doc->createElement('xades:SigPolicyHash');
        $digestMethod = $doc->createElement('ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $digestValue = $doc->createElement('ds:DigestValue', 'Ohixl6upD6av8N7pEvDABhEL6hM=');
        $hash->appendChild($digestMethod);
        $hash->appendChild($digestValue);
        $sigPolicyIdEl->appendChild($hash);
        $sigPolicyId->appendChild($sigPolicyIdEl);
        $signedSigProps->appendChild($sigPolicyId);

        $signerRole = $doc->createElement('xades:SignerRole');
        $claimedRoles = $doc->createElement('xades:ClaimedRoles');
        $claimedRole = $doc->createElement('xades:ClaimedRole', 'emisor');
        $claimedRoles->appendChild($claimedRole);
        $signerRole->appendChild($claimedRoles);
        $signedSigProps->appendChild($signerRole);

        $signedProps->appendChild($signedSigProps);

        $signedDataObjProps = $doc->createElement('xades:SignedDataObjectProperties');
        $dataObjFormat = $doc->createElement('xades:DataObjectFormat');
        $dataObjFormat->setAttribute('ObjectReference', "#$refId");
        $objId = $doc->createElement('xades:ObjectIdentifier');
        $objIdentifier = $doc->createElement('xades:Identifier', 'urn:oid:1.2.840.10003.5.109.10');
        $objIdentifier->setAttribute('Qualifier', 'OIDAsURN');
        $objId->appendChild($objIdentifier);
        $objId->appendChild($doc->createElement('xades:Description'));
        $dataObjFormat->appendChild($objId);
        $dataObjFormat->appendChild($doc->createElement('xades:MimeType', 'text/xml'));
        $dataObjFormat->appendChild($doc->createElement('xades:Encoding', 'UTF-8'));
        $signedDataObjProps->appendChild($dataObjFormat);
        $signedProps->appendChild($signedDataObjProps);

        $qualifyingProps->appendChild($signedProps);
        $objectNode = $doc->createElement('ds:Object');
        $objectNode->appendChild($qualifyingProps);

        $objDSig->addReference(
            $signedProps,
            XMLSecurityDSig::SHA1,
            ['http://www.w3.org/2001/10/xml-exc-c14n#'],
            ['uri' => "#$signedPropsId", 'type' => 'http://uri.etsi.org/01903#SignedProperties']
        );

        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $objKey->loadKey($privateKeyContent, false, false, $passwordCert);
        $objDSig->sign($objKey);
        $objDSig->add509Cert($certFirma, false, false);
        $objDSig->appendSignature($doc->documentElement);

        $sigNode = $doc->getElementsByTagName('Signature')->item(0);
        $sigNode->setAttribute('Id', $sigId);
        $sigNode->appendChild($objectNode);

        return $doc->saveXML();
    }
}
