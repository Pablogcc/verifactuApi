<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use DateTime;
use DateTimeZone;

/**
 * Servicio de firma electrónica para documentos Facturae.
 *
 * Carga el certificado desde un fichero PFX y construye
 * una firma XAdES-BES/EPES compatible con la política Facturae.
 */
class ChilkatLikeFacturaeSigner
{
    // XMLDSig / XAdES namespaces
    private const DS_NS    = 'http://www.w3.org/2000/09/xmldsig#';
    private const XADES_NS = 'http://uri.etsi.org/01903/v1.3.2#';

    // EXACTO como tu firma "buena"
    private const C14N_ALG   = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    private const SIG_ALG    = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
    private const DIGEST_ALG = 'http://www.w3.org/2000/09/xmldsig#sha1';

    // Política Facturae v3.1 (SHA1) como tu ejemplo válido
    private const POLICY_URL      = 'http://www.facturae.es/politica_de_firma_formato_facturae/politica_de_firma_formato_facturae_v3_1.pdf';
    private const POLICY_DESC     = 'facturae31';
    private const POLICY_SHA1_B64 = 'Ohixl6upD6av8N7pEvDABhEL6hM=';

    /**
     * Firma un Facturae con estructura igual a tu ejemplo válido (XAdES-BES/EPES estilo Facturae v3.1 sha1)
     *
     * @param string $xmlContent XML Facturae SIN firmar
     * @param string $pfxPath    Ruta al .pfx
     * @param string $pfxPass    Password del .pfx
     * @param bool   $debugSelfCheck Si true, verifica internamente digests+RSA y lanza excepción si algo no cuadra
     *
     * @return string XML firmado
     */
    public function signFacturaeWithPolicy(string $xmlContent, string $pfxPath, string $pfxPass, bool $debugSelfCheck = false): string
    {
        if (!is_file($pfxPath)) {
            throw new \Exception("No existe el PFX en: {$pfxPath}");
        }

        // --- 1) Cargar PFX (cert + private key) ---
        $pfxBin = file_get_contents($pfxPath);
        if ($pfxBin === false) {
            throw new \Exception("No se pudo leer el PFX");
        }

        $certs = [];
        if (!openssl_pkcs12_read($pfxBin, $certs, $pfxPass)) {
            throw new \Exception("No se pudo abrir el PFX (password incorrecta o PFX inválido)");
        }
        if (empty($certs['pkey']) || empty($certs['cert'])) {
            throw new \Exception("El PFX no contiene clave privada y certificado");
        }

        $privateKeyPem = $certs['pkey'];
        $certPem       = $certs['cert'];

        $pkey = openssl_pkey_get_private($privateKeyPem, $pfxPass);
        if ($pkey === false) {
            throw new \Exception("No se pudo cargar la clave privada del PFX");
        }

        // Extraer cert base64 (sin cabeceras) y DER binario
        if (!preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $certPem, $m)) {
            throw new \Exception("No se pudo extraer el certificado del PEM");
        }
        $certB64 = preg_replace('/\s+/', '', $m[1]);
        $certDer = base64_decode($certB64, true);
        if ($certDer === false) {
            throw new \Exception("Certificado Base64 inválido");
        }

        $certParsed = openssl_x509_parse($certPem);
        if ($certParsed === false) {
            throw new \Exception("No se pudo parsear el certificado X509");
        }

        // Issuer puede venir como array o string dependiendo de OpenSSL/PHP
        $issuerRaw = $certParsed['issuer'] ?? null;
        if (!is_array($issuerRaw) && !(is_string($issuerRaw) && $issuerRaw !== '')) {
            throw new \Exception("No se pudo obtener issuer del certificado (openssl_x509_parse)");
        }
        $issuerNameStr = $this->formatIssuerName($issuerRaw);

        // Serial: intentar garantizar decimal (muchos validadores son quisquillosos)
        $serialNumber = $this->getCertSerialDecimal($certParsed);

