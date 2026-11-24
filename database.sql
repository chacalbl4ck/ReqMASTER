-- ========================================================
-- SISTEMA DE GESTÃO DE REQUISIÇÕES (SERVIÇOS & MATERIAIS)
-- Compatível com PHP 8.5 & MySQL 8.0+ / MariaDB 10.4+
-- ========================================================

CREATE DATABASE IF NOT EXISTS `sistema_requisicoes` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `sistema_requisicoes`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_logs`;
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
  `document` VARCHAR(25) NOT NULL UNIQUE, -- CNPJ ou CPF
  `name` VARCHAR(200) NOT NULL,           -- Razão Social ou Nome Completo
  `trade_name` VARCHAR(200) NULL,         -- Nome Fantasia
  `category` VARCHAR(100) NOT NULL,       -- Categoria (ex: TI, Obras, Papelaria, Limpeza)
  `contact_person` VARCHAR(120) NULL,     -- Responsável comercial
  `email` VARCHAR(150) NULL,
  `phone` VARCHAR(30) NULL,
  `address` VARCHAR(255) NULL,
  `city` VARCHAR(100) NULL,
  `state` VARCHAR(2) NULL,
  `zip_code` VARCHAR(15) NULL,
  `bank_info` TEXT NULL,                  -- Banco, Agência, Conta, Chave Pix
  `notes` TEXT NULL,                      -- Observações cadastrais
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
  `contract_number` VARCHAR(50) NOT NULL UNIQUE, -- Ex: CT-2026/042
  `title` VARCHAR(200) NOT NULL,
  `type` ENUM('servico', 'material', 'misto') NOT NULL DEFAULT 'servico',
  `total_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `used_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `renewal_clause` VARCHAR(255) NULL,
  `file_path` VARCHAR(255) NULL,                 -- Arquivo do contrato digitalizado
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
  `code` VARCHAR(30) NOT NULL UNIQUE,             -- Ex: REQ-2026-0001
  `user_id` INT UNSIGNED NOT NULL,                -- Usuário Solicitante
  `supplier_id` INT UNSIGNED NOT NULL,            -- Fornecedor / Para quem solicita
  `contract_id` INT UNSIGNED NULL,                -- Vínculo opcional a Contrato
  `type` ENUM('material', 'servico') NOT NULL,    -- Compra de Materiais ou Prestação de Serviços
  `title` VARCHAR(200) NOT NULL,                  -- Resumo da solicitação
  `department` VARCHAR(100) NOT NULL,             -- Setor / Centro de Custo
  `priority` ENUM('baixa', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
  `justification` TEXT NOT NULL,                  -- Justificativa / Necessidade
  `deadline` DATE NULL,                           -- Prazo desejado de entrega/início
  `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('rascunho', 'pendente', 'em_analise', 'aprovada', 'rejeitada', 'em_execucao', 'concluida', 'cancelada') NOT NULL DEFAULT 'pendente',
  `rejection_reason` TEXT NULL,
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
  `unit` VARCHAR(20) NOT NULL DEFAULT 'un',       -- un, cx, pct, kg, m, l, hr, dia, etc.
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `technical_specs` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_items_requisition` FOREIGN KEY (`requisition_id`) 
    REFERENCES `requisitions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. TABELA DE AUDITORIA / HISTÓRICO DE AÇÕES
