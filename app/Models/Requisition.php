<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use Exception;

class Requisition extends Model
{
    public function generateCode(): string
    {
        $year = date('Y');
        $prefix = "REQ-{$year}-";

        $stmt = $this->db->prepare("
            SELECT code FROM requisitions 
            WHERE code LIKE :prefix 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['prefix' => "{$prefix}%"]);
        $lastCode = $stmt->fetchColumn();

        if ($lastCode) {
            $num = (int)substr($lastCode, strlen($prefix)) + 1;
        } else {
            $num = 1;
        }

        return $prefix . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }

    public function all(array $filters = []): array
    {
        $sql = "
            SELECT r.*,
                   u.name as requester_name,
                   u.department as requester_dept,
                   s.name as supplier_name,
                   s.trade_name as supplier_trade_name,
                   s.document as supplier_document,
                   c.contract_number,
                   c.title as contract_title,
                   app.name as approver_name
            FROM requisitions r
            JOIN users u ON u.id = r.user_id
            JOIN suppliers s ON s.id = r.supplier_id
            LEFT JOIN contracts c ON c.id = r.contract_id
            LEFT JOIN users app ON app.id = r.approved_by
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND r.type = :type";
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['supplier_id'])) {
            $sql .= " AND r.supplier_id = :supplier_id";
            $params['supplier_id'] = (int)$filters['supplier_id'];
        }

        if (!empty($filters['contract_id'])) {
            $sql .= " AND r.contract_id = :contract_id";
            $params['contract_id'] = (int)$filters['contract_id'];
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND r.priority = :priority";
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (r.code LIKE :search OR r.title LIKE :search OR s.name LIKE :search OR r.department LIKE :search)";
            $params['search'] = "%{$filters['search']}%";
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND r.user_id = :user_id";
            $params['user_id'] = (int)$filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(r.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(r.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY r.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   u.name as requester_name,
                   u.email as requester_email,
                   u.role as requester_role,
                   u.department as requester_dept,
                   u.phone as requester_phone,
                   s.name as supplier_name,
                   s.trade_name as supplier_trade_name,
                   s.document as supplier_document,
                   s.type as supplier_type,
                   s.email as supplier_email,
                   s.phone as supplier_phone,
                   s.bank_info as supplier_bank_info,
                   c.contract_number,
                   c.title as contract_title,
                   c.total_value as contract_total_value,
                   c.used_value as contract_used_value,
                   (c.total_value - c.used_value) as contract_balance,
                   app.name as approver_name
            FROM requisitions r
            JOIN users u ON u.id = r.user_id
            JOIN suppliers s ON s.id = r.supplier_id
            LEFT JOIN contracts c ON c.id = r.contract_id
            LEFT JOIN users app ON app.id = r.approved_by
            WHERE r.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row) {
            $row['items'] = $this->getItems($id);
            $row['attachments'] = $this->getAttachments($id);
        }

        return $row ?: null;
    }

    public function getAttachments(int $requisitionId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM requisition_attachments WHERE requisition_id = :id ORDER BY id ASC");
        $stmt->execute(['id' => $requisitionId]);
        return $stmt->fetchAll();
    }

    public function addAttachment(int $requisitionId, string $filename, string $originalName, string $mimeType, int $fileSize): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO requisition_attachments (requisition_id, filename, original_name, mime_type, file_size)
            VALUES (:req_id, :filename, :orig, :mime, :size)
        ");
        $stmt->execute([
            'req_id'   => $requisitionId,
            'filename' => $filename,
            'orig'     => $originalName,
            'mime'     => $mimeType,
            'size'     => $fileSize
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function deleteAttachment(int $attachmentId): bool
    {
        $stmt = $this->db->prepare("SELECT filename FROM requisition_attachments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $attachmentId]);
        $filename = $stmt->fetchColumn();

        if ($filename) {
            $filePath = __DIR__ . '/../../public/' . $filename;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $del = $this->db->prepare("DELETE FROM requisition_attachments WHERE id = :id");
        return $del->execute(['id' => $attachmentId]);
    }

    public function getItems(int $requisitionId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM requisition_items WHERE requisition_id = :id ORDER BY id ASC");
        $stmt->execute(['id' => $requisitionId]);
        return $stmt->fetchAll();
    }

    public function createWithItems(array $data, array $items, array $attachments = []): int
    {
        $this->db->beginTransaction();

        try {
            $code = $this->generateCode();

            // Calcula total geral somando itens e verifica se tem valores preenchidos
            $totalAmount = 0.00;
            $hasValues = 0;

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 1);
                $unitPrice = (float)($item['unit_price'] ?? 0);
                if ($unitPrice > 0) {
                    $hasValues = 1;
                }
                $totalAmount += ($qty * $unitPrice);
            }

            $viewToken = bin2hex(random_bytes(32));

            $stmt = $this->db->prepare("
                INSERT INTO requisitions (
                    code, view_token, user_id, supplier_id, contract_id, type, title,
                    department, priority, justification, deadline, total_amount, has_values,
                    requester_signature, status
                ) VALUES (
                    :code, :view_token, :user_id, :supplier_id, :contract_id, :type, :title,
                    :department, :priority, :justification, :deadline, :total_amount, :has_values,
                    :requester_sig, :status
                )
            ");

            $contractId = !empty($data['contract_id']) ? (int)$data['contract_id'] : null;

            $stmt->execute([
                'code'          => $code,
                'view_token'    => $viewToken,
                'user_id'       => (int)$data['user_id'],
                'supplier_id'   => (int)$data['supplier_id'],
                'contract_id'   => $contractId,
                'type'          => $data['type'], // 'material' ou 'servico'
                'title'         => $data['title'],
                'department'    => $data['department'],
                'priority'      => $data['priority'] ?? 'media',
                'justification' => $data['justification'],
                'deadline'      => !empty($data['deadline']) ? $data['deadline'] : null,
                'total_amount'  => $totalAmount,
                'has_values'    => $hasValues,
                'requester_sig' => $data['requester_signature'] ?? null,
                'status'        => $data['status'] ?? 'pendente'
            ]);

            $requisitionId = (int)$this->db->lastInsertId();

            // Insere itens
            $stmtItem = $this->db->prepare("
                INSERT INTO requisition_items (
                    requisition_id, description, unit, quantity, unit_price, total_price, technical_specs
                ) VALUES (
                    :req_id, :desc, :unit, :qty, :price, :total, :specs
                )
            ");

            foreach ($items as $item) {
                $desc = trim($item['description'] ?? '');
                if (empty($desc)) continue;

                $qty = (float)($item['quantity'] ?? 1);
                $price = (float)($item['unit_price'] ?? 0);
                $total = $qty * $price;

                $stmtItem->execute([
                    'req_id' => $requisitionId,
                    'desc'   => $desc,
                    'unit'   => $item['unit'] ?? 'un',
                    'qty'    => $qty,
                    'price'  => $price,
                    'total'  => $total,
                    'specs'  => $item['technical_specs'] ?? null
                ]);
            }

            // Insere anexos se houver
            if (!empty($attachments)) {
                $stmtAtt = $this->db->prepare("
                    INSERT INTO requisition_attachments (requisition_id, filename, original_name, mime_type, file_size)
                    VALUES (:req_id, :filename, :orig, :mime, :size)
                ");
                foreach ($attachments as $att) {
                    $stmtAtt->execute([
                        'req_id'   => $requisitionId,
                        'filename' => $att['filename'],
                        'orig'     => $att['original_name'],
                        'mime'     => $att['mime_type'],
                        'size'     => (int)$att['file_size']
                    ]);
                }
            }

            // Registra Log
            $log = new ActivityLog();
            $logMsg = "Requisição {$code} criada" . ($hasValues ? " no valor de " . formatMoney($totalAmount) : " sem valores pré-definidos") . ".";
            $log->log('Criação', $logMsg, $requisitionId, $contractId, (int)$data['user_id']);

            // Registra Assinatura Digital do Solicitante na Trilha de Auditoria
            if (!empty($data['requester_signature'])) {
                $isCert = str_contains($data['requester_signature'], 'CERTIFICADO DIGITAL');
                $sigMethod = $isCert ? 'Certificado Digital ICP-Brasil' : 'Assinatura Digital (Canvas)';
                $log->log('Assinatura Digital - Solicitante', "Requisição assinada digitalmente pelo solicitante ({$sigMethod}) em " . date('d/m/Y \à\s H:i:s') . ".", $requisitionId, $contractId, (int)$data['user_id']);
            }

            $this->db->commit();
            return $requisitionId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $id, string $newStatus, int $userId, ?string $reason = null, ?string $approverSignature = null): bool
    {
        $this->db->beginTransaction();

        try {
            $req = $this->findById($id);
            if (!$req) {
                throw new Exception("Requisição #{$id} não encontrada.");
            }

            $params = [
                'id'     => $id,
                'status' => $newStatus,
            ];

            $sql = "UPDATE requisitions SET status = :status";

            if ($newStatus === 'aprovada') {
                $sql .= ", approved_by = :approved_by, approved_at = NOW(), rejection_reason = NULL";
                $params['approved_by'] = $userId;
                if (!empty($approverSignature)) {
                    $sql .= ", approver_signature = :approver_sig";
                    $params['approver_sig'] = $approverSignature;
                }
            } elseif ($newStatus === 'rejeitada') {
                $sql .= ", rejection_reason = :reason, approved_by = :approved_by, approved_at = NOW()";
                $params['reason'] = $reason ?? 'Não informado';
                $params['approved_by'] = $userId;
            }

            $sql .= " WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Se a requisição está vinculada a um contrato, recalcula o saldo utilizado do contrato
            if (!empty($req['contract_id'])) {
                $contractModel = new Contract();
                $contractModel->recalculateUsedValue((int)$req['contract_id']);
            }

            // Log de atividade
            $log = new ActivityLog();
            $actionName = match ($newStatus) {
                'aprovada' => 'Aprovação',
                'rejeitada' => 'Rejeição',
                'concluida' => 'Conclusão',
                'cancelada' => 'Cancelamento',
                default => 'Alteração de Status'
            };
            $desc = "Status alterado de '{$req['status']}' para '{$newStatus}'." . ($reason ? " Motivo: {$reason}" : "");
            $log->log($actionName, $desc, $id, !empty($req['contract_id']) ? (int)$req['contract_id'] : null, $userId);

            // Registra Assinatura Digital do Aprovador na Trilha de Auditoria
            if ($newStatus === 'aprovada' && !empty($approverSignature)) {
                $isCert = str_contains($approverSignature, 'CERTIFICADO DIGITAL');
                $sigMethod = $isCert ? 'Certificado Digital ICP-Brasil' : 'Assinatura Digital (Canvas)';
                $log->log('Assinatura Digital - Aprovador', "Requisição aprovada e assinada digitalmente pelo gestor/aprovador ({$sigMethod}) em " . date('d/m/Y \à\s H:i:s') . ".", $id, !empty($req['contract_id']) ? (int)$req['contract_id'] : null, $userId);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateByAdmin(int $id, array $data, array $items, int $adminUserId, string $adminUserName): bool
    {
        $this->db->beginTransaction();

        try {
            $oldReq = $this->findById($id);
            if (!$oldReq) {
                throw new Exception("Requisição #{$id} não encontrada.");
            }

            // Calcula novo total
            $totalAmount = 0.00;
            $hasValues = 0;
            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 1);
                $unitPrice = (float)($item['unit_price'] ?? 0);
                if ($unitPrice > 0) {
                    $hasValues = 1;
                }
                $totalAmount += ($qty * $unitPrice);
            }

            // Monta resumo das alterações para auditoria
            $changes = [];
            if ($oldReq['title'] !== $data['title']) {
                $changes[] = "Título alterado de '{$oldReq['title']}' para '{$data['title']}'";
            }
            if ($oldReq['department'] !== $data['department']) {
                $changes[] = "Departamento alterado para '{$data['department']}'";
            }
            if ($oldReq['priority'] !== $data['priority']) {
                $changes[] = "Prioridade alterada de '{$oldReq['priority']}' para '{$data['priority']}'";
            }
            if ((int)$oldReq['supplier_id'] !== (int)$data['supplier_id']) {
                $changes[] = "Fornecedor alterado (ID: {$data['supplier_id']})";
            }
            if ((int)($oldReq['contract_id'] ?? 0) !== (int)($data['contract_id'] ?? 0)) {
                $changes[] = "Vínculo de contrato alterado";
            }
            if ((float)$oldReq['total_amount'] !== (float)$totalAmount) {
                $changes[] = "Valor total alterado de " . formatMoney($oldReq['total_amount']) . " para " . formatMoney($totalAmount);
            }
            $changes[] = count($items) . " itens atualizados";

            $changesSummary = implode('; ', $changes);

            // Atualiza a tabela requisitions
            $contractId = !empty($data['contract_id']) ? (int)$data['contract_id'] : null;
            $stmt = $this->db->prepare("
                UPDATE requisitions SET
                    supplier_id   = :supplier_id,
                    contract_id   = :contract_id,
                    type          = :type,
                    title         = :title,
                    department    = :department,
                    priority      = :priority,
                    justification = :justification,
                    deadline      = :deadline,
                    total_amount  = :total_amount,
                    has_values    = :has_values
                WHERE id = :id
            ");

            $stmt->execute([
                'id'            => $id,
                'supplier_id'   => (int)$data['supplier_id'],
                'contract_id'   => $contractId,
                'type'          => $data['type'],
                'title'         => $data['title'],
                'department'    => $data['department'],
                'priority'      => $data['priority'] ?? 'media',
                'justification' => $data['justification'],
                'deadline'      => !empty($data['deadline']) ? $data['deadline'] : null,
                'total_amount'  => $totalAmount,
                'has_values'    => $hasValues
            ]);

            // Atualiza itens: remove antigos e insere novos
            $stmtDel = $this->db->prepare("DELETE FROM requisition_items WHERE requisition_id = :id");
            $stmtDel->execute(['id' => $id]);

            $stmtItem = $this->db->prepare("
                INSERT INTO requisition_items (
                    requisition_id, description, unit, quantity, unit_price, total_price, technical_specs
                ) VALUES (
                    :req_id, :desc, :unit, :qty, :price, :total, :specs
                )
            ");

            foreach ($items as $item) {
                $desc = trim($item['description'] ?? '');
                if (empty($desc)) continue;

                $qty = (float)($item['quantity'] ?? 1);
                $price = (float)($item['unit_price'] ?? 0);
                $total = $qty * $price;

                $stmtItem->execute([
                    'req_id' => $id,
                    'desc'   => $desc,
                    'unit'   => $item['unit'] ?? 'un',
                    'qty'    => $qty,
                    'price'  => $price,
                    'total'  => $total,
                    'specs'  => $item['technical_specs'] ?? null
                ]);
            }

            // Recalcula saldo de contrato se houver
            $contractModel = new Contract();
            if ($contractId) $contractModel->recalculateUsedValue($contractId);
            if (!empty($oldReq['contract_id']) && (int)$oldReq['contract_id'] !== $contractId) {
                $contractModel->recalculateUsedValue((int)$oldReq['contract_id']);
            }

            // Registra NA TRILHA DE AUDITORIA
            $log = new ActivityLog();
            $log->log(
                'Edição pelo Administrador', 
                "Requisição editada pelo administrador {$adminUserName}. Detalhes: {$changesSummary}.", 
                $id, 
                $contractId, 
                $adminUserId
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getDashboardMetrics(): array
    {
        // Totais por status
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_all,
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as total_pending,
                SUM(CASE WHEN status = 'aprovada' THEN 1 ELSE 0 END) as total_approved,
                SUM(CASE WHEN status = 'em_execucao' THEN 1 ELSE 0 END) as total_in_progress,
                SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as total_completed,
                SUM(CASE WHEN status = 'rejeitada' THEN 1 ELSE 0 END) as total_rejected,
                SUM(CASE WHEN type = 'material' THEN 1 ELSE 0 END) as total_materials,
                SUM(CASE WHEN type = 'servico' THEN 1 ELSE 0 END) as total_services,
                COALESCE(SUM(CASE WHEN status IN ('aprovada', 'em_execucao', 'concluida') THEN total_amount ELSE 0 END), 0) as total_amount_approved,
                COALESCE(SUM(CASE WHEN status = 'pendente' THEN total_amount ELSE 0 END), 0) as total_amount_pending
            FROM requisitions
        ");
        $counts = $stmt->fetch();

        // Total fornecedores e contratos ativos
        $stmtSuppliers = $this->db->query("SELECT COUNT(*) FROM suppliers WHERE status = 'ativo'");
        $totalSuppliers = (int)$stmtSuppliers->fetchColumn();

        $stmtContracts = $this->db->query("SELECT COUNT(*), COALESCE(SUM(total_value), 0), COALESCE(SUM(used_value), 0) FROM contracts WHERE status = 'ativo'");
        $contractsStats = $stmtContracts->fetch(PDO::FETCH_NUM);

        return [
            'counts' => $counts,
            'total_suppliers' => $totalSuppliers,
            'total_contracts' => (int)$contractsStats[0],
            'contracts_total_val' => (float)$contractsStats[1],
            'contracts_used_val'  => (float)$contractsStats[2],
            'contracts_remaining' => (float)$contractsStats[1] - (float)$contractsStats[2],
        ];
    }

    public function delete(int $id): bool
    {
        // 1. Remove arquivos físicos de anexos da requisição
        $attachments = $this->getAttachments($id);
        foreach ($attachments as $att) {
            $filePath = __DIR__ . '/../../public/' . $att['filename'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // 2. Se a requisição tinha contrato vinculado e foi aprovada, estorna o saldo se necessário
        $stmtReq = $this->db->prepare("SELECT contract_id, total_amount, status FROM requisitions WHERE id = :id");
        $stmtReq->execute(['id' => $id]);
        $req = $stmtReq->fetch();

        if ($req && !empty($req['contract_id']) && in_array($req['status'], ['aprovada', 'em_execucao', 'concluida'], true)) {
            $stmtRevert = $this->db->prepare("
                UPDATE contracts 
                SET used_value = GREATEST(0, used_value - :amount) 
                WHERE id = :cid
            ");
            $stmtRevert->execute([
                'amount' => (float)$req['total_amount'],
                'cid'    => (int)$req['contract_id']
            ]);
        }

        // 3. Remove a requisição do banco (itens, anexos e logs caem via CASCADE)
        $stmt = $this->db->prepare("DELETE FROM requisitions WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function ensureViewToken(int $id): string
    {
        $stmt = $this->db->prepare("SELECT view_token FROM requisitions WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $existing = $stmt->fetchColumn();
        if (!empty($existing)) {
            return (string)$existing;
        }
        $token = bin2hex(random_bytes(32));
        $upd = $this->db->prepare("UPDATE requisitions SET view_token = :tok WHERE id = :id");
        $upd->execute(['tok' => $token, 'id' => $id]);
        return $token;
    }

    public function findByViewToken(string $token): ?array
    {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{32,128}$/i', $token)) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT id, view_token FROM requisitions WHERE view_token = :tok LIMIT 1");
        $stmt->execute(['tok' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !hash_equals((string)$row['view_token'], $token)) {
            return null;
        }
        return $this->findById((int)$row['id']);
    }
}
