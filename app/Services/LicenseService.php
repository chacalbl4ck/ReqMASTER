<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class LicenseService
{
    public const CHECK_INTERVAL_SECONDS = 300; // 5 minutos
    private const MAX_OFFLINE_GRACE_FAILS = 3;
    private const VAULT_FILE = 'license.enc';

    private VaultService $vault;
    private Setting $settingModel;
    private NtpTimeService $ntpService;

    public function __construct(?Setting $settingModel = null)
    {
        $this->vault = new VaultService();
        $this->settingModel = $settingModel ?? new Setting();
        $this->ntpService = new NtpTimeService();
    }

    /**
     * Valida se a chave segue o padrão oficial XXXX-XXXX-XXXX-XXXX.
     */
    public function isValidFormat(string $key): bool
    {
        $normalized = strtoupper(trim($key));
        return (bool)preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $normalized);
    }

    /**
     * Verifica integridade local, expiração via NTP + cidade do CNPJ e executa rechecagem a cada 5 minutos.
     */
    public function enforce(): array
    {
        $fingerprint = $this->vault->getMachineFingerprint();
        $vaultData = $this->vault->readEncryptedFile(self::VAULT_FILE);

        $dbStatus = $this->settingModel->get('license_status', 'unlicensed');
        $dbKey    = strtoupper(trim((string)$this->settingModel->get('license_key', '')));
        $dbLock   = $this->settingModel->get('license_lock_mode', '1');
        $dbProof  = $this->settingModel->get('license_proof', '');

        if ($dbLock === '1' && $dbStatus !== 'active') {
            return ['valid' => false, 'reason' => 'Sistema não licenciado ou aguardando ativação.'];
        }

        if (!$vaultData || empty($dbKey) || $dbStatus !== 'active' || $dbLock === '1') {
            $this->lockLocally('unlicensed');
            return ['valid' => false, 'reason' => 'Licença não ativada ou sistema bloqueado.'];
        }

        if (
            ($vaultData['fingerprint'] ?? '') !== $fingerprint ||
            strtoupper((string)($vaultData['key'] ?? '')) !== $dbKey ||
            ($vaultData['status'] ?? '') !== 'active'
        ) {
            $this->lockLocally('tampered');
            return ['valid' => false, 'reason' => 'Falha na verificação de integridade da licença.'];
        }

        $verifiedAt = (int)($vaultData['verified_at'] ?? 0);
        $expiresAt  = (string)($vaultData['expires_at'] ?? '');
        $expectedProofPayload = $dbKey . '|' . $fingerprint . '|' . $verifiedAt . '|' . $expiresAt . '|active';
        if (empty($dbProof) || !$this->vault->hmacVerify($expectedProofPayload, $dbProof)) {
            $this->lockLocally('tampered');
            return ['valid' => false, 'reason' => 'Assinatura de integridade da licença inválida.'];
        }

        // Checagem contínua de expiração mesmo entre os intervalos de 5 minutos (usando offset NTP salvo)
        $ntpOffset = (int)($vaultData['ntp_offset'] ?? 0);
        $expiresAtUtc = (int)($vaultData['expires_at_utc'] ?? 0);
        if ($expiresAtUtc > 0 && (time() + $ntpOffset) > $expiresAtUtc) {
            // Confirma online via NTP antes de bloquear definitivamente
            $recheck = $this->performFullRemoteCheck($dbKey, $vaultData);
            if (!$recheck['valid']) {
                return $recheck;
            }
        }

        // Detecta retrocesso manual do relógio local (> 2 minutos) e força consulta NTP imediata
        $clockRolledBack = time() < ($verifiedAt - 120);
        $lastChecked = (int)($vaultData['last_checked_at'] ?? 0);

        if ($clockRolledBack || (time() - $lastChecked) >= self::CHECK_INTERVAL_SECONDS) {
            return $this->performFullRemoteCheck($dbKey, $vaultData);
        }

        return ['valid' => true, 'key' => $dbKey, 'last_checked_at' => $lastChecked];
    }

    /**
     * Ativa uma chave de licença validando linha no GitHub, CNPJ/cidade e data/hora via NTP.
     */
    public function activateKey(string $rawKey): array
    {
        $key = strtoupper(trim($rawKey));

        if (!$this->isValidFormat($key)) {
            return [
                'success' => false,
                'message' => 'Formato de chave inválido. Utilize o padrão oficial XXXX-XXXX-XXXX-XXXX (16 dígitos).'
            ];
        }

        $remote = $this->verifyKeyRemotely($key);
        if ($remote['network_error']) {
            return [
                'success' => false,
                'message' => 'Falha de comunicação com o servidor oficial de licenciamento. Verifique sua conexão com a internet.'
            ];
        }

        if (!$remote['valid']) {
            return [
                'success' => false,
                'message' => 'Chave de licença não autorizada, inexistente ou revogada.'
            ];
        }

        $cnpjToUse = !empty($remote['cnpj'])
            ? $remote['cnpj']
            : preg_replace('/\D/', '', (string)$this->settingModel->get('company_cnpj', ''));

        $timeInfo = $this->ntpService->getAuthoritativeCityTime($cnpjToUse, null);
        if (!$timeInfo['ntp_success']) {
            return [
                'success' => false,
                'message' => 'Não foi possível validar a data e hora nos servidores NTP (principal e backup). Verifique a porta UDP 123 / conexão de rede.'
            ];
        }

        $expiresAt = $remote['expires_at'];
        if (!empty($expiresAt) && $timeInfo['local_date'] > $expiresAt) {
            $expBr = date('d/m/Y', strtotime($expiresAt));
            return [
                'success' => false,
                'message' => "Licença expirada em {$expBr} (data oficial em {$timeInfo['city']}/{$timeInfo['uf']}: {$timeInfo['local_br']})."
            ];
        }

        $this->persistValidLicense($key, $expiresAt, $timeInfo);

        $expText = !empty($expiresAt) ? (' Válida até ' . date('d/m/Y', strtotime($expiresAt)) . '.') : '';
        return [
            'success' => true,
            'message' => "Licença validada com sucesso via NTP ({$timeInfo['ntp_server']} • {$timeInfo['city']}/{$timeInfo['uf']})!{$expText}"
        ];
    }

    /**
     * Executa checagem remota completa (GitHub + CNPJ/Fuso + NTP principal/backup + Data Limite).
     */
    private function performFullRemoteCheck(string $dbKey, array $vaultData): array
    {
        $remote = $this->verifyKeyRemotely($dbKey);

        if ($remote['network_error']) {
            $fails = (int)($vaultData['offline_fails'] ?? 0) + 1;
            if ($fails >= self::MAX_OFFLINE_GRACE_FAILS) {
                $this->lockLocally('offline_expired');
                return ['valid' => false, 'reason' => 'Não foi possível validar a licença online após múltiplas tentativas.'];
            }
            $vaultData['offline_fails'] = $fails;
            $vaultData['last_checked_at'] = time();
            $this->vault->writeEncryptedFile(self::VAULT_FILE, $vaultData);
            return ['valid' => true, 'key' => $dbKey];
        }

        if (!$remote['valid']) {
            $this->lockLocally('revoked');
            return ['valid' => false, 'reason' => 'Sua licença foi revogada ou removida da base oficial.'];
        }

        $cnpjToUse = !empty($remote['cnpj'])
            ? $remote['cnpj']
            : (!empty($vaultData['cnpj']) ? (string)$vaultData['cnpj'] : preg_replace('/\D/', '', (string)$this->settingModel->get('company_cnpj', '')));

        $cachedGeo = [
            'cnpj'     => $vaultData['cnpj'] ?? '',
            'city'     => $vaultData['cnpj_city'] ?? '',
            'uf'       => $vaultData['cnpj_uf'] ?? '',
            'timezone' => $vaultData['cnpj_timezone'] ?? '',
            'found'    => !empty($vaultData['geo_found']),
        ];

        $timeInfo = $this->ntpService->getAuthoritativeCityTime($cnpjToUse, $cachedGeo);
        if (!$timeInfo['ntp_success']) {
            $fails = (int)($vaultData['offline_fails'] ?? 0) + 1;
            if ($fails >= self::MAX_OFFLINE_GRACE_FAILS) {
                $this->lockLocally('ntp_error');
                return ['valid' => false, 'reason' => 'Falha ao consultar servidores NTP principal e backup para validar data da licença.'];
            }
            $vaultData['offline_fails'] = $fails;
            $vaultData['last_checked_at'] = time();
            $this->vault->writeEncryptedFile(self::VAULT_FILE, $vaultData);
            return ['valid' => true, 'key' => $dbKey];
        }

        $expiresAt = $remote['expires_at'];
        if (!empty($expiresAt) && $timeInfo['local_date'] > $expiresAt) {
            $this->lockLocally('expired');
            $expBr = date('d/m/Y', strtotime($expiresAt));
            return [
                'valid'  => false,
                'reason' => "Licença expirada em {$expBr} (horário oficial em {$timeInfo['city']}/{$timeInfo['uf']}: {$timeInfo['local_br']})."
            ];
        }

        $this->persistValidLicense($dbKey, $expiresAt, $timeInfo);
        return ['valid' => true, 'key' => $dbKey, 'last_checked_at' => time()];
    }

    /**
     * Consulta o arquivo 'licences' / 'licences.txt' e extrai Chave, Data Limite e CNPJ da linha.
     * Formatos aceitos por linha:
     *   XXXX-XXXX-XXXX-XXXX | YYYY-MM-DD | 00.000.000/0001-91
     *   XXXX-XXXX-XXXX-XXXX | DD/MM/YYYY | 00000000000191
     *   XXXX-XXXX-XXXX-XXXX | YYYY-MM-DD
     *   XXXX-XXXX-XXXX-XXXX
     */
    private function verifyKeyRemotely(string $licenseKey): array
    {
        $repo  = $this->vault->getCredential('lic_repo');
        $token = $this->vault->getCredential('lic_token');
        $file  = $this->vault->getCredential('lic_file');

        if (empty($repo) || empty($token) || empty($file)) {
            return ['valid' => false, 'network_error' => true];
        }

        $url = "https://api.github.com/repos/{$repo}/contents/{$file}";
        $content = $this->httpGetRaw($url, $token);

        if ($content === null && !str_ends_with($file, '.txt')) {
            $content = $this->httpGetRaw("https://api.github.com/repos/{$repo}/contents/{$file}.txt", $token);
        }

        if ($content === null) {
            return ['valid' => false, 'network_error' => true];
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $target = strtoupper(trim($licenseKey));

        foreach ($lines as $line) {
            $clean = trim($line);
            if ($clean === '' || str_starts_with($clean, '#') || str_starts_with($clean, '//')) {
                continue;
            }

            $parts = preg_split('/\s*[|;]\s*|\s+/', $clean);
            $candidateKey = strtoupper(trim((string)($parts[0] ?? '')));

            if ($candidateKey !== '' && hash_equals($candidateKey, $target)) {
                $expiresAt = null;
                $cnpj = '';

                for ($i = 1; $i < count($parts); $i++) {
                    $tokenPart = trim((string)$parts[$i]);
                    if ($expiresAt === null && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tokenPart, $m)) {
                        if (checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                            $expiresAt = $tokenPart;
                        }
                    } elseif ($expiresAt === null && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $tokenPart, $m)) {
                        if (checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
                            $expiresAt = "{$m[3]}-{$m[2]}-{$m[1]}";
                        }
                    } else {
                        $digits = preg_replace('/\D/', '', $tokenPart);
                        if (strlen($digits) === 14) {
                            $cnpj = $digits;
                        }
                    }
                }

                return [
                    'valid'         => true,
                    'network_error' => false,
                    'expires_at'    => $expiresAt,
                    'cnpj'          => $cnpj,
                ];
            }
        }

        return ['valid' => false, 'network_error' => false];
    }

    private function persistValidLicense(string $key, ?string $expiresAt, array $timeInfo): void
    {
        $now = time();
        $fingerprint = $this->vault->getMachineFingerprint();
        $expiresStr = (string)($expiresAt ?? '');
        $payload = $key . '|' . $fingerprint . '|' . $now . '|' . $expiresStr . '|active';
        $proof = $this->vault->hmacSign($payload);

        $expiresAtUtc = 0;
        if (!empty($expiresStr)) {
            try {
                $tz = new DateTimeZone($timeInfo['timezone'] ?: 'America/Sao_Paulo');
                $dtExp = new DateTimeImmutable($expiresStr . ' 23:59:59', $tz);
                $expiresAtUtc = $dtExp->getTimestamp();
            } catch (Throwable $e) {
                $expiresAtUtc = 0;
            }
        }

        $ntpOffset = (int)$timeInfo['utc_timestamp'] - $now;

        $this->vault->writeEncryptedFile(self::VAULT_FILE, [
            'key'                => $key,
            'fingerprint'        => $fingerprint,
            'status'             => 'active',
            'verified_at'        => $now,
            'last_checked_at'    => $now,
            'offline_fails'      => 0,
            'expires_at'         => $expiresStr,
            'expires_at_utc'     => $expiresAtUtc,
            'ntp_offset'         => $ntpOffset,
            'ntp_server'         => $timeInfo['ntp_server'] ?? '',
            'ntp_local_datetime' => $timeInfo['local_br'] ?? '',
            'cnpj'               => $timeInfo['cnpj'] ?? '',
            'cnpj_city'          => $timeInfo['city'] ?? '',
            'cnpj_uf'            => $timeInfo['uf'] ?? '',
            'cnpj_timezone'      => $timeInfo['timezone'] ?? 'America/Sao_Paulo',
            'geo_found'          => !empty($timeInfo['geo_found']),
        ]);

        $this->settingModel->setMany([
            'license_status'       => 'active',
            'license_key'          => $key,
            'license_lock_mode'    => '0',
            'license_expires_at'   => $expiresStr,
            'license_proof'        => $proof,
            'license_last_check'   => date('Y-m-d H:i:s', $now),
            'license_activated_at' => $this->settingModel->get('license_activated_at') ?: date('Y-m-d H:i:s', $now),
        ]);
    }

    private function lockLocally(string $status): void
    {
        try {
            $this->vault->deleteVaultFile(self::VAULT_FILE);
            $this->settingModel->setMany([
                'license_status'    => $status,
                'license_lock_mode' => '1',
                'license_proof'     => '',
            ]);
        } catch (Throwable $e) {
            // Ignora falha silenciosa
        }
    }

    private function httpGetRaw(string $url, string $token): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => [
                    'User-Agent: ReqMaster-LicenseEngine',
                    'Accept: application/vnd.github.raw+json',
                    'Authorization: Bearer ' . $token,
                    'X-GitHub-Api-Version: 2022-11-28',
                    'Cache-Control: no-cache, no-store',
                ],
            ]);
            $res = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($res !== false && $code >= 200 && $code < 300) {
                return (string)$res;
            }
            return null;
        }

        $opts = [
            'http' => [
                'method'  => 'GET',
                'timeout' => 10,
                'header'  => "User-Agent: ReqMaster-LicenseEngine\r\n"
                           . "Accept: application/vnd.github.raw+json\r\n"
                           . "Authorization: Bearer {$token}\r\n"
                           . "Cache-Control: no-cache\r\n",
            ],
        ];
        $ctx = stream_context_create($opts);
        $res = @file_get_contents($url, false, $ctx);
        return $res !== false ? $res : null;
    }

    public function getSummary(): array
    {
        $vaultData = $this->vault->readEncryptedFile(self::VAULT_FILE);
        $lastCheckTs = (int)($vaultData['last_checked_at'] ?? 0);
        $expiresRaw = (string)($vaultData['expires_at'] ?? $this->settingModel->get('license_expires_at', ''));
        $city = (string)($vaultData['cnpj_city'] ?? '');
        $uf   = (string)($vaultData['cnpj_uf'] ?? '');

        return [
            'fingerprint'        => $this->vault->getMachineFingerprint(),
            'status'             => $this->settingModel->get('license_status', 'unlicensed'),
            'key'                => $this->settingModel->get('license_key', ''),
            'lock_mode'          => $this->settingModel->get('license_lock_mode', '1'),
            'expires_at'         => !empty($expiresRaw) ? date('d/m/Y', strtotime($expiresRaw)) : 'Não definida',
            'cnpj_location'      => ($city !== '' && $uf !== '') ? "{$city}/{$uf}" : 'Não identificado',
            'cnpj_timezone'      => (string)($vaultData['cnpj_timezone'] ?? 'America/Sao_Paulo'),
            'ntp_server'         => (string)($vaultData['ntp_server'] ?? '-'),
            'ntp_local_datetime' => (string)($vaultData['ntp_local_datetime'] ?? '-'),
            'last_check'         => $lastCheckTs > 0 ? date('d/m/Y H:i:s', $lastCheckTs) : 'Nunca verificado',
            'next_check_in'      => $lastCheckTs > 0 ? max(0, self::CHECK_INTERVAL_SECONDS - (time() - $lastCheckTs)) : 0,
        ];
    }
}
