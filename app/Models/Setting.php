<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use Exception;

class Setting extends Model
{
    private static bool $initialized = false;
    private static array $cache = [];

    public function __construct()
    {
        parent::__construct();
        $this->ensureTablesAndColumns();
    }

    private function ensureTablesAndColumns(): void
    {
        if (self::$initialized) {
            return;
        }

        try {
            // 1. Tabela de configurações gerais (chave-valor)
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `settings` (
                    `key_name` VARCHAR(100) PRIMARY KEY,
                    `value` LONGTEXT NULL,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 2. Tabela de anexos e fotos das requisições
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `requisition_attachments` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `requisition_id` INT UNSIGNED NOT NULL,
                    `filename` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NOT NULL,
                    `mime_type` VARCHAR(100) NOT NULL,
                    `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT `fk_attachments_requisition` FOREIGN KEY (`requisition_id`) 
                        REFERENCES `requisitions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 3. Verifica e adiciona colunas de assinatura e controle de valores em `requisitions`
            $columnsStmt = $this->db->query("SHOW COLUMNS FROM `requisitions`");
            $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('requester_signature', $columns, true)) {
                $this->db->exec("ALTER TABLE `requisitions` ADD COLUMN `requester_signature` LONGTEXT NULL AFTER `rejection_reason`;");
            }
            if (!in_array('approver_signature', $columns, true)) {
                $this->db->exec("ALTER TABLE `requisitions` ADD COLUMN `approver_signature` LONGTEXT NULL AFTER `requester_signature`;");
            }
            if (!in_array('has_values', $columns, true)) {
                $this->db->exec("ALTER TABLE `requisitions` ADD COLUMN `has_values` TINYINT(1) NOT NULL DEFAULT 1 AFTER `total_amount`;");
            }

            self::$initialized = true;
        } catch (Exception $e) {
            // Log or ignore if table already exists
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $stmt = $this->db->prepare("SELECT `value` FROM `settings` WHERE `key_name` = :k LIMIT 1");
        $stmt->execute(['k' => $key]);
        $val = $stmt->fetchColumn();

        if ($val === false) {
            return $default;
        }

        self::$cache[$key] = (string)$val;
        return (string)$val;
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT `key_name`, `value` FROM `settings`");
        $all = [];
        while ($row = $stmt->fetch()) {
            $all[$row['key_name']] = $row['value'];
            self::$cache[$row['key_name']] = $row['value'];
        }
        return $all;
    }

    public function set(string $key, ?string $value): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO `settings` (`key_name`, `value`) 
            VALUES (:k, :v) 
            ON DUPLICATE KEY UPDATE `value` = :v2
        ");
        $stmt->execute([
            'k' => $key,
            'v' => $value,
            'v2' => $value
        ]);
        self::$cache[$key] = $value;
    }

    public function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set((string)$key, $value !== null ? (string)$value : null);
        }
    }
}