        // CertDigest (SHA1 del DER) como tu ejemplo
        $certDigestB64 = base64_encode(hash('sha1', $certDer, true));

        // --- 2) Cargar XML Facturae ---
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        if (!$doc->loadXML($xmlContent, LIBXML_NOBLANKS)) {
            throw new \Exception("XML inválido");
        }

        $root = $doc->documentElement;
        if (!$root instanceof DOMElement) {
            throw new \Exception("XML sin nodo raíz");
        }

        // Facturae 3.2 suele NO querer Id en root
        $root->removeAttribute('Id');

        // --- 3) IDs como el ejemplo ---
        $uuid = $this->uuidV4();
        $sigIdBase    = "Signature-{$uuid}";
        $sigId        = "{$sigIdBase}-Signature";
        $sigValueId   = "{$sigIdBase}-SignatureValue";
        $keyInfoId    = "{$sigIdBase}-KeyInfo";
        $qualId       = "{$sigIdBase}-QualifyingProperties";
        $signedPropId = "{$sigIdBase}-SignedProperties";

        $docRefId = "Reference-{$this->uuidV4()}"; // Id del Reference del documento (ObjectReference apunta aquí)

        // --- 4) Construir ds:Signature (MISMA forma que tu firma buena) ---
        $sig = $doc->createElementNS(self::DS_NS, 'ds:Signature');
        // En muchos DOM ya lo escribe solo, pero lo dejamos igual al ejemplo:
        if (!$sig->hasAttribute('xmlns:ds')) {
            $sig->setAttribute('xmlns:ds', self::DS_NS);
        }
        $sig->setAttribute('Id', $sigId);

        // SignedInfo
        $signedInfo = $doc->createElementNS(self::DS_NS, 'ds:SignedInfo');

        $canon = $doc->createElementNS(self::DS_NS, 'ds:CanonicalizationMethod');
        $canon->setAttribute('Algorithm', self::C14N_ALG);

