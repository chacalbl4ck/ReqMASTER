-- ========================================================
-- REQMASTER - ESQUEMA RELACIONAL DO BANCO DE DADOS
-- Compatível com PHP 8.2 / 8.5+ e MySQL 8.0+ / MariaDB 10.4+
-- ========================================================

CREATE DATABASE IF NOT EXISTS `sistema_requisicoes`
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `sistema_requisicoes`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `security_attempts`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `requisition_attachments`;
DROP TABLE IF EXISTS `requisition_items`;
DROP TABLE IF EXISTS `requisitions`;
DROP TABLE IF EXISTS `contracts`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- 1. TABELA DE USUÁRIOS
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'aprovador', 'solicitante') NOT NULL DEFAULT 'solicitante',
  `department` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `avatar` VARCHAR(255) NULL,
  `totp_secret` VARCHAR(128) NULL,
  `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `totp_recovery_codes` TEXT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. TABELA DE FORNECEDORES / PRESTADORES ("Para quem solicitar")
-- --------------------------------------------------------
CREATE TABLE `suppliers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('pj', 'pf') NOT NULL DEFAULT 'pj',
  `document` VARCHAR(25) NOT NULL UNIQUE,
  `name` VARCHAR(200) NOT NULL,
  `trade_name` VARCHAR(200) NULL,
  `category` VARCHAR(100) NOT NULL,
  `contact_person` VARCHAR(120) NULL,
  `email` VARCHAR(150) NULL,
  `phone` VARCHAR(30) NULL,
  `address` VARCHAR(255) NULL,
  `city` VARCHAR(100) NULL,
  `state` VARCHAR(2) NULL,
  `zip_code` VARCHAR(15) NULL,
  `bank_info` TEXT NULL,
  `notes` TEXT NULL,
  `status` ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. TABELA DE CONTRATOS
-- --------------------------------------------------------
CREATE TABLE `contracts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT UNSIGNED NOT NULL,
  `contract_number` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(200) NOT NULL,
  `type` ENUM('servico', 'material', 'misto') NOT NULL DEFAULT 'servico',
  `total_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `used_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `renewal_clause` VARCHAR(255) NULL,
  `file_path` VARCHAR(255) NULL,
  `status` ENUM('ativo', 'encerrado', 'suspenso', 'cancelado') NOT NULL DEFAULT 'ativo',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_contracts_supplier` FOREIGN KEY (`supplier_id`)
    REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. TABELA DE REQUISIÇÕES
-- --------------------------------------------------------
CREATE TABLE `requisitions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(30) NOT NULL UNIQUE,
  `view_token` VARCHAR(128) NULL UNIQUE,
  `user_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `contract_id` INT UNSIGNED NULL,
  `type` ENUM('material', 'servico') NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `priority` ENUM('baixa', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
  `justification` TEXT NOT NULL,
  `deadline` DATE NULL,
  `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `has_values` TINYINT(1) NOT NULL DEFAULT 1,
  `status` ENUM('rascunho', 'pendente', 'em_analise', 'aprovada', 'rejeitada', 'em_execucao', 'concluida', 'cancelada') NOT NULL DEFAULT 'pendente',
  `rejection_reason` TEXT NULL,
  `requester_signature` LONGTEXT NULL,
  `approver_signature` LONGTEXT NULL,
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_requisitions_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_requisitions_supplier` FOREIGN KEY (`supplier_id`)
    REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_requisitions_contract` FOREIGN KEY (`contract_id`)
    REFERENCES `contracts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_requisitions_approver` FOREIGN KEY (`approved_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. TABELA DE ITENS DA REQUISIÇÃO
-- --------------------------------------------------------
CREATE TABLE `requisition_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `requisition_id` INT UNSIGNED NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'un',
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `technical_specs` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_items_requisition` FOREIGN KEY (`requisition_id`)
    REFERENCES `requisitions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. TABELA DE ANEXOS E FOTOS DAS REQUISIÇÕES
-- --------------------------------------------------------
CREATE TABLE `requisition_attachments` (
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

-- --------------------------------------------------------
-- 7. TABELA DE AUDITORIA / HISTÓRICO DE AÇÕES
-- --------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `requisition_id` INT UNSIGNED NULL,
  `contract_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(80) NOT NULL,
  `description` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_logs_requisition` FOREIGN KEY (`requisition_id`)
    REFERENCES `requisitions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_logs_contract` FOREIGN KEY (`contract_id`)
    REFERENCES `contracts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. TABELA DE CONFIGURAÇÕES DO SISTEMA
-- --------------------------------------------------------
CREATE TABLE `settings` (
  `key_name` VARCHAR(100) PRIMARY KEY,
  `value` LONGTEXT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. TABELA DE CONTROLE DE TENTATIVAS E BLOQUEIO (ANTI-FORÇA BRUTA)
-- --------------------------------------------------------
CREATE TABLE `security_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `context` VARCHAR(50) NOT NULL,
  `identifier` VARCHAR(190) NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
  `locked_until` DATETIME NULL,
  `last_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_ip_ctx_id` (`ip_address`, `context`, `identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
