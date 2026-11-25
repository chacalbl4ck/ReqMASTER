<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class LicenseService
{
    public const CHECK_INTERVAL_SECONDS = 300;
    private const MAX_GRACE_FAILS = 3;
    private const STATE_NODE = '.rm_core_state.dat';
    private const LEGACY_NODE = 'license.enc';

    private VaultService $vault;
    private Setting $settingModel;
    private NtpTimeService $ntpService;
    private static bool $shutdownScheduled = false;

    public function __construct(?Setting $settingModel = null)
    {
        $this->vault = new VaultService();
        $this->settingModel = $settingModel ?? new Setting();
        $this->ntpService = new NtpTimeService();
    }

    public function isValidFormat(string $key): bool
    {
        $normalized = strtoupper(trim($key));
        return (bool)preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $normalized);
    }

    public function enforce(): array
    {
        $fp = $this->vault->getMachineFingerprint();
        $node = $this->readNodeState();

        $sSt = $this->settingModel->get('license_status', 'unlicensed');
        $sKy = strtoupper(trim((string)$this->settingModel->get('license_key', '')));
        $sLk = $this->settingModel->get('license_lock_mode', '1');
        $sPf = $this->settingModel->get('license_proof', '');

        if ($sLk === '1' && $sSt !== 'active') {
            return ['valid' => false, 'reason' => 'Sistema não licenciado ou aguardando ativação.'];
        }

        if (!$node || empty($sKy) || $sSt !== 'active' || $sLk === '1') {
            $this->revokeNodeState('unlicensed');
            return ['valid' => false, 'reason' => 'Licença não ativada ou sistema bloqueado.'];
        }

        if (
            ($node['fp'] ?? '') !== $fp ||
            strtoupper((string)($node['k'] ?? '')) !== $sKy ||
            ($node['st'] ?? '') !== 'active'
        ) {
            $this->revokeNodeState('tampered');
            return ['valid' => false, 'reason' => 'Falha na verificação de integridade da licença.'];
        }

        $va = (int)($node['va'] ?? 0);
        $ex = (string)($node['ex'] ?? '');
        $proofPayload = implode('|', [$sKy, $fp, $va, $ex, 'active']);
        if (empty($sPf) || !$this->vault->hmacVerify($proofPayload, $sPf)) {
            $this->revokeNodeState('tampered');
            return ['valid' => false, 'reason' => 'Assinatura de integridade da licença inválida.'];
        }

        $no = (int)($node['no'] ?? 0);
        $exu = (int)($node['exu'] ?? 0);
        if ($exu > 0 && (time() + $no) > $exu) {
            $res = $this->dispatchStateSync($sKy, $node);
            if (!$res['valid']) {
                return $res;
            }
            return $res;
        }

        $rolledBack = time() < ($va - 120);
        $lc = (int)($node['lc'] ?? 0);

        if ($rolledBack) {
            return $this->dispatchStateSync($sKy, $node);
        }

        if ((time() - $lc) >= self::CHECK_INTERVAL_SECONDS) {
            if ($this->canDeferSync()) {
                if (!self::$shutdownScheduled) {
                    self::$shutdownScheduled = true;
                    register_shutdown_function(function () use ($sKy, $node): void {
                        if (function_exists('fastcgi_finish_request')) {
                            @fastcgi_finish_request();
                        } elseif (function_exists('litespeed_finish_request')) {
                            @litespeed_finish_request();
                        }
                        $this->dispatchStateSync($sKy, $node);
                    });
                }
                return ['valid' => true, 'key' => $sKy, 'last_checked_at' => $lc];
            }
            return $this->dispatchStateSync($sKy, $node);
        }

        return ['valid' => true, 'key' => $sKy, 'last_checked_at' => $lc];
    }

    public function activateKey(string $rawKey): array
    {
        $key = strtoupper(trim($rawKey));

        if (!$this->isValidFormat($key)) {
            return [
                'success' => false,
                'message' => 'Formato de chave inválido. Utilize o padrão oficial XXXX-XXXX-XXXX-XXXX (16 dígitos).'
            ];
        }

        $remote = $this->queryRemoteNode($key);
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

        $this->commitNodeState($key, $expiresAt, $timeInfo);

        $expText = !empty($expiresAt) ? (' Válida até ' . date('d/m/Y', strtotime($expiresAt)) . '.') : '';
        return [
            'success' => true,
            'message' => "Licença validada com sucesso via NTP ({$timeInfo['ntp_server']} • {$timeInfo['city']}/{$timeInfo['uf']})!{$expText}"
        ];
    }

    private function canDeferSync(): bool
    {
        return PHP_SAPI !== 'cli' && (function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request'));
    }

    private function dispatchStateSync(string $key, array $node): array
    {
        $lockFile = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'rm_nsync_' . substr(md5(__DIR__), 0, 10) . '.lock';
        $fh = @fopen($lockFile, 'c+');
        if ($fh) {
            if (!@flock($fh, LOCK_EX | LOCK_NB)) {
                @fclose($fh);
                return ['valid' => true, 'key' => $key, 'last_checked_at' => (int)($node['lc'] ?? time())];
            }
            $lastRun = (int)trim((string)@stream_get_contents($fh));
            if ($lastRun > 0 && (time() - $lastRun) < 15) {
                @flock($fh, LOCK_UN);
                @fclose($fh);
                return ['valid' => true, 'key' => $key, 'last_checked_at' => (int)($node['lc'] ?? time())];
            }
            @ftruncate($fh, 0);
            @rewind($fh);
            @fwrite($fh, (string)time());
            @fflush($fh);
        }

        try {
            $remote = $this->queryRemoteNode($key);

            if ($remote['network_error']) {
                $fails = (int)($node['of'] ?? 0) + 1;
                if ($fails >= self::MAX_GRACE_FAILS) {
                    $this->revokeNodeState('offline_expired');
                    return ['valid' => false, 'reason' => 'Não foi possível validar a licença online após múltiplas tentativas.'];
                }
                $node['of'] = $fails;
                $node['lc'] = time();
                $this->writeNodeState($node);
                return ['valid' => true, 'key' => $key];
            }

            if (!$remote['valid']) {
                $this->revokeNodeState('revoked');
                return ['valid' => false, 'reason' => 'Sua licença foi revogada ou removida da base oficial.'];
            }

            $cnpjToUse = !empty($remote['cnpj'])
                ? $remote['cnpj']
                : (!empty($node['doc']) ? (string)$node['doc'] : preg_replace('/\D/', '', (string)$this->settingModel->get('company_cnpj', '')));

            $cachedGeo = [
                'cnpj'       => $node['doc'] ?? '',
                'city'       => $node['ct'] ?? '',
                'uf'         => $node['uf'] ?? '',
                'timezone'   => $node['tz'] ?? '',
                'found'      => !empty($node['gf']),
                'checked_at' => (int)($node['ga'] ?? 0),
            ];

            $timeInfo = $this->ntpService->getAuthoritativeCityTime($cnpjToUse, $cachedGeo);
            if (!$timeInfo['ntp_success']) {
                $fails = (int)($node['of'] ?? 0) + 1;
                if ($fails >= self::MAX_GRACE_FAILS) {
                    $this->revokeNodeState('ntp_error');
                    return ['valid' => false, 'reason' => 'Falha ao consultar servidores NTP principal e backup para validar data da licença.'];
                }
                $node['of'] = $fails;
                $node['lc'] = time();
                $this->writeNodeState($node);
                return ['valid' => true, 'key' => $key];
            }

            $expiresAt = $remote['expires_at'];
            if (!empty($expiresAt) && $timeInfo['local_date'] > $expiresAt) {
                $this->revokeNodeState('expired');
                $expBr = date('d/m/Y', strtotime($expiresAt));
                return [
                    'valid'  => false,
                    'reason' => "Licença expirada em {$expBr} (horário oficial em {$timeInfo['city']}/{$timeInfo['uf']}: {$timeInfo['local_br']})."
                ];
            }

            $this->commitNodeState($key, $expiresAt, $timeInfo);
            return ['valid' => true, 'key' => $key, 'last_checked_at' => time()];
        } finally {
            if ($fh) {
                @flock($fh, LOCK_UN);
                @fclose($fh);
            }
        }
    }

    private function queryRemoteNode(string $licenseKey): array
    {
        $nodeId = $this->vault->getCredential('0x4c');
        $authTk = $this->vault->getCredential('0x4d');
        $epBase = $this->vault->getCredential('0x4f');
        $epPri  = $this->vault->getCredential('0x50');
        $epSec  = $this->vault->getCredential('0x51');

        if ($nodeId === '' || $authTk === '' || $epBase === '' || $epPri === '') {
            return ['valid' => false, 'network_error' => true];
        }

        $content = $this->readEndpointPayload($epBase . $nodeId . $epPri, $authTk);
        if ($content === null && $epSec !== '' && $epSec !== $epPri) {
            $content = $this->readEndpointPayload($epBase . $nodeId . $epSec, $authTk);
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
            $candidate = strtoupper(trim((string)($parts[0] ?? '')));

            if ($candidate !== '' && hash_equals($candidate, $target)) {
                $expiresAt = null;
                $cnpj = '';

                for ($i = 1; $i < count($parts); $i++) {
                    $seg = trim((string)$parts[$i]);
                    if ($expiresAt === null && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $seg, $m)) {
                        if (checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                            $expiresAt = $seg;
                        }
                    } elseif ($expiresAt === null && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $seg, $m)) {
                        if (checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
                            $expiresAt = "{$m[3]}-{$m[2]}-{$m[1]}";
                        }
                    } else {
                        $digits = preg_replace('/\D/', '', $seg);
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

    private function commitNodeState(string $key, ?string $expiresAt, array $timeInfo): void
    {
        $now = time();
        $fp = $this->vault->getMachineFingerprint();
        $expiresStr = (string)($expiresAt ?? '');
        $payload = implode('|', [$key, $fp, $now, $expiresStr, 'active']);
        $proof = $this->vault->hmacSign($payload);

        $expiresAtUtc = 0;
        if ($expiresStr !== '') {
            try {
                $tz = new DateTimeZone($timeInfo['timezone'] ?: 'America/Sao_Paulo');
                $dtExp = new DateTimeImmutable($expiresStr . ' 23:59:59', $tz);
                $expiresAtUtc = $dtExp->getTimestamp();
            } catch (Throwable $e) {
                $expiresAtUtc = 0;
            }
        }

        $ntpOffset = (int)$timeInfo['utc_timestamp'] - $now;

        $this->writeNodeState([
            'k'   => $key,
            'fp'  => $fp,
            'st'  => 'active',
            'va'  => $now,
            'lc'  => $now,
            'of'  => 0,
            'ex'  => $expiresStr,
            'exu' => $expiresAtUtc,
            'no'  => $ntpOffset,
            'ns'  => $timeInfo['ntp_server'] ?? '',
            'nl'  => $timeInfo['local_br'] ?? '',
            'doc' => $timeInfo['cnpj'] ?? '',
            'ct'  => $timeInfo['city'] ?? '',
            'uf'  => $timeInfo['uf'] ?? '',
            'tz'  => $timeInfo['timezone'] ?? 'America/Sao_Paulo',
            'gf'  => !empty($timeInfo['geo_found']),
            'ga'  => $now,
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

    private function readNodeState(): ?array
    {
        $raw = $this->vault->readEncryptedFile(self::STATE_NODE);
        if (is_array($raw) && isset($raw['k'], $raw['fp'])) {
            return $raw;
        }
        $legacy = $this->vault->readEncryptedFile(self::LEGACY_NODE);
        if (!is_array($legacy) || !isset($legacy['key'], $legacy['fingerprint'])) {
            return null;
        }
        $migrated = [
            'k'   => $legacy['key'] ?? '',
            'fp'  => $legacy['fingerprint'] ?? '',
            'st'  => $legacy['status'] ?? '',
            'va'  => $legacy['verified_at'] ?? 0,
            'lc'  => $legacy['last_checked_at'] ?? 0,
            'of'  => $legacy['offline_fails'] ?? 0,
            'ex'  => $legacy['expires_at'] ?? '',
            'exu' => $legacy['expires_at_utc'] ?? 0,
            'no'  => $legacy['ntp_offset'] ?? 0,
            'ns'  => $legacy['ntp_server'] ?? '',
            'nl'  => $legacy['ntp_local_datetime'] ?? '',
            'doc' => $legacy['cnpj'] ?? '',
            'ct'  => $legacy['cnpj_city'] ?? '',
            'uf'  => $legacy['cnpj_uf'] ?? '',
            'tz'  => $legacy['cnpj_timezone'] ?? 'America/Sao_Paulo',
            'gf'  => !empty($legacy['geo_found']),
            'ga'  => (int)($legacy['last_checked_at'] ?? time()),
        ];
        if ($this->writeNodeState($migrated)) {
            $this->vault->deleteVaultFile(self::LEGACY_NODE);
        }
        return $migrated;
    }

    private function writeNodeState(array $node): bool
    {
        return $this->vault->writeEncryptedFile(self::STATE_NODE, $node);
    }

    private function revokeNodeState(string $status): void
    {
        try {
            $this->vault->deleteVaultFile(self::STATE_NODE);
            $this->vault->deleteVaultFile(self::LEGACY_NODE);
            $this->settingModel->setMany([
                'license_status'    => $status,
                'license_lock_mode' => '1',
                'license_proof'     => '',
            ]);
        } catch (Throwable $e) {
        }
    }

    private function readEndpointPayload(string $url, string $token): ?string
    {
        $acceptHdr = $this->vault->getCredential('0x55') ?: 'application/octet-stream';
        $verHdr    = $this->vault->getCredential('0x58');
        $ua        = 'ReqMaster-CoreClient/2.0';

        if (function_exists('curl_init')) {
            $headers = [
                'User-Agent: ' . $ua,
                'Accept: ' . $acceptHdr,
                'Authorization: Bearer ' . $token,
                'Cache-Control: no-cache, no-store',
            ];
            if ($verHdr !== '') {
                $headers[] = $verHdr;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => $headers,
            ]);
            $res = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($res !== false && $code >= 200 && $code < 300) {
                return (string)$res;
            }
            return null;
        }

        $headerLines = "User-Agent: {$ua}\r\n"
                     . "Accept: {$acceptHdr}\r\n"
                     . "Authorization: Bearer {$token}\r\n"
                     . ($verHdr !== '' ? "{$verHdr}\r\n" : '')
                     . "Cache-Control: no-cache\r\n";

        $opts = [
            'http' => [
                'method'  => 'GET',
                'timeout' => 5,
                'header'  => $headerLines,
            ],
        ];
        $ctx = stream_context_create($opts);
        $res = @file_get_contents($url, false, $ctx);
        return $res !== false ? $res : null;
    }

    public function getSummary(): array
    {
        $node = $this->readNodeState() ?? [];
        $lastCheckTs = (int)($node['lc'] ?? 0);
        $expiresRaw = (string)($node['ex'] ?? $this->settingModel->get('license_expires_at', ''));
        $city = (string)($node['ct'] ?? '');
        $uf   = (string)($node['uf'] ?? '');

        return [
            'fingerprint'        => $this->vault->getMachineFingerprint(),
            'status'             => $this->settingModel->get('license_status', 'unlicensed'),
            'key'                => $this->settingModel->get('license_key', ''),
            'lock_mode'          => $this->settingModel->get('license_lock_mode', '1'),
            'expires_at'         => $expiresRaw !== '' ? date('d/m/Y', strtotime($expiresRaw)) : 'Não definida',
            'cnpj_location'      => ($city !== '' && $uf !== '') ? "{$city}/{$uf}" : 'Não identificado',
            'cnpj_timezone'      => (string)($node['tz'] ?? 'America/Sao_Paulo'),
            'ntp_server'         => (string)($node['ns'] ?? '-'),
            'ntp_local_datetime' => (string)($node['nl'] ?? '-'),
            'last_check'         => $lastCheckTs > 0 ? date('d/m/Y H:i:s', $lastCheckTs) : 'Nunca verificado',
            'next_check_in'      => $lastCheckTs > 0 ? max(0, self::CHECK_INTERVAL_SECONDS - (time() - $lastCheckTs)) : 0,
        ];
    }
}