        $sigMethod = $doc->createElementNS(self::DS_NS, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', self::SIG_ALG);

        $signedInfo->appendChild($canon);
        $signedInfo->appendChild($sigMethod);

        // Reference 1: documento (URI="")
        $refDoc = $doc->createElementNS(self::DS_NS, 'ds:Reference');
        $refDoc->setAttribute('Id', $docRefId);
        $refDoc->setAttribute('URI', '');

        $transforms = $doc->createElementNS(self::DS_NS, 'ds:Transforms');
        $tr = $doc->createElementNS(self::DS_NS, 'ds:Transform');
        $tr->setAttribute('Algorithm', self::DS_NS . 'enveloped-signature');
        $transforms->appendChild($tr);
        $refDoc->appendChild($transforms);

        $dm1 = $doc->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $dm1->setAttribute('Algorithm', self::DIGEST_ALG);
        $dv1 = $doc->createElementNS(self::DS_NS, 'ds:DigestValue', ''); // se rellena luego
        $refDoc->appendChild($dm1);
        $refDoc->appendChild($dv1);

        // Reference 2: SignedProperties
        $refSP = $doc->createElementNS(self::DS_NS, 'ds:Reference');
        $refSP->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $refSP->setAttribute('URI', "#{$signedPropId}");

        $dm2 = $doc->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $dm2->setAttribute('Algorithm', self::DIGEST_ALG);
        $dv2 = $doc->createElementNS(self::DS_NS, 'ds:DigestValue', '');
        $refSP->appendChild($dm2);
        $refSP->appendChild($dv2);

        // Reference 3: KeyInfo
        $refKI = $doc->createElementNS(self::DS_NS, 'ds:Reference');
        $refKI->setAttribute('URI', "#{$keyInfoId}");

        $dm3 = $doc->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $dm3->setAttribute('Algorithm', self::DIGEST_ALG);
        $dv3 = $doc->createElementNS(self::DS_NS, 'ds:DigestValue', '');
        $refKI->appendChild($dm3);
        $refKI->appendChild($dv3);

        // Orden EXACTO: Doc, SignedProps, KeyInfo
        $signedInfo->appendChild($refDoc);
        $signedInfo->appendChild($refSP);
        $signedInfo->appendChild($refKI);

        $sig->appendChild($signedInfo);

        // SignatureValue (se rellena al final)
        $sigValue = $doc->createElementNS(self::DS_NS, 'ds:SignatureValue', '');
        $sigValue->setAttribute('Id', $sigValueId);
        $sig->appendChild($sigValue);

        // KeyInfo
        $keyInfo = $doc->createElementNS(self::DS_NS, 'ds:KeyInfo');
        $keyInfo->setAttribute('Id', $keyInfoId);

        // KeyValue RSA (Modulus + Exponent)
        $pubKey = openssl_pkey_get_public($certPem);
        if ($pubKey === false) {
            throw new \Exception("No se pudo obtener la clave pública del certificado");
        }
        $pubDetails = openssl_pkey_get_details($pubKey);
        if (empty($pubDetails['rsa']['n']) || empty($pubDetails['rsa']['e'])) {
            throw new \Exception("El certificado no parece RSA o faltan parámetros n/e");
        }
        $modulusB64  = base64_encode($pubDetails['rsa']['n']);
        $exponentB64 = base64_encode($pubDetails['rsa']['e']);

        $keyValue = $doc->createElementNS(self::DS_NS, 'ds:KeyValue');
        $rsaKey   = $doc->createElementNS(self::DS_NS, 'ds:RSAKeyValue');

        $modEl = $doc->createElementNS(self::DS_NS, 'ds:Modulus');
        $this->setWrappedBase64($doc, $modEl, $modulusB64, 64);

        $expEl = $doc->createElementNS(self::DS_NS, 'ds:Exponent', $exponentB64);

        $rsaKey->appendChild($modEl);
        $rsaKey->appendChild($expEl);
        $keyValue->appendChild($rsaKey);

        // X509Data + X509Certificate (base64 con saltos)
        $x509Data = $doc->createElementNS(self::DS_NS, 'ds:X509Data');
        $x509Cert = $doc->createElementNS(self::DS_NS, 'ds:X509Certificate');
        $this->setWrappedBase64($doc, $x509Cert, $certB64, 64);
        $x509Data->appendChild($x509Cert);

        // Orden como tu ejemplo: KeyValue primero, luego X509Data
        $keyInfo->appendChild($keyValue);
        $keyInfo->appendChild($x509Data);

        $sig->appendChild($keyInfo);

        // ds:Object con xades:QualifyingProperties
        $obj = $doc->createElementNS(self::DS_NS, 'ds:Object');

        $qual = $doc->createElementNS(self::XADES_NS, 'xades:QualifyingProperties');
        if (!$qual->hasAttribute('xmlns:xades')) {
            $qual->setAttribute('xmlns:xades', self::XADES_NS);
        }
        $qual->setAttribute('Id', $qualId);
        $qual->setAttribute('Target', "#{$sigId}");

        $signedProps = $doc->createElementNS(self::XADES_NS, 'xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropId);

        // SignedSignatureProperties
        $ssp = $doc->createElementNS(self::XADES_NS, 'xades:SignedSignatureProperties');

        $dt = new DateTime('now', new DateTimeZone('Europe/Madrid'));
        $signTime = $doc->createElementNS(self::XADES_NS, 'xades:SigningTime', $dt->format('Y-m-d\TH:i:sP'));
        $ssp->appendChild($signTime);

        // SigningCertificate
        $signingCert = $doc->createElementNS(self::XADES_NS, 'xades:SigningCertificate');
        $certEl      = $doc->createElementNS(self::XADES_NS, 'xades:Cert');
        $certDigest  = $doc->createElementNS(self::XADES_NS, 'xades:CertDigest');

        $cdm = $doc->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $cdm->setAttribute('Algorithm', self::DIGEST_ALG);
        $cdv = $doc->createElementNS(self::DS_NS, 'ds:DigestValue', $certDigestB64);

        $certDigest->appendChild($cdm);
        $certDigest->appendChild($cdv);

        $issuerSerial = $doc->createElementNS(self::XADES_NS, 'xades:IssuerSerial');
        $issuerSerial->appendChild($doc->createElementNS(self::DS_NS, 'ds:X509IssuerName', $issuerNameStr));
        $issuerSerial->appendChild($doc->createElementNS(self::DS_NS, 'ds:X509SerialNumber', $serialNumber));

        $certEl->appendChild($certDigest);
        $certEl->appendChild($issuerSerial);

        $signingCert->appendChild($certEl);
        $ssp->appendChild($signingCert);

        // SignaturePolicyIdentifier (Facturae v3.1 sha1)
        $spi  = $doc->createElementNS(self::XADES_NS, 'xades:SignaturePolicyIdentifier');
        $spid = $doc->createElementNS(self::XADES_NS, 'xades:SignaturePolicyId');

        $sigPolicyId = $doc->createElementNS(self::XADES_NS, 'xades:SigPolicyId');
        $sigPolicyId->appendChild($doc->createElementNS(self::XADES_NS, 'xades:Identifier', self::POLICY_URL));
        $sigPolicyId->appendChild($doc->createElementNS(self::XADES_NS, 'xades:Description', self::POLICY_DESC));

        $sigPolicyHash = $doc->createElementNS(self::XADES_NS, 'xades:SigPolicyHash');
        $pdm = $doc->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $pdm->setAttribute('Algorithm', self::DIGEST_ALG);
        $pdv = $doc->createElementNS(self::DS_NS, 'ds:DigestValue', self::POLICY_SHA1_B64);

        $sigPolicyHash->appendChild($pdm);
        $sigPolicyHash->appendChild($pdv);

        $spid->appendChild($sigPolicyId);
        $spid->appendChild($sigPolicyHash);
        $spi->appendChild($spid);

        $ssp->appendChild($spi);

        // SignerRole: emisor
        $signerRole = $doc->createElementNS(self::XADES_NS, 'xades:SignerRole');
        $claimedRoles = $doc->createElementNS(self::XADES_NS, 'xades:ClaimedRoles');
        $claimedRoles->appendChild($doc->createElementNS(self::XADES_NS, 'xades:ClaimedRole', 'emisor'));
        $signerRole->appendChild($claimedRoles);
        $ssp->appendChild($signerRole);

        $signedProps->appendChild($ssp);

        // SignedDataObjectProperties (como tu firma válida)
        $sdop = $doc->createElementNS(self::XADES_NS, 'xades:SignedDataObjectProperties');
        $dof  = $doc->createElementNS(self::XADES_NS, 'xades:DataObjectFormat');
        $dof->setAttribute('ObjectReference', "#{$docRefId}");

        $dof->appendChild($doc->createElementNS(self::XADES_NS, 'xades:Description', ''));

        $objId = $doc->createElementNS(self::XADES_NS, 'xades:ObjectIdentifier');
        $idn   = $doc->createElementNS(self::XADES_NS, 'xades:Identifier', 'urn:oid:1.2.840.10003.5.109.10');
        $idn->setAttribute('Qualifier', 'OIDAsURN');
        $objId->appendChild($idn);
        $objId->appendChild($doc->createElementNS(self::XADES_NS, 'xades:Description', ''));

        $dof->appendChild($objId);
        $dof->appendChild($doc->createElementNS(self::XADES_NS, 'xades:MimeType', 'text/xml'));
        $dof->appendChild($doc->createElementNS(self::XADES_NS, 'xades:Encoding', 'UTF-8'));

        $sdop->appendChild($dof);
        $signedProps->appendChild($sdop);

        $qual->appendChild($signedProps);
        $obj->appendChild($qual);
        $sig->appendChild($obj);

        // --- 5) Insertar Signature al final del root (enveloped) ---
        $root->appendChild($sig);

        // --- 6) Calcular digests (SHA1) ---
        // 6.1 Documento: root sin ds:Signature
        $docDigestB64 = $this->digestFacturaeDocumentSha1($doc);

        // 6.2 SignedProperties
        $signedPropsDigestB64 = base64_encode(hash('sha1', $signedProps->C14N(false, false), true));

        // 6.3 KeyInfo
        $keyInfoDigestB64 = base64_encode(hash('sha1', $keyInfo->C14N(false, false), true));

        // Escribir DigestValue en los 3 references
        $dv1->nodeValue = $docDigestB64;
        $dv2->nodeValue = $signedPropsDigestB64;
        $dv3->nodeValue = $keyInfoDigestB64;

        // --- 7) Firmar SignedInfo (RSA-SHA1) ---
        $signedInfoC14N = $signedInfo->C14N(false, false);

        $signatureBin = '';
        if (!openssl_sign($signedInfoC14N, $signatureBin, $pkey, OPENSSL_ALGO_SHA1)) {
            throw new \Exception("Error firmando SignedInfo con RSA-SHA1");
        }

        // SignatureValue sin saltos (como tu ejemplo)
        $sigValue->nodeValue = base64_encode($signatureBin);

        // Root sin Id
        $root->removeAttribute('Id');

        // --- 8) Self-check opcional (MUY útil para ver qué falla) ---
        if ($debugSelfCheck) {
            $this->selfCheck($doc, $signedInfo, $sigValue, $pubKey, $docDigestB64, $signedPropsDigestB64, $keyInfoDigestB64);
        }

        return $doc->saveXML();
    }

    private function digestFacturaeDocumentSha1(DOMDocument $doc): string
    {
        $tmp = new DOMDocument();
        $tmp->preserveWhiteSpace = false;
        $tmp->formatOutput = false;
        $tmp->loadXML($doc->saveXML($doc->documentElement), LIBXML_NOBLANKS);

        // eliminar ds:Signature (en cualquier posición)
        $sigNodes = $tmp->getElementsByTagNameNS(self::DS_NS, 'Signature');
        for ($i = $sigNodes->length - 1; $i >= 0; $i--) {
            $n = $sigNodes->item($i);
            if ($n && $n->parentNode) {
                $n->parentNode->removeChild($n);
            }
        }

        $tmpRoot = $tmp->documentElement;
        $c14n = $tmpRoot->C14N(false, false);

        return base64_encode(hash('sha1', $c14n, true));
    }

    private function setWrappedBase64(DOMDocument $doc, DOMElement $el, string $b64, int $chunkLen = 64): void
    {
        $b64 = preg_replace('/\s+/', '', trim($b64));
        while ($el->firstChild) {
            $el->removeChild($el->firstChild);
        }
        if ($b64 === '') {
            return;
        }
        $chunks = str_split($b64, $chunkLen);
        $last = count($chunks) - 1;
        foreach ($chunks as $i => $chunk) {
            $text = ($i === $last) ? $chunk : ($chunk . "\n");
            $el->appendChild($doc->createTextNode($text));
        }
    }

    private function formatIssuerName(array|string $issuer): string
    {
        // Si ya viene string (algunos entornos), lo respetamos
        if (is_string($issuer)) {
            return trim($issuer);
        }

        // Orden típico y compatible con lo que has visto (incluye serialNumber si existe)
        $order = ['CN', 'serialNumber', 'OU', 'O', 'L', 'ST', 'C'];
        $parts = [];

        foreach ($order as $k) {
            if (!empty($issuer[$k])) {
                // Si viniera como array (múltiples OU), concaténalo
                if (is_array($issuer[$k])) {
                    foreach ($issuer[$k] as $vv) {
                        if ($vv !== '') $parts[] = $k . '=' . $vv;
                    }
                } else {
                    $parts[] = $k . '=' . $issuer[$k];
                }
            }
        }

        // Añadir el resto de claves no incluidas en order
        foreach ($issuer as $k => $v) {
            if ($v === '' || in_array($k, $order, true)) continue;

            if (is_array($v)) {
                foreach ($v as $vv) {
                    if ($vv !== '') $parts[] = $k . '=' . $vv;
                }
            } else {
                $parts[] = $k . '=' . $v;
            }
        }

        return implode(',', $parts);
    }

    private function getCertSerialDecimal(array $certParsed): string
    {
        // Preferimos serialNumber si ya es decimal
        $sn = (string)($certParsed['serialNumber'] ?? '');
        if ($sn !== '' && ctype_digit($sn)) {
            return $sn;
        }

        // Algunos entornos dan serialNumberHex
        $hex = (string)($certParsed['serialNumberHex'] ?? '');
        if ($hex === '' && $sn !== '') {
            // si serialNumber no era decimal, lo tratamos como “posible hex”
            $hex = $sn;
        }

        $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);

        if ($hex !== '' && function_exists('gmp_init')) {
            // Convertir hex -> decimal
            return gmp_strval(gmp_init($hex, 16), 10);
        }

        // Último recurso: devolver lo que hubiera (mejor que vacío)
        return $sn !== '' ? $sn : ($hex !== '' ? $hex : '0');
    }

