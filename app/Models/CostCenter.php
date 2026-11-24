<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class CostCenter extends Model
{
    public function all(bool $onlyActive = false): array
    {
        $sql = "SELECT * FROM cost_centers";
        if ($onlyActive) {
            $sql .= " WHERE active = 1";
        }
        $sql .= " ORDER BY code ASC, name ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM cost_centers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM cost_centers WHERE code = :code LIMIT 1");
        $stmt->execute(['code' => strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO cost_centers (code, name, description, active)
            VALUES (:code, :name, :description, :active)
        ");
        $stmt->execute([
            'code'        => strtoupper(trim($data['code'])),
            'name'        => trim($data['name']),
            'description' => trim($data['description'] ?? ''),
            'active'      => isset($data['active']) ? (int)$data['active'] : 1,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE cost_centers
            SET code = :code, name = :name, description = :description, active = :active
            WHERE id = :id
        ");
        return $stmt->execute([
            'code'        => strtoupper(trim($data['code'])),
            'name'        => trim($data['name']),
            'description' => trim($data['description'] ?? ''),
            'active'      => isset($data['active']) ? (int)$data['active'] : 1,
            'id'          => $id,
        ]);
    }

    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE cost_centers SET active = IF(active = 1, 0, 1) WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM cost_centers WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
