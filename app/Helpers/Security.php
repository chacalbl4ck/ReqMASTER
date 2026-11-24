<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Config\Database;
use PDO;
use Throwable;

class Security
{
    public static function sendSecurityHeaders(bool $blockBots = false): void
    {
        if (headers_sent() || PHP_SAPI === 'cli') {
            return;
        }
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        if ($blockBots) {
            header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet', true);
        }
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function getUser(): ?array
    {
        if (!self::isLoggedIn()) return null;
        return $_SESSION['user'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            flash('warning', 'Faça login para acessar o sistema.');
            redirect('login');
        }
    }

    public static function hasRole(array|string $roles): bool
    {
        $user = self::getUser();
        if (!$user) return false;

        $roles = (array)$roles;
        return in_array($user['role'], $roles, true);
    }

    public static function requireRole(array|string $roles): void
    {
        self::requireLogin();
        if (!self::hasRole($roles)) {
            flash('danger', 'Acesso negado: você não tem permissão para esta ação.');
            redirect('');
        }
    }

    public static function verifyPassword(string $password, string $hash, int $userId): bool
    {
        if (!password_verify($password, $hash)) {
            return false;
        }

        // Rehash automático caso o algoritmo ou custo do PHP seja atualizado
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            try {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $db = Database::getConnection();
                $stmt = $db->prepare("UPDATE users SET password = :pwd WHERE id = :id");
                $stmt->execute(['pwd' => $newHash, 'id' => $userId]);
            } catch (Throwable $e) {
                // Ignora erro de rehash silenciosamente
            }
        }

        return true;
    }

    /**
     * Valida força mínima de senha forte.
     */
    public static function checkPasswordStrength(string $password): array
    {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = 'mínimo de 8 caracteres';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'pelo menos 1 letra maiúscula';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'pelo menos 1 letra minúscula';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'pelo menos 1 número';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'pelo menos 1 caractere especial (!@#$%...)';
        }

        return [
            'strong' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Proteção contra força bruta (Rate Limiting baseado em banco de dados).
     */
    public static function isRateLimited(string $context, string $identifier = ''): ?int
    {
        $ip = self::getClientIp();
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT attempts, locked_until
                FROM security_attempts
                WHERE ip_address = :ip AND context = :ctx AND identifier = :id
                LIMIT 1
            ");
            $stmt->execute(['ip' => $ip, 'ctx' => $context, 'id' => substr($identifier, 0, 180)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['locked_until'])) {
                $lockedUntilTs = strtotime($row['locked_until']);
                if ($lockedUntilTs && $lockedUntilTs > time()) {
                    return (int)ceil(($lockedUntilTs - time()) / 60);
                }
            }
        } catch (Throwable $e) {
            // Ignora caso tabela ainda não exista
        }
        return null;
    }

    public static function recordFailedAttempt(string $context, string $identifier = '', int $maxAttempts = 5, int $lockMinutes = 15): void
    {
        $ip = self::getClientIp();
        $idShort = substr($identifier, 0, 180);
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT id, attempts, last_attempt_at
                FROM security_attempts
                WHERE ip_address = :ip AND context = :ctx AND identifier = :id
                LIMIT 1
            ");
            $stmt->execute(['ip' => $ip, 'ctx' => $context, 'id' => $idShort]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $ins = $db->prepare("
                    INSERT INTO security_attempts (ip_address, context, identifier, attempts, locked_until, last_attempt_at)
                    VALUES (:ip, :ctx, :id, 1, NULL, NOW())
                ");
                $ins->execute(['ip' => $ip, 'ctx' => $context, 'id' => $idShort]);
                return;
            }

            // Reseta contador se a última tentativa foi há mais de 30 minutos
            $attempts = (strtotime($row['last_attempt_at']) < (time() - 1800)) ? 1 : ((int)$row['attempts'] + 1);
            $lockedUntil = ($attempts >= $maxAttempts) ? date('Y-m-d H:i:s', time() + ($lockMinutes * 60)) : null;

            $upd = $db->prepare("
                UPDATE security_attempts
                SET attempts = :att, locked_until = :lu, last_attempt_at = NOW()
                WHERE id = :id
            ");
            $upd->execute(['att' => $attempts, 'lu' => $lockedUntil, 'id' => $row['id']]);
        } catch (Throwable $e) {
            // Ignora falha silenciosa
        }
    }

    public static function clearAttempts(string $context, string $identifier = ''): void
    {
        $ip = self::getClientIp();
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM security_attempts WHERE ip_address = :ip AND context = :ctx AND identifier = :id");
            $stmt->execute(['ip' => $ip, 'ctx' => $context, 'id' => substr($identifier, 0, 180)]);
        } catch (Throwable $e) {
            // Ignora
        }
    }

    public static function getClientIp(): string
    {
        return substr((string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45);
    }

    /**
     * Valida MIME real (magic bytes) e extensão segura contra upload malicioso.
     */
    public static function validateUploadedFile(array $file, array $allowedExtensions, array $allowedMimePrefixes, int $maxSizeBytes = 10485760): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'Falha no envio do arquivo.'];
        }

        if (($file['size'] ?? 0) > $maxSizeBytes) {
            return ['valid' => false, 'message' => 'O arquivo excede o limite máximo permitido de ' . round($maxSizeBytes / 1048576, 1) . ' MB.'];
        }

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            return ['valid' => false, 'message' => 'Extensão de arquivo não permitida.'];
        }

        // Bloqueia extensões duplas perigosas como shell.php.jpg ou arquivo.phtml
        $baseName = strtolower((string)($file['name'] ?? ''));
        if (preg_match('/\.(php|phtml|php\d|phar|cgi|pl|py|sh|exe|bat)\./i', $baseName)) {
            return ['valid' => false, 'message' => 'Nome de arquivo suspeito bloqueado por segurança.'];
        }

        $realMime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $realMime = (string)finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }

        if (!empty($realMime)) {
            $mimeOk = false;
            foreach ($allowedMimePrefixes as $prefix) {
                if (str_starts_with($realMime, $prefix)) {
                    $mimeOk = true;
                    break;
                }
            }
            if (!$mimeOk) {
                return ['valid' => false, 'message' => "Conteúdo do arquivo ({$realMime}) incompatível com a extensão permitida."];
            }
        }

        return ['valid' => true, 'ext' => $ext, 'mime' => $realMime];
    }

    public static function formatDocument(?string $doc): string
    {
        if (!$doc) return '-';
        $cleaned = preg_replace('/\D/', '', $doc);

        if (strlen($cleaned) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cleaned);
        } elseif (strlen($cleaned) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cleaned);
        }

        return $doc;
    }
}
