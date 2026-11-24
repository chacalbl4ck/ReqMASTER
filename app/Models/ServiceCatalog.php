<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class ServiceCatalog extends Model
{
    public function all(bool $onlyActive = false, string $search = ''): array
    {
        $sql = "SELECT * FROM services WHERE 1=1";
        $params = [];

        if ($onlyActive) {
            $sql .= " AND active = 1";
        }
        if ($search !== '') {
            $sql .= " AND (name LIKE :s1 OR notes LIKE :s2)";
            $term = '%' . $search . '%';
            $params['s1'] = $term;
            $params['s2'] = $term;
        }

        $sql .= " ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM services WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO services (name, notes, active)
            VALUES (:name, :notes, :active)
        ");
        $stmt->execute([
            'name'   => trim($data['name']),
            'notes'  => !empty($data['notes']) ? trim($data['notes']) : null,
            'active' => isset($data['active']) ? (int)$data['active'] : 1,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE services
            SET name = :name, notes = :notes, active = :active
            WHERE id = :id
        ");
        return $stmt->execute([
            'name'   => trim($data['name']),
            'notes'  => !empty($data['notes']) ? trim($data['notes']) : null,
            'active' => isset($data['active']) ? (int)$data['active'] : 1,
            'id'     => $id,
        ]);
    }

    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE services SET active = IF(active = 1, 0, 1) WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM services WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
