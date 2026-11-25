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
            if (!in_array('view_token', $columns, true)) {
                $this->db->exec("ALTER TABLE `requisitions` ADD COLUMN `view_token` VARCHAR(128) NULL UNIQUE AFTER `code`;");
            }
            if (!in_array('is_equipment_repair', $columns, true)) {
                $this->db->exec("ALTER TABLE `requisitions` ADD COLUMN `is_equipment_repair` TINYINT(1) NOT NULL DEFAULT 0 AFTER `approver_signature`;");
            }
            if (!in_array('linked_product_id', $columns, true)) {
                $this->db->exec("ALTER TABLE `requisitions` ADD COLUMN `linked_product_id` INT UNSIGNED NULL AFTER `is_equipment_repair`;");
            }
            if (!in_array('maintenance_type', $columns, true)) {
                $this->db->exec("ALTER TABLE `requisitions` ADD COLUMN `maintenance_type` ENUM('reparo', 'preventiva') NULL DEFAULT NULL AFTER `linked_product_id`;");
            }
            if (!in_array('completed_at', $columns, true)) {
                $this->db->exec("ALTER TABLE `requisitions` ADD COLUMN `completed_at` DATETIME NULL AFTER `approved_at`;");
            }

            // 4. Verifica e adiciona colunas de avatar e 2FA TOTP na tabela `users`
            $userColsStmt = $this->db->query("SHOW COLUMNS FROM `users`");
            $userCols = $userColsStmt->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('avatar', $userCols, true)) {
                $this->db->exec("ALTER TABLE `users` ADD COLUMN `avatar` VARCHAR(255) NULL AFTER `phone`;");
            }
            if (!in_array('totp_secret', $userCols, true)) {
                $this->db->exec("ALTER TABLE `users` ADD COLUMN `totp_secret` VARCHAR(128) NULL AFTER `avatar`;");
            }
            if (!in_array('totp_enabled', $userCols, true)) {
                $this->db->exec("ALTER TABLE `users` ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`;");
            }
            if (!in_array('totp_recovery_codes', $userCols, true)) {
                $this->db->exec("ALTER TABLE `users` ADD COLUMN `totp_recovery_codes` TEXT NULL AFTER `totp_enabled`;");
            }

            // 4b. Tabela de controle contra força bruta
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `security_attempts` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `ip_address` VARCHAR(45) NOT NULL,
                    `context` VARCHAR(50) NOT NULL,
                    `identifier` VARCHAR(190) NULL,
                    `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
                    `locked_until` DATETIME NULL,
                    `last_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uniq_ip_ctx_id` (`ip_address`, `context`, `identifier`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 4c. Tabela de Centros de Custo
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `cost_centers` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `code` VARCHAR(30) NOT NULL UNIQUE,
                    `name` VARCHAR(120) NOT NULL,
                    `description` VARCHAR(255) NULL,
                    `active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 4d. Tabelas de Produtos/Equipamentos, Veículos, Fotos e Serviços
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `products` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(200) NOT NULL,
                    `serial_number` VARCHAR(120) NULL,
                    `is_vehicle` TINYINT(1) NOT NULL DEFAULT 0,
                    `chassis` VARCHAR(80) NULL,
                    `license_plate` VARCHAR(20) NULL,
                    `current_km` INT UNSIGNED NULL,
                    `notes` TEXT NULL,
                    `maintenance_interval_days` INT UNSIGNED NULL,
                    `maintenance_interval_km` INT UNSIGNED NULL,
                    `last_maintenance_at` DATE NULL,
                    `last_maintenance_km` INT UNSIGNED NULL,
                    `active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Migra colunas de veículo em bases existentes
            $prodColsStmt = $this->db->query("SHOW COLUMNS FROM `products`");
            $prodCols = $prodColsStmt->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('is_vehicle', $prodCols, true)) {
                $this->db->exec("ALTER TABLE `products` ADD COLUMN `is_vehicle` TINYINT(1) NOT NULL DEFAULT 0 AFTER `serial_number`;");
            }
            if (!in_array('chassis', $prodCols, true)) {
                $this->db->exec("ALTER TABLE `products` ADD COLUMN `chassis` VARCHAR(80) NULL AFTER `is_vehicle`;");
            }
            if (!in_array('license_plate', $prodCols, true)) {
                $this->db->exec("ALTER TABLE `products` ADD COLUMN `license_plate` VARCHAR(20) NULL AFTER `chassis`;");
            }
            if (!in_array('current_km', $prodCols, true)) {
                $this->db->exec("ALTER TABLE `products` ADD COLUMN `current_km` INT UNSIGNED NULL AFTER `license_plate`;");
            }
            if (!in_array('maintenance_interval_km', $prodCols, true)) {
                $this->db->exec("ALTER TABLE `products` ADD COLUMN `maintenance_interval_km` INT UNSIGNED NULL AFTER `maintenance_interval_days`;");
            }
            if (!in_array('last_maintenance_km', $prodCols, true)) {
                $this->db->exec("ALTER TABLE `products` ADD COLUMN `last_maintenance_km` INT UNSIGNED NULL AFTER `last_maintenance_at`;");
            }

            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `product_subitems` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `product_id` INT UNSIGNED NOT NULL,
                    `name` VARCHAR(200) NOT NULL,
                    `notes` VARCHAR(255) NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT `fk_product_subitems_product` FOREIGN KEY (`product_id`)
                        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `vehicle_mileage_logs` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `product_id` INT UNSIGNED NOT NULL,
                    `user_id` INT UNSIGNED NULL,
                    `mileage_km` INT UNSIGNED NOT NULL,
                    `odometer_photo` VARCHAR(255) NULL,
                    `notes` VARCHAR(255) NULL,
                    `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT `fk_mileage_product` FOREIGN KEY (`product_id`)
                        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk_mileage_user` FOREIGN KEY (`user_id`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `product_photos` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `product_id` INT UNSIGNED NOT NULL,
                    `filename` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT `fk_product_photos_product` FOREIGN KEY (`product_id`)
                        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `services` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(200) NOT NULL,
                    `notes` TEXT NULL,
                    `active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 5. Remove chaves legadas/expostas caso existam de versões anteriores
            $this->db->exec("DELETE FROM `settings` WHERE `key_name` = 'github_repo'");
            $this->db->exec("UPDATE `settings` SET `value` = '' WHERE `key_name` = 'license_key' AND `value` != '' AND `value` NOT REGEXP '^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$'");

            // 6. Configurações padrão limpas (White-label e travado até ativação oficial)
            $defaults = [
                'license_status'        => 'unlicensed',
                'license_key'           => '',
                'license_lock_mode'     => '1',
                'license_proof'         => '',
                'turnstile_enabled'     => '0',
                'turnstile_site_key'    => '',
                'turnstile_secret_key'  => '',
                'is_web_hosted'         => '0',
                'enable_qrcode_link'    => '0',
                'block_search_bots'     => '0',
                'mtls_port'             => '8443',
                'mtls_url'              => '',
                'theme_primary_color'   => '#4f46e5',
                'theme_secondary_color' => '#4338ca',
                'theme_accent_color'    => '#6366f1',
                'req_primary_color'     => '#4338ca',
                'req_bg_tint'           => '#eef2ff',
                'req_border_color'      => '#cbd5e1',
            ];

            foreach ($defaults as $k => $v) {
                $stmtCheck = $this->db->prepare("SELECT COUNT(*) FROM `settings` WHERE `key_name` = :k");
                $stmtCheck->execute(['k' => $k]);
                if ((int)$stmtCheck->fetchColumn() === 0) {
                    $stmtIns = $this->db->prepare("INSERT INTO `settings` (`key_name`, `value`) VALUES (:k, :v)");
                    $stmtIns->execute(['k' => $k, 'v' => $v]);
                }
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
