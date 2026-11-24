<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Throwable;

class LicenseService
{
    public const CHECK_INTERVAL_SECONDS = 300; // 5 minutos
    private const MAX_OFFLINE_GRACE_FAILS = 3;
    private const VAULT_FILE = 'license.enc';

    private VaultService $vault;
    private Setting $settingModel;

    public function __construct(?Setting $settingModel = null)
    {
        $this->vault = new VaultService();
        $this->settingModel = $settingModel ?? new Setting();
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
     * Verifica a integridade local e executa a checagem remota a cada 5 minutos.
     * Impede adulteração direta no banco de dados ou arquivos locais.
     */
    public function enforce(): array
    {
        $fingerprint = $this->vault->getMachineFingerprint();
        $vaultData = $this->vault->readEncryptedFile(self::VAULT_FILE);

        $dbStatus = $this->settingModel->get('license_status', 'unlicensed');
        $dbKey    = strtoupper(trim((string)$this->settingModel->get('license_key', '')));
        $dbLock   = $this->settingModel->get('license_lock_mode', '1');
        $dbProof  = $this->settingModel->get('license_proof', '');

        // Bloqueio manual explícito pelo administrador
        if ($dbLock === '1' && $dbStatus !== 'active') {
            return ['valid' => false, 'reason' => 'Sistema não licenciado ou aguardando ativação.'];
        }

        // Anti-burla: exige vault criptografado e prova HMAC compatível com o banco e servidor
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

        $expectedProofPayload = $dbKey . '|' . $fingerprint . '|' . ($vaultData['verified_at'] ?? 0) . '|active';
        if (empty($dbProof) || !$this->vault->hmacVerify($expectedProofPayload, $dbProof)) {
            $this->lockLocally('tampered');
            return ['valid' => false, 'reason' => 'Assinatura de integridade da licença inválida.'];
        }

        // Checagem periódica obrigatória a cada 5 minutos (300 segundos)
        $lastChecked = (int)($vaultData['last_checked_at'] ?? 0);
        if ((time() - $lastChecked) >= self::CHECK_INTERVAL_SECONDS) {
            $remoteCheck = $this->verifyKeyRemotely($dbKey);

            if ($remoteCheck['network_error']) {
                $fails = (int)($vaultData['offline_fails'] ?? 0) + 1;
                if ($fails >= self::MAX_OFFLINE_GRACE_FAILS) {
                    $this->lockLocally('offline_expired');
                    return ['valid' => false, 'reason' => 'Não foi possível validar a licença online após múltiplas tentativas.'];
                }
                $vaultData['offline_fails'] = $fails;
                $vaultData['last_checked_at'] = time();
                $this->vault->writeEncryptedFile(self::VAULT_FILE, $vaultData);
            } elseif (!$remoteCheck['valid']) {
                // Chave removida ou revogada no repositório oficial
                $this->lockLocally('revoked');
                return ['valid' => false, 'reason' => 'Sua licença foi revogada ou não consta na base oficial.'];
            } else {
                // Renovação do selo de 5 minutos
                $now = time();
                $this->persistValidLicense($dbKey, $now);
            }
        }

        return ['valid' => true, 'key' => $dbKey, 'last_checked_at' => $vaultData['last_checked_at'] ?? time()];
    }

    /**
     * Ativa uma chave de licença validando online na base oficial.
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

        $now = time();
        $this->persistValidLicense($key, $now);

        return [
            'success' => true,
            'message' => 'Licença verificada e ativada com sucesso! O sistema foi desbloqueado.'
        ];
    }

    /**
     * Força verificação imediata da licença atual contra o servidor oficial.
     */
    public function forceRecheck(): array
    {
        $dbKey = strtoupper(trim((string)$this->settingModel->get('license_key', '')));
        if (empty($dbKey) || !$this->isValidFormat($dbKey)) {
            $this->lockLocally('unlicensed');
            return ['success' => false, 'message' => 'Nenhuma chave válida configurada.'];
        }

        $remote = $this->verifyKeyRemotely($dbKey);
        if ($remote['network_error']) {
            return ['success' => false, 'message' => 'Servidor de licenciamento indisponível no momento.'];
        }

        if (!$remote['valid']) {
            $this->lockLocally('revoked');
            return ['success' => false, 'message' => 'Chave não encontrada ou revogada na base oficial. Sistema bloqueado.'];
        }

        $this->persistValidLicense($dbKey, time());
        return ['success' => true, 'message' => 'Licença confirmada com sucesso no servidor oficial.'];
    }

    /**
     * Trava o sistema manualmente pelo administrador.
     */
    public function lockManually(): void
    {
        $this->settingModel->set('license_lock_mode', '1');
        $vaultData = $this->vault->readEncryptedFile(self::VAULT_FILE);
        if (is_array($vaultData)) {
            $vaultData['status'] = 'locked';
            $this->vault->writeEncryptedFile(self::VAULT_FILE, $vaultData);
        }
    }

    /**
     * Consulta remota segura ao repositório privado de licenças (arquivo 'licences').
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
            // Suporta linha contendo apenas a chave ou chave seguida de comentário/separador
            $candidate = strtoupper(preg_split('/[\s|;#]+/', $clean)[0] ?? '');
            if ($candidate !== '' && hash_equals($candidate, $target)) {
                return ['valid' => true, 'network_error' => false];
            }
        }

        return ['valid' => false, 'network_error' => false];
    }

    private function persistValidLicense(string $key, int $timestamp): void
    {
        $fingerprint = $this->vault->getMachineFingerprint();
        $payload = $key . '|' . $fingerprint . '|' . $timestamp . '|active';
        $proof = $this->vault->hmacSign($payload);

        $this->vault->writeEncryptedFile(self::VAULT_FILE, [
            'key'             => $key,
            'fingerprint'     => $fingerprint,
            'status'          => 'active',
            'verified_at'     => $timestamp,
            'last_checked_at' => $timestamp,
            'offline_fails'   => 0,
        ]);

        $this->settingModel->setMany([
            'license_status'       => 'active',
            'license_key'          => $key,
            'license_lock_mode'    => '0',
            'license_proof'        => $proof,
            'license_last_check'   => date('Y-m-d H:i:s', $timestamp),
            'license_activated_at' => $this->settingModel->get('license_activated_at') ?: date('Y-m-d H:i:s', $timestamp),
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
            // Ignora falha silenciosa durante lock de emergência
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
        return [
            'fingerprint'   => $this->vault->getMachineFingerprint(),
            'status'        => $this->settingModel->get('license_status', 'unlicensed'),
            'key'           => $this->settingModel->get('license_key', ''),
            'lock_mode'     => $this->settingModel->get('license_lock_mode', '1'),
            'last_check'    => $lastCheckTs > 0 ? date('d/m/Y H:i:s', $lastCheckTs) : 'Nunca verificado',
            'next_check_in' => $lastCheckTs > 0 ? max(0, self::CHECK_INTERVAL_SECONDS - (time() - $lastCheckTs)) : 0,
        ];
    }
}