-- --------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `requisition_id` INT UNSIGNED NULL,
  `contract_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(50) NOT NULL,                  -- 'Criação', 'Aprovação', 'Rejeição', 'Alteração de Status'
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
-- CARGA DE DADOS INICIAIS (SEED)
-- Senha padrão para todos os usuários: admin123 (bcrypt)
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `department`, `phone`, `active`) VALUES
(1, 'Administrador do Sistema', 'admin@empresa.com', '$2y$10$w8.d2N0a4dZ7X1rC8uG1r.Yx5nB3bL8zJ9iO7lK6mV4tP2qR1sN.m', 'admin', 'Diretoria / TI', '(11) 98888-1001', 1),
(2, 'Carlos Eduardo (Gestor/Aprovador)', 'carlos.aprovador@empresa.com', '$2y$10$w8.d2N0a4dZ7X1rC8uG1r.Yx5nB3bL8zJ9iO7lK6mV4tP2qR1sN.m', 'aprovador', 'Suprimentos & Compras', '(11) 98888-1002', 1),
(3, 'Mariana Silva (Solicitante)', 'mariana.solicitante@empresa.com', '$2y$10$w8.d2N0a4dZ7X1rC8uG1r.Yx5nB3bL8zJ9iO7lK6mV4tP2qR1sN.m', 'solicitante', 'Operações & Logística', '(11) 98888-1003', 1);

-- Fornecedores / Prestadores
INSERT INTO `suppliers` (`id`, `type`, `document`, `name`, `trade_name`, `category`, `contact_person`, `email`, `phone`, `address`, `city`, `state`, `zip_code`, `bank_info`, `notes`, `status`) VALUES
(1, 'pj', '12.345.678/0001-90', 'TechCloud Soluções em Informática Ltda', 'TechCloud Soluções', 'Tecnologia & TI', 'Roberto Souza', 'comercial@techcloud.com.br', '(11) 3210-4000', 'Av. Paulista, 1500, cj 42', 'São Paulo', 'SP', '01310-200', 'Banco Itaú (341) Ag: 0230 CC: 45678-9 | Pix: 12345678000190', 'Fornecedor homologado para licenças e suporte de TI.', 'ativo'),
(2, 'pj', '98.765.432/0001-10', 'Construtora & Engenharia Delta S/A', 'Delta Engenharia', 'Obras e Reformas', 'Engª Juliana Prado', 'contato@deltaeng.com.br', '(11) 3450-8000', 'Rua das Indústrias, 800', 'Campinas', 'SP', '13080-000', 'Banco Bradesco (237) Ag: 1200 CC: 98765-4 | Pix: juliana@deltaeng.com.br', 'Prestador para manutenção predial e infraestrutura civil.', 'ativo'),
(3, 'pj', '45.890.123/0001-55', 'Papelaria & Distribuidora Alfa Ltda', 'Alfa Suprimentos', 'Materiais de Escritório', 'Lucas Mendes', 'vendas@alfasuprimentos.com.br', '(11) 2200-1122', 'Rua Santa Efigênia, 340', 'São Paulo', 'SP', '01207-000', 'Banco do Brasil (001) Ag: 3344 CC: 11223-4 | Pix: financeiro@alfasuprimentos.com.br', 'Contrato com tabela fixa para compras recorrentes.', 'ativo'),
(4, 'pf', '234.567.890-12', 'Marcos Aurélio Consultoria Tributária', 'Marcos Aurélio Consultor', 'Consultoria e Auditoria', 'Marcos Aurélio', 'marcos.tributario@email.com', '(11) 99123-4567', 'Alameda Santos, 980', 'São Paulo', 'SP', '01418-100', 'Santander (033) Ag: 4500 CC: 33445-5 | Pix: 23456789012', 'Prestação de serviços pontuais de revisão fiscal.', 'ativo');

-- Contratos
INSERT INTO `contracts` (`id`, `supplier_id`, `contract_number`, `title`, `type`, `total_value`, `used_value`, `start_date`, `end_date`, `renewal_clause`, `notes`, `status`) VALUES
(1, 1, 'CT-2026/001', 'Suporte Técnico e Gestão de Servidores Cloud', 'servico', 120000.00, 15000.00, '2026-01-01', '2026-12-31', 'Renovação automática por iguais períodos de 12 meses', 'Contrato anual de infraestrutura com SLA de 4 horas.', 'ativo'),
(2, 2, 'CT-2026/002', 'Reforma Estrutural do Galpão B e Fachada', 'servico', 85000.00, 32000.00, '2026-02-01', '2026-08-31', 'Sem renovação automática - medições mensais', 'Empreitada global com medições conforme cronograma físico-financeiro.', 'ativo'),
(3, 3, 'CT-2026/003', 'Fornecimento Contínuo de Papelaria e Escritório', 'material', 40000.00, 4850.00, '2026-01-15', '2027-01-14', 'Renovável mediante termo aditivo de repactuação de preços', 'Tabela de preços congelada durante a vigência.', 'ativo');

-- Requisições
INSERT INTO `requisitions` (`id`, `code`, `user_id`, `supplier_id`, `contract_id`, `type`, `title`, `department`, `priority`, `justification`, `deadline`, `total_amount`, `status`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 'REQ-2026-0001', 3, 1, 1, 'servico', 'Implantação de Firewall e Configuração de VPN Corporativa', 'TI / Infraestrutura', 'alta', 'Necessidade urgente de adequação de segurança para colaboradores em trabalho remoto.', '2026-03-15', 15000.00, 'aprovada', 2, '2026-02-12 14:30:00', '2026-02-10 09:15:00'),
(2, 'REQ-2026-0002', 3, 3, 3, 'material', 'Lote Semestral de Papel A4, Toners e Materiais de Almoxarifado', 'Administrativo', 'media', 'Reposição periódica dos estoques dos setores de contabilidade e logística.', '2026-03-20', 4850.00, 'em_analise', NULL, NULL, '2026-02-14 11:20:00'),
(3, 'REQ-2026-0003', 3, 2, 2, 'servico', 'Manutenção Preventiva do Telhado e Calhas - Galpão B', 'Operações & Logística', 'urgente', 'Infiltração identificada após chuvas torrenciais, risco a maquinários.', '2026-02-28', 12000.00, 'pendente', NULL, NULL, '2026-02-18 16:45:00');

-- Itens da Requisição 1
INSERT INTO `requisition_items` (`requisition_id`, `description`, `unit`, `quantity`, `unit_price`, `total_price`, `technical_specs`) VALUES
(1, 'Horas de Especialista em Segurança de Redes', 'hr', 50.00, 200.00, 10000.00, 'Configuração de túneis IPSec e políticas de acesso restrito'),
(1, 'Setup e Licenciamento de Appliance Virtual Firewall', 'un', 1.00, 5000.00, 5000.00, 'Validade 12 meses inclusa');

-- Itens da Requisição 2
INSERT INTO `requisition_items` (`requisition_id`, `description`, `unit`, `quantity`, `unit_price`, `total_price`, `technical_specs`) VALUES
(2, 'Papel Sulfite A4 75g Caixa com 10 Resmas', 'cx', 20.00, 145.00, 2900.00, 'Marca Chamex ou Report'),
(2, 'Cartucho de Toner HP LaserJet Preto 85A', 'un', 5.00, 190.00, 950.00, 'Original HP'),
(2, 'Kit de Canetas Esferográficas e Pastas Suspensas', 'pct', 10.00, 100.00, 1000.00, 'Diversos cores');

-- Itens da Requisição 3
INSERT INTO `requisition_items` (`requisition_id`, `description`, `unit`, `quantity`, `unit_price`, `total_price`, `technical_specs`) VALUES
(3, 'Serviço de impermeabilização e troca de telhas de fibrocimento', 'dia', 4.00, 3000.00, 12000.00, 'Equipe de 3 profissionais com NR-35 (Trabalho em Altura)');

-- Logs de Auditoria
INSERT INTO `activity_logs` (`requisition_id`, `contract_id`, `user_id`, `action`, `description`) VALUES
(1, 1, 3, 'Criação', 'Requisição REQ-2026-0001 criada e submetida para aprovação.'),
(1, 1, 2, 'Aprovação', 'Requisição aprovada pelo Gestor de Suprimentos Carlos Eduardo.'),
(2, 3, 3, 'Criação', 'Requisição REQ-2026-0002 cadastrada vinculada ao contrato de papelaria.'),
(3, 2, 3, 'Criação', 'Requisição REQ-2026-0003 criada com prioridade urgente.');