    private function selfCheck(
        DOMDocument $doc,
        DOMElement $signedInfo,
        DOMElement $sigValue,
        $pubKey,
        string $expectedDocDigest,
        string $expectedSpDigest,
        string $expectedKiDigest
    ): void {
        // 1) Recalcular digests y comparar
        $docDigest = $this->digestFacturaeDocumentSha1($doc);
        if ($docDigest !== $expectedDocDigest) {
            throw new \Exception("SELF-CHECK: Digest DOCUMENTO no coincide. esperado={$expectedDocDigest} actual={$docDigest}");
        }

        $xp = new DOMXPath($doc);
        $xp->registerNamespace('ds', self::DS_NS);
        $xp->registerNamespace('xades', self::XADES_NS);

        $sp = $xp->query('//xades:SignedProperties')->item(0);
        $ki = $xp->query('//ds:KeyInfo')->item(0);

        if (!$sp instanceof DOMElement) {
            throw new \Exception("SELF-CHECK: No se encontró xades:SignedProperties");
        }
        if (!$ki instanceof DOMElement) {
            throw new \Exception("SELF-CHECK: No se encontró ds:KeyInfo");
        }

        $spDigest = base64_encode(hash('sha1', $sp->C14N(false, false), true));
        if ($spDigest !== $expectedSpDigest) {
            throw new \Exception("SELF-CHECK: Digest SignedProperties no coincide. esperado={$expectedSpDigest} actual={$spDigest}");
        }

        $kiDigest = base64_encode(hash('sha1', $ki->C14N(false, false), true));
        if ($kiDigest !== $expectedKiDigest) {
            throw new \Exception("SELF-CHECK: Digest KeyInfo no coincide. esperado={$expectedKiDigest} actual={$kiDigest}");
        }

        // 2) Verificar RSA sobre C14N(SignedInfo)
        $signedInfoC14N = $signedInfo->C14N(false, false);

        $sigBin = base64_decode(trim($sigValue->textContent), true);
        if ($sigBin === false) {
            throw new \Exception("SELF-CHECK: SignatureValue no es Base64 válido");
        }

        $ok = openssl_verify($signedInfoC14N, $sigBin, $pubKey, OPENSSL_ALGO_SHA1);
        if ($ok !== 1) {
            throw new \Exception("SELF-CHECK: openssl_verify falló (RSA-SHA1 no valida contra SignedInfo)");
        }
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
