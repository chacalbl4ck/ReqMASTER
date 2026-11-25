<?php
declare(strict_types=1);

namespace App\Services;

class VaultService
{
    private const VAULT_BLOBS = [
        '0x4a' => 'LMOOQymE8fWzyC6Hbb1HoAreVGMuiMONyDL6PQzBVthG+d77WucvzGc57rlbY0R5dw==',
        '0x4b' => 'Inkv1bZrxHrsGIiMPp7aeOFjNz7AZh9lqz4/gRVk49uuGuz7Jmy/ItNkXWHiRUPkDFe6nun4VJ2DlUioTn8BuCMsJXUU/2eUciHhXAnaLLt4ZCl1f7OPjyIlqNF31buwH3VxXrqgRH+BKuUx7FgwWmGB9Q9gAEJ5xg==',
        '0x4c' => 'u+5Jo9sTQ3K8HCRdrj4M6kY14ZuB44a+bX+juNHlAe1y7P2+yUlcRJ70FpEUWPWkhs8cfOpD3RW0',
        '0x4d' => 'yH3G8Jg0iZzobDTVJ6YwamEKKWPJZlfxvcU6T7DQhCF/qetx2ifmwbvWF6QziUea3IXSE+4MjYZpIf2PbA94H3UJlOQI9z6morD+vvhLdhl2AaiU7y9jmJdNI1djYzL71+wyvAc/2nxX7cf1MGQH8YMslHQDKEYS5A==',
        '0x4e' => 'w3qPHLLNosq2352AjDYjFxe0REbi1HFbzCRR16arbyNQD5Fi',
        '0x4f' => 'gHlGyZhpS3DPesKkNpcGMtuXFQOi0sw7MmQ7rXAcrHmj3Lq5aaeI53iEAnFjQrL8A1Bx3t8PKjwT',
        '0x50' => 'WWKt0HNH/k3u0sF56X/4FlbzAaxu/ANJcBJolUvaxJT3+G5qZFK20PeVQMW6eQ==',
        '0x51' => 'kVXFP6bMiFyWmiq1qMgD8mSqUd2z8H/GpQ26pBn27sxjuOpvIJ180ytajMNlMIaKHbI=',
        '0x52' => 'K8dfLyyG9RtPKmfBc9kNU0EeVEzaufYurU3Sdln0zwLeHbOWWouZC+lAREyz5zg=',
        '0x53' => 'o6SdLwJjXY9yZwFYP57up71fReZ4ERZ9NM/CiFkA4GdXI5AQEMDw',
        '0x54' => 'hKSnM2RedaRhtSJ/uyFGuWEaIIcig7ayJ9bBVw4ItOhPPDsrjDqM',
        '0x55' => 'qsJu55LSjPE5+WnXh7cpKMxHkVzwKWww2MZao01w3ZtDjEY9ACgl5mUGOvTYJE+jKhHsscMtbfoClmc=',
        '0x56' => 'rqfwavd5yxlq1u3ZN0LHwkUROgrLT0X0e0nbgQXgjN1x6h6dyY+1FTrHou7rFbagub30iApxvQ==',
        '0x57' => 'YgN9kcDIczSFVAtHkQGJo/9onlVZW5WeJp7Doqh0iDpQhNt85RY=',
        '0x58' => '9MXKrxyzVk+GaxK6bpmaiPNdUv5HjxurA9AQp5LewG8Kqapa/6z4zhrBYe9og3Sw9DWH41fafn/iCWCW',
    ];

    private static bool $dirChecked = false;
    private string $vaultDir;

    public function __construct()
    {
        $this->vaultDir = __DIR__ . '/../../config/vault';
        $this->ensureVaultDir();
    }

    private function ensureVaultDir(): void
    {
        if (self::$dirChecked) {
            return;
        }
        if (!is_dir($this->vaultDir)) {
            @mkdir($this->vaultDir, 0750, true);
        }
        $htaccess = $this->vaultDir . '/.htaccess';
        if (is_dir($this->vaultDir) && is_writable($this->vaultDir) && !file_exists($htaccess)) {
            @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        }
        self::$dirChecked = true;
    }

    private function getEmbeddedKey(): string
    {
        $p1 = [0x41, 0x5e, 0x4c, 0x2a, 0x22, 0x6b];
        $p2 = [0x61, 0x5c, 0x46, 0x5e, 0x75, 0x12];
        $p3 = [0x79, 0x30, 0x3b, 0x1e, 0x31, 0x76];
        $raw = '';
        foreach ($p1 as $b) $raw .= chr($b ^ 0x13);
        foreach ($p2 as $b) $raw .= chr($b ^ 0x2a);
        foreach ($p3 as $b) $raw .= chr($b ^ 0x41);
        return hash('sha256', $raw . '|' . base64_decode('Uk1fQ09SRV9TRUNfMjAyNw=='), true);
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
