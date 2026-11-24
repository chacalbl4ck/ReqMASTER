<?php
declare(strict_types=1);

namespace App\Services;

class VaultService
{
    private const VAULT_BLOBS = [
        'upd_repo'  => 'j6mXYAl638XiwifduuPPQAwYWffaY9BNZuWbAGk4oE4VVyMFIznqQ+3qaTpSaJ0ahg==',
        'upd_token' => 'X3bKBffoel2yNH38o2aI14c3wVfrsr9bgLM2zMH7zbFZ7Qt0dA0vmuKCyM1sBLaj4VSi0mBHQvXBV3Lt3TFWmRc9yBJK2tpcrSAsHhwRvrBEKneEqAjwFD6D8YhHDVi+VBj471vj7ROFU9HPhDg2GF8evM4dVmr5/Q==',
        'lic_repo'  => 'i8gnsRE5bthC7BHXPPO+3GOZDQ2auoP4x3fZ14hWljSfdNZMdkiuUXAmFrzIFlmRLeDwDxnHNaXO',
        'lic_token' => 'sBp9P7e8ymsdiT290gzZgy9dSybcDa4rSkqnr7ftHDBsofSIaclS2A2/l0HlgCbRgKIRJ4ZbXEQ2jj8ciw4Hra2GrauvHOVndMWsk8dfOUCs0cmmy0Nq/aZJhnbqGv3Um588nYJMLeEFiz3b6bSK0n92VGsKY7R2hg==',
        'lic_file'  => 'QL6KuSm7k9plm164vGRLyNFqpSWNjmRB9dQFlkR75PhdCW8J',
    ];

    private string $vaultDir;

    public function __construct()
    {
        $this->vaultDir = __DIR__ . '/../../config/vault';
        $this->ensureVaultDir();
    }

    private function ensureVaultDir(): void
    {
        if (!is_dir($this->vaultDir)) {
            @mkdir($this->vaultDir, 0750, true);
        }
        $htaccess = $this->vaultDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        }
    }

    private function getEmbeddedKey(): string
    {
        $seed = implode('', ['RM_', '2026_', 'Vault_', 'Master_', '99xK_', 'Sec']);
        return hash('sha256', $seed, true);
    }

    private function getServerBoundKey(): string
    {
        $root = realpath(__DIR__ . '/../../') ?: (__DIR__ . '/../../');
        $host = php_uname('n') ?: 'localhost';
        $secret = implode('', ['RM_SRV_', 'BOUND_', 'KEY_', '2026_', 'AES256']);
        return hash('sha256', $secret . '|' . $root . '|' . $host, true);
    }

    public function getCredential(string $key): string
    {
        if (!isset(self::VAULT_BLOBS[$key])) {
            return '';
        }
        $raw = base64_decode(self::VAULT_BLOBS[$key], true);
        if ($raw === false || strlen($raw) <= 28) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->getEmbeddedKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return $decrypted !== false ? $decrypted : '';
    }

    public function getMachineFingerprint(): string
    {
        $root = realpath(__DIR__ . '/../../') ?: __DIR__;
        $uname = php_uname('n') . '|' . php_uname('s');
        $hash = strtoupper(hash('sha256', $root . '|' . $uname));
        return 'RM-' . substr($hash, 0, 4) . '-' . substr($hash, 4, 4) . '-' . substr($hash, 8, 4);
    }

    public function encryptServerData(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($json, 'aes-256-gcm', $this->getServerBoundKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $ct);
    }

    public function decryptServerData(string $encoded): ?array
    {
        $raw = base64_decode(trim($encoded), true);
        if ($raw === false || strlen($raw) <= 28) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct = substr($raw, 28);

        $json = openssl_decrypt($ct, 'aes-256-gcm', $this->getServerBoundKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($json === false) {
            return null;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function readEncryptedFile(string $filename): ?array
    {
        $path = $this->vaultDir . '/' . basename($filename);
        if (!file_exists($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return null;
        }
        return $this->decryptServerData($content);
    }

    public function writeEncryptedFile(string $filename, array $data): bool
    {
        $this->ensureVaultDir();
        $path = $this->vaultDir . '/' . basename($filename);
        $blob = $this->encryptServerData($data);
        return @file_put_contents($path, $blob, LOCK_EX) !== false;
    }

    public function deleteVaultFile(string $filename): void
    {
        $path = $this->vaultDir . '/' . basename($filename);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public function hmacSign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->getServerBoundKey() . $this->getEmbeddedKey());
    }

    public function hmacVerify(string $payload, string $signature): bool
    {
        $expected = $this->hmacSign($payload);
        return hash_equals($expected, $signature);
    }

    public function getVaultDir(): string
    {
        return $this->vaultDir;
    }
}
