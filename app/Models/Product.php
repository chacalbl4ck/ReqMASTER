<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Product extends Model
{
    public function all(bool $onlyActive = false, string $search = ''): array
    {
        $sql = "
            SELECT p.*,
                   (SELECT filename FROM product_photos pp WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) as thumb_photo,
                   (SELECT COUNT(*) FROM product_photos pp WHERE pp.product_id = p.id) as photo_count,
                   CASE
                       WHEN p.maintenance_interval_days IS NOT NULL AND p.maintenance_interval_days > 0 AND p.last_maintenance_at IS NOT NULL
                       THEN DATEDIFF(CURDATE(), p.last_maintenance_at)
                       ELSE NULL
                   END as days_since_maintenance,
                   CASE
                       WHEN p.active = 1
                            AND p.maintenance_interval_days IS NOT NULL
                            AND p.maintenance_interval_days > 0
                            AND p.last_maintenance_at IS NOT NULL
                            AND DATEDIFF(CURDATE(), p.last_maintenance_at) > p.maintenance_interval_days
                       THEN 1
                       ELSE 0
                   END as is_maintenance_overdue
            FROM products p
            WHERE 1=1
        ";
        $params = [];

        if ($onlyActive) {
            $sql .= " AND p.active = 1";
        }

        if ($search !== '') {
            $sql .= " AND (p.name LIKE :s1 OR p.serial_number LIKE :s2 OR p.notes LIKE :s3)";
            $term = '%' . $search . '%';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
        }

        $sql .= " ORDER BY p.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['photos'] = $this->getPhotos($id);
        $row['maintenance_history'] = $this->getMaintenanceHistory($id);
        return $row;
    }

    public function getPhotos(int $productId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM product_photos WHERE product_id = :id ORDER BY id ASC");
        $stmt->execute(['id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMaintenanceHistory(int $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.id, r.code, r.title, r.maintenance_type, r.status, r.created_at, r.completed_at, r.total_amount,
                   s.name as supplier_name, u.name as requester_name
            FROM requisitions r
            LEFT JOIN suppliers s ON s.id = r.supplier_id
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.linked_product_id = :id
            ORDER BY r.created_at DESC
        ");
        $stmt->execute(['id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data, array $photos = []): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO products (name, serial_number, notes, maintenance_interval_days, last_maintenance_at, active)
            VALUES (:name, :serial, :notes, :interval_days, :last_maint, :active)
        ");
        $interval = !empty($data['maintenance_interval_days']) ? max(1, (int)$data['maintenance_interval_days']) : null;
        $lastMaint = !empty($data['last_maintenance_at']) ? $data['last_maintenance_at'] : null;

        $stmt->execute([
            'name'          => trim($data['name']),
            'serial'        => !empty($data['serial_number']) ? trim($data['serial_number']) : null,
            'notes'         => !empty($data['notes']) ? trim($data['notes']) : null,
            'interval_days' => $interval,
            'last_maint'    => $lastMaint,
            'active'        => isset($data['active']) ? (int)$data['active'] : 1,
        ]);

        $productId = (int)$this->db->lastInsertId();
        $this->addPhotos($productId, $photos);
        return $productId;
    }

    public function update(int $id, array $data, array $newPhotos = []): bool
    {
        $stmt = $this->db->prepare("
            UPDATE products
            SET name = :name,
                serial_number = :serial,
                notes = :notes,
                maintenance_interval_days = :interval_days,
                last_maintenance_at = :last_maint,
                active = :active
            WHERE id = :id
        ");
        $interval = !empty($data['maintenance_interval_days']) ? max(1, (int)$data['maintenance_interval_days']) : null;
        $lastMaint = !empty($data['last_maintenance_at']) ? $data['last_maintenance_at'] : null;

        $ok = $stmt->execute([
            'name'          => trim($data['name']),
            'serial'        => !empty($data['serial_number']) ? trim($data['serial_number']) : null,
            'notes'         => !empty($data['notes']) ? trim($data['notes']) : null,
            'interval_days' => $interval,
            'last_maint'    => $lastMaint,
            'active'        => isset($data['active']) ? (int)$data['active'] : 1,
            'id'            => $id,
        ]);

        if (!empty($newPhotos)) {
            $this->addPhotos($id, $newPhotos);
        }

        return $ok;
    }

    public function addPhotos(int $productId, array $photos): void
    {
        if (empty($photos)) return;
        $stmt = $this->db->prepare("
            INSERT INTO product_photos (product_id, filename, original_name)
            VALUES (:pid, :fname, :orig)
        ");
        foreach ($photos as $p) {
            $stmt->execute([
                'pid'   => $productId,
                'fname' => $p['filename'],
                'orig'  => $p['original_name'],
            ]);
        }
    }

    public function deletePhoto(int $photoId): ?int
    {
        $stmt = $this->db->prepare("SELECT product_id, filename FROM product_photos WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $photoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $abs = __DIR__ . '/../../public/' . ltrim((string)$row['filename'], '/');
        if (file_exists($abs)) {
            @unlink($abs);
        }

        $del = $this->db->prepare("DELETE FROM product_photos WHERE id = :id");
        $del->execute(['id' => $photoId]);
        return (int)$row['product_id'];
    }

    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE products SET active = IF(active = 1, 0, 1) WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function delete(int $id): bool
    {
        foreach ($this->getPhotos($id) as $p) {
            $abs = __DIR__ . '/../../public/' . ltrim((string)$p['filename'], '/');
            if (file_exists($abs)) {
                @unlink($abs);
            }
        }
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function recordMaintenanceCompletion(int $productId, string $dateYmd): bool
    {
        $stmt = $this->db->prepare("UPDATE products SET last_maintenance_at = :dt WHERE id = :id");
        return $stmt->execute(['dt' => $dateYmd, 'id' => $productId]);
    }

    public function getOverdueMaintenance(): array
    {
        $sql = "
            SELECT p.*,
                   DATEDIFF(CURDATE(), p.last_maintenance_at) as days_elapsed,
                   (DATEDIFF(CURDATE(), p.last_maintenance_at) - p.maintenance_interval_days) as days_overdue,
                   DATE_ADD(p.last_maintenance_at, INTERVAL p.maintenance_interval_days DAY) as due_date
            FROM products p
            WHERE p.active = 1
              AND p.maintenance_interval_days IS NOT NULL
              AND p.maintenance_interval_days > 0
              AND p.last_maintenance_at IS NOT NULL
              AND DATEDIFF(CURDATE(), p.last_maintenance_at) > p.maintenance_interval_days
            ORDER BY days_overdue DESC, p.name ASC
        ";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMaintenanceReport(array $filters = []): array
    {
        $sql = "
            SELECT r.id as requisition_id,
                   r.code,
                   r.title,
                   r.maintenance_type,
                   r.status,
                   r.created_at,
                   r.completed_at,
                   r.total_amount,
                   r.has_values,
                   p.id as product_id,
                   p.name as product_name,
                   p.serial_number,
                   p.maintenance_interval_days,
                   p.last_maintenance_at,
                   s.name as supplier_name,
                   u.name as requester_name
            FROM requisitions r
            JOIN products p ON p.id = r.linked_product_id
            LEFT JOIN suppliers s ON s.id = r.supplier_id
            LEFT JOIN users u ON u.id = r.user_id
            WHERE (r.is_equipment_repair = 1 OR r.linked_product_id IS NOT NULL)
        ";
        $params = [];

        if (!empty($filters['product_id'])) {
            $sql .= " AND r.linked_product_id = :pid";
            $params['pid'] = (int)$filters['product_id'];
        }
        if (!empty($filters['maintenance_type'])) {
            $sql .= " AND r.maintenance_type = :mtype";
            $params['mtype'] = $filters['maintenance_type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(r.created_at) >= :dfrom";
            $params['dfrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(r.created_at) <= :dto";
            $params['dto'] = $filters['date_to'];
        }

        $sql .= " ORDER BY COALESCE(r.completed_at, r.created_at) DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
