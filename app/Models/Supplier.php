<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Supplier extends Model
{
    public function all(?string $status = null, ?string $search = null): array
    {
        $sql = "
            SELECT s.*, 
                   COUNT(DISTINCT c.id) as total_contracts,
                   COUNT(DISTINCT r.id) as total_requisitions,
                   COALESCE(SUM(c.total_value), 0) as contracts_total_amount
            FROM suppliers s
            LEFT JOIN contracts c ON c.supplier_id = s.id
            LEFT JOIN requisitions r ON r.supplier_id = s.id
            WHERE 1=1
        ";
        $params = [];

        if ($status) {
            $sql .= " AND s.status = :status";
            $params['status'] = $status;
        }

        if ($search) {
            $sql .= " AND (s.name LIKE :search OR s.trade_name LIKE :search OR s.document LIKE :search OR s.category LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        $sql .= " GROUP BY s.id ORDER BY s.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getActiveList(): array
    {
        $stmt = $this->db->query("
            SELECT id, name, trade_name, document, category 
            FROM suppliers 
            WHERE status = 'ativo' 
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO suppliers (
                type, document, name, trade_name, category, contact_person,
                email, phone, address, city, state, zip_code, bank_info, notes, status
            ) VALUES (
                :type, :document, :name, :trade_name, :category, :contact_person,
                :email, :phone, :address, :city, :state, :zip_code, :bank_info, :notes, :status
            )
        ");
        $stmt->execute([
            'type'           => $data['type'] ?? 'pj',
            'document'       => preg_replace('/[^\d]/', '', $data['document']),
            'name'           => $data['name'],
            'trade_name'     => $data['trade_name'] ?? null,
            'category'       => $data['category'],
            'contact_person' => $data['contact_person'] ?? null,
            'email'          => $data['email'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'address'        => $data['address'] ?? null,
            'city'           => $data['city'] ?? null,
            'state'          => $data['state'] ?? null,
            'zip_code'       => $data['zip_code'] ?? null,
            'bank_info'      => $data['bank_info'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'status'         => $data['status'] ?? 'ativo',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE suppliers SET
                type = :type,
                document = :document,
                name = :name,
                trade_name = :trade_name,
                category = :category,
                contact_person = :contact_person,
                email = :email,
                phone = :phone,
                address = :address,
                city = :city,
                state = :state,
                zip_code = :zip_code,
                bank_info = :bank_info,
                notes = :notes,
                status = :status
            WHERE id = :id
        ");
        return $stmt->execute([
            'id'             => $id,
            'type'           => $data['type'] ?? 'pj',
            'document'       => preg_replace('/[^\d]/', '', $data['document']),
            'name'           => $data['name'],
            'trade_name'     => $data['trade_name'] ?? null,
            'category'       => $data['category'],
            'contact_person' => $data['contact_person'] ?? null,
            'email'          => $data['email'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'address'        => $data['address'] ?? null,
            'city'           => $data['city'] ?? null,
            'state'          => $data['state'] ?? null,
            'zip_code'       => $data['zip_code'] ?? null,
            'bank_info'      => $data['bank_info'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'status'         => $data['status'] ?? 'ativo',
        ]);
    }

    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE suppliers
            SET status = IF(status = 'ativo', 'inativo', 'ativo')
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM suppliers WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
