<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Config\Database;
use PDO;

class Security
{
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
        // 1. Verificação padrão BCRYPT
        if (password_verify($password, $hash)) {
            return true;
        }

        // 2. Fallback para senha inicial texto plano ou hash legado (com auto-upgrade)
        if ($password === $hash || md5($password) === $hash) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET password = :pwd WHERE id = :id");
            $stmt->execute(['pwd' => $newHash, 'id' => $userId]);
            return true;
        }

        return false;
    }

    public static function formatDocument(?string $doc): string
    {
        if (!$doc) return '-';
        $cleaned = preg_replace('/\D/', '', $doc);
        
        if (strlen($cleaned) === 11) {
            // CPF: 000.000.000-00
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cleaned);
        } elseif (strlen($cleaned) === 14) {
            // CNPJ: 00.000.000/0000-00
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cleaned);
        }
        
        return $doc;
    }
}
