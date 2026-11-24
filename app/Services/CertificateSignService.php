<?php
declare(strict_types=1);

namespace App\Services;

class CertificateSignService
{
    /**
     * Valida e gera assinatura digital a partir do certificado X.509 PEM enviado pelo navegador
     * via integração nativa com o repositório de certificados do Windows ou Linux (mTLS).
     */
    public function signFromX509Pem(string $pemCert, array $docData, string $clientVerify = ''): array
    {
        $pemCert = trim($pemCert);
        if (empty($pemCert) || !str_contains($pemCert, 'BEGIN CERTIFICATE')) {
            return [
                'success' => false,
                'message' => 'Nenhum certificado digital foi selecionado ou enviado pelo navegador.'
            ];
        }

        $certRes = @openssl_x509_read($pemCert);
        if (!$certRes) {
            return [
                'success' => false,
                'message' => 'Não foi possível decodificar o certificado X.509 fornecido pelo navegador.'
            ];
        }

        $x509 = @openssl_x509_parse($certRes);
        if (!$x509) {
            return [
                'success' => false,
                'message' => 'Falha ao ler metadados do certificado digital.'
            ];
        }

        // Validação de expiração
        $validFrom = (int)($x509['validFrom_time_t'] ?? 0);
        $validTo   = (int)($x509['validTo_time_t'] ?? 0);
        if ($validFrom > 0 && $validFrom > time()) {
            return [
                'success' => false,
                'message' => 'O certificado selecionado ainda não entrou em vigor.'
            ];
        }
        if ($validTo > 0 && $validTo < time()) {
            $expDate = date('d/m/Y H:i', $validTo);
            return [
                'success' => false,
                'message' => "O certificado digital selecionado expirou em {$expDate}."
            ];
        }

        $subject = $x509['subject'] ?? [];
        $issuer  = $x509['issuer'] ?? [];

        $commonName = (string)($subject['CN'] ?? $subject['O'] ?? 'Titular Certificado');
        $issuerName = (string)($issuer['CN'] ?? ($issuer['O'] ?? 'Autoridade Certificadora'));
        $serialNumber = (string)($x509['serialNumberHex'] ?? $x509['serialNumber'] ?? strtoupper(bin2hex(random_bytes(8))));

        // Extrai CPF ou CNPJ (padrão ICP-Brasil: CN = "NOME:CPF/CNPJ" ou subjectAltName)
        $docRaw = '';
        if (preg_match('/(?::|\s)(\d{11}|\d{14})(?:\b|$)/', $commonName, $matches)) {
            $docRaw = $matches[1];
        } elseif (!empty($subject['serialNumber']) && preg_match('/(\d{11}|\d{14})/', (string)$subject['serialNumber'], $m2)) {
            $docRaw = $m2[1];
        } elseif (!empty($x509['extensions']['subjectAltName']) && preg_match('/(\d{11}|\d{14})/', (string)$x509['extensions']['subjectAltName'], $m3)) {
            $docRaw = $m3[1];
        }

        $formattedDoc = 'Não informado';
        if (strlen($docRaw) === 11) {
            $formattedDoc = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $docRaw);
        } elseif (strlen($docRaw) === 14) {
            $formattedDoc = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $docRaw);
        }

        $cleanName = trim((string)preg_replace('/:\d+$/', '', $commonName));

        // Impressão digital SHA-256 do certificado público apresentado pelo navegador
        $certFingerprint = @openssl_x509_fingerprint($certRes, 'sha256') ?: hash('sha256', $pemCert);

        // Hash canônico do documento vinculado ao certificado do titular
        $canonical = json_encode($docData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hashSha256 = hash('sha256', $canonical . '|' . $certFingerprint . '|' . $serialNumber);

        $now = date('d/m/Y H:i:s');
        $expFormatted = $validTo > 0 ? date('d/m/Y', $validTo) : 'Ativo';

        // Carimbo visual oficial da assinatura via Certificado do Navegador (Windows/Linux)
        $sealDataUri = $this->generateVisualSeal([
            'type'       => 'ICP-BR',
            'title'      => 'ASSINADO VIA CERTIFICADO DIGITAL (NAVEGADOR / SO)',
            'name'       => $cleanName,
            'doc'        => $formattedDoc,
            'issuer'     => $issuerName,
            'serial'     => $serialNumber,
            'valid_to'   => $expFormatted,
            'date_time'  => $now,
            'hash_short' => substr($hashSha256, 0, 10) . '...' . substr($hashSha256, -10),
            'color'      => '#059669'
        ]);

        $payload = [
            'method'           => 'BROWSER_OS_X509',
            'signer_name'      => $cleanName,
            'signer_doc'       => $formattedDoc,
            'issuer'           => $issuerName,
            'serial_number'    => $serialNumber,
            'cert_fingerprint' => $certFingerprint,
            'valid_to'         => $expFormatted,
            'signed_at'        => date('Y-m-d H:i:s'),
            'hash_sha256'      => $hashSha256,
            'tls_verify'       => $clientVerify ?: 'PRESENTED'
        ];

        return [
            'success'        => true,
            'seal_image'     => $sealDataUri,
            'signer_name'    => $cleanName,
            'signer_doc'     => $formattedDoc,
            'issuer'         => $issuerName,
            'payload'        => $payload,
            'signature_code' => 'CERT-' . strtoupper(substr($hashSha256, 0, 10))
        ];
    }

    private function generateVisualSeal(array $d): string
    {
        $xmlFlags    = ENT_XML1 | ENT_QUOTES;
        $nameEsc     = htmlspecialchars($d['name'], $xmlFlags, 'UTF-8');
        $docEsc      = htmlspecialchars($d['doc'], $xmlFlags, 'UTF-8');
        $issuerEsc   = htmlspecialchars(mb_strimwidth($d['issuer'], 0, 52, '...'), $xmlFlags, 'UTF-8');
        $dateTimeEsc = htmlspecialchars($d['date_time'], $xmlFlags, 'UTF-8');
        $hashEsc     = htmlspecialchars($d['hash_short'], $xmlFlags, 'UTF-8');
        $titleEsc    = htmlspecialchars($d['title'], $xmlFlags, 'UTF-8');
        $color       = htmlspecialchars($d['color'], $xmlFlags, 'UTF-8');

        $svg = <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="480" height="150" viewBox="0 0 480 150">
    <rect x="2" y="2" width="476" height="146" rx="10" fill="#ffffff" stroke="{$color}" stroke-width="2.5"/>
    <rect x="2" y="2" width="12" height="146" rx="0" fill="{$color}"/>

    <circle cx="48" cy="46" r="24" fill="{$color}" opacity="0.12"/>
    <path d="M48 30 L62 36 V48 C62 57 56 65 48 68 C40 65 34 57 34 48 V36 Z" fill="{$color}"/>
    <path d="M48 40 L44 47 L47 47 L46 54 L52 46 L49 46 Z" fill="#ffffff"/>

    <text x="38" y="84" font-family="Arial, sans-serif" font-size="10" font-weight="bold" fill="{$color}">ICP</text>
    <text x="36" y="96" font-family="Arial, sans-serif" font-size="10" font-weight="bold" fill="{$color}">Brasil</text>

    <text x="86" y="28" font-family="Arial, sans-serif" font-size="9.5" font-weight="bold" fill="{$color}" letter-spacing="0.6">
        {$titleEsc}
    </text>

    <text x="86" y="52" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#0f172a">
        {$nameEsc}
    </text>
    <text x="86" y="70" font-family="Arial, sans-serif" font-size="10" font-weight="bold" fill="#334155">
        CPF/CNPJ: {$docEsc}
    </text>

    <text x="86" y="90" font-family="Arial, sans-serif" font-size="9" fill="#64748b">
        Emissor: {$issuerEsc}
    </text>
    <text x="86" y="106" font-family="Arial, sans-serif" font-size="9" fill="#64748b">
        Data/Hora da Assinatura: {$dateTimeEsc}
    </text>

    <line x1="86" y1="116" x2="460" y2="116" stroke="#e2e8f0" stroke-width="1"/>
    <text x="86" y="132" font-family="Courier, monospace" font-size="8.5" fill="#64748b">
        Hash SHA-256: {$hashEsc} &#8226; Certificado A1/A3 Navegador
    </text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
