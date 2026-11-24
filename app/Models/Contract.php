<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Contract extends Model
{
    public function all(?int $supplierId = null, ?string $status = null, ?string $search = null): array
    {
        $sql = "
            SELECT c.*, 
                   s.name as supplier_name,
                   s.trade_name as supplier_trade_name,
                   s.document as supplier_document,
                   (c.total_value - c.used_value) as balance_value,
                   CASE 
                       WHEN c.total_value > 0 THEN ROUND((c.used_value / c.total_value) * 100, 1)
                       ELSE 0 
                   END as usage_percentage
            FROM contracts c
            JOIN suppliers s ON s.id = c.supplier_id
            WHERE 1=1
        ";
        $params = [];

        if ($supplierId) {
            $sql .= " AND c.supplier_id = :supplier_id";
            $params['supplier_id'] = $supplierId;
        }

        if ($status) {
            $sql .= " AND c.status = :status";
            $params['status'] = $status;
        }

        if ($search) {
            $sql .= " AND (c.contract_number LIKE :search OR c.title LIKE :search OR s.name LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        $sql .= " ORDER BY c.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   s.name as supplier_name,
                   s.trade_name as supplier_trade_name,
                   s.document as supplier_document,
                   s.phone as supplier_phone,
                   s.email as supplier_email,
                   (c.total_value - c.used_value) as balance_value,
                   CASE 
                       WHEN c.total_value > 0 THEN ROUND((c.used_value / c.total_value) * 100, 1)
                       ELSE 0 
                   END as usage_percentage
            FROM contracts c
            JOIN suppliers s ON s.id = c.supplier_id
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySupplier(int $supplierId, bool $onlyActive = true): array
    {
        $sql = "
            SELECT c.id, c.supplier_id, c.contract_number, c.title, c.type, c.total_value, c.used_value, 
                   (c.total_value - c.used_value) as balance_value,
                   c.start_date, c.end_date, c.status
            FROM contracts c
            WHERE c.supplier_id = :supplier_id
        ";
        if ($onlyActive) {
            $sql .= " AND c.status = 'ativo' AND c.end_date >= CURDATE()";
        }
        $sql .= " ORDER BY c.contract_number ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['supplier_id' => $supplierId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO contracts (
                supplier_id, contract_number, title, type, total_value, used_value,
                start_date, end_date, renewal_clause, notes, status
            ) VALUES (
                :supplier_id, :contract_number, :title, :type, :total_value, 0.00,
                :start_date, :end_date, :renewal_clause, :notes, :status
            )
        ");
        $stmt->execute([
            'supplier_id'     => (int)$data['supplier_id'],
            'contract_number' => $data['contract_number'],
            'title'           => $data['title'],
            'type'            => $data['type'] ?? 'servico',
            'total_value'     => (float)($data['total_value'] ?? 0),
            'start_date'      => $data['start_date'],
            'end_date'        => $data['end_date'],
            'renewal_clause'  => $data['renewal_clause'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'status'          => $data['status'] ?? 'ativo',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE contracts SET
                supplier_id = :supplier_id,
                contract_number = :contract_number,
                title = :title,
                type = :type,
                total_value = :total_value,
                start_date = :start_date,
                end_date = :end_date,
                renewal_clause = :renewal_clause,
                notes = :notes,
                status = :status
            WHERE id = :id
        ");
        return $stmt->execute([
            'id'              => $id,
            'supplier_id'     => (int)$data['supplier_id'],
            'contract_number' => $data['contract_number'],
            'title'           => $data['title'],
            'type'            => $data['type'] ?? 'servico',
            'total_value'     => (float)($data['total_value'] ?? 0),
            'start_date'      => $data['start_date'],
            'end_date'        => $data['end_date'],
            'renewal_clause'  => $data['renewal_clause'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'status'          => $data['status'] ?? 'ativo',
        ]);
    }

    public function recalculateUsedValue(int $contractId): void
    {
        // Soma todas as requisições ativas vinculadas (aprovada, em_execucao, concluida)
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM requisitions
            WHERE contract_id = :contract_id
              AND status IN ('aprovada', 'em_execucao', 'concluida')
        ");
        $stmt->execute(['contract_id' => $contractId]);
        $total = (float)$stmt->fetchColumn();

        $update = $this->db->prepare("UPDATE contracts SET used_value = :used WHERE id = :id");
        $update->execute(['used' => $total, 'id' => $contractId]);
    }

    public function getExpiringSoon(int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, s.name as supplier_name, DATEDIFF(c.end_date, CURDATE()) as days_left
            FROM contracts c
            JOIN suppliers s ON s.id = c.supplier_id
            WHERE c.status = 'ativo' 
              AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
            ORDER BY c.end_date ASC
        ");
        $stmt->execute(['days' => $days]);
        return $stmt->fetchAll();
    }

    public function delete(int $id): bool
    {
        $contract = $this->findById($id);
        if ($contract && !empty($contract['file_path'])) {
            $abs = __DIR__ . '/../../public/' . ltrim((string)$contract['file_path'], '/');
            if (file_exists($abs)) {
                @unlink($abs);
            }
        }
        $stmt = $this->db->prepare("DELETE FROM contracts WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
