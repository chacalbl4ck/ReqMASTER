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
                   (SELECT COUNT(*) FROM product_subitems ps WHERE ps.product_id = p.id) as subitem_count,
                   CASE
                       WHEN p.maintenance_interval_days IS NOT NULL AND p.maintenance_interval_days > 0 AND p.last_maintenance_at IS NOT NULL
                       THEN DATEDIFF(CURDATE(), p.last_maintenance_at)
                       ELSE NULL
                   END as days_since_maintenance,
                   CASE
                       WHEN p.is_vehicle = 1 AND p.maintenance_interval_km IS NOT NULL AND p.maintenance_interval_km > 0 AND p.current_km IS NOT NULL
                       THEN GREATEST(0, CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED))
                       ELSE NULL
                   END as km_since_maintenance,
                   CASE
                       WHEN p.active = 1
                            AND p.maintenance_interval_days IS NOT NULL
                            AND p.maintenance_interval_days > 0
                            AND p.last_maintenance_at IS NOT NULL
                            AND DATEDIFF(CURDATE(), p.last_maintenance_at) > p.maintenance_interval_days
                       THEN 1
                       ELSE 0
                   END as is_days_overdue,
                   CASE
                       WHEN p.active = 1
                            AND p.is_vehicle = 1
                            AND p.maintenance_interval_km IS NOT NULL
                            AND p.maintenance_interval_km > 0
                            AND p.current_km IS NOT NULL
                            AND (CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED)) >= p.maintenance_interval_km
                       THEN 1
                       ELSE 0
                   END as is_km_overdue,
                   CASE
                       WHEN p.active = 1 AND (
                           (p.maintenance_interval_days IS NOT NULL AND p.maintenance_interval_days > 0 AND p.last_maintenance_at IS NOT NULL AND DATEDIFF(CURDATE(), p.last_maintenance_at) > p.maintenance_interval_days)
                           OR
                           (p.is_vehicle = 1 AND p.maintenance_interval_km IS NOT NULL AND p.maintenance_interval_km > 0 AND p.current_km IS NOT NULL AND (CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED)) >= p.maintenance_interval_km)
                       )
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
            $sql .= " AND (p.name LIKE :s1 OR p.serial_number LIKE :s2 OR p.notes LIKE :s3 OR p.license_plate LIKE :s4 OR p.chassis LIKE :s5)";
            $term = '%' . $search . '%';
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
            $params['s5'] = $term;
        }

        $sql .= " ORDER BY p.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $subMap = [];
            $subStmt = $this->db->query("SELECT id, product_id, name, notes FROM product_subitems ORDER BY id ASC");
            foreach ($subStmt->fetchAll(PDO::FETCH_ASSOC) as $sub) {
                $subMap[(int)$sub['product_id']][] = $sub;
            }
            foreach ($rows as &$row) {
                $row['subitems'] = $subMap[(int)$row['id']] ?? [];
            }
            unset($row);
        }

        return $rows;
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
        $row['subitems'] = $this->getSubitems($id);
        $row['mileage_logs'] = $this->getMileageLogs($id, 50);
        $row['maintenance_history'] = $this->getMaintenanceHistory($id);
        return $row;
    }

    public function getSubitems(int $productId): array
    {
        $stmt = $this->db->prepare("SELECT id, product_id, name, notes FROM product_subitems WHERE product_id = :id ORDER BY id ASC");
        $stmt->execute(['id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function replaceSubitems(int $productId, array $subitems): void
    {
        $del = $this->db->prepare("DELETE FROM product_subitems WHERE product_id = :id");
        $del->execute(['id' => $productId]);

        if (empty($subitems)) {
            return;
        }

        $ins = $this->db->prepare("INSERT INTO product_subitems (product_id, name, notes) VALUES (:pid, :name, :notes)");
        foreach ($subitems as $sub) {
            $name = trim((string)($sub['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $ins->execute([
                'pid'   => $productId,
                'name'  => mb_substr($name, 0, 200, 'UTF-8'),
                'notes' => !empty($sub['notes']) ? mb_substr(trim((string)$sub['notes']), 0, 255, 'UTF-8') : null,
            ]);
        }
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

    public function create(array $data, array $photos = [], array $subitems = []): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO products (
                name, serial_number, is_vehicle, chassis, license_plate, current_km,
                notes, maintenance_interval_days, maintenance_interval_km, last_maintenance_at, last_maintenance_km, active
            ) VALUES (
                :name, :serial, :is_vehicle, :chassis, :plate, :current_km,
                :notes, :interval_days, :interval_km, :last_maint, :last_maint_km, :active
            )
        ");
        $isVehicle = !empty($data['is_vehicle']) ? 1 : 0;
        $intervalDays = !empty($data['maintenance_interval_days']) ? max(1, (int)$data['maintenance_interval_days']) : null;
        $intervalKm = ($isVehicle && !empty($data['maintenance_interval_km'])) ? max(1, (int)$data['maintenance_interval_km']) : null;
        $currentKm = ($isVehicle && isset($data['current_km']) && $data['current_km'] !== '' && $data['current_km'] !== null)
            ? max(0, (int)$data['current_km'])
            : null;
        $lastMaintKm = ($isVehicle && isset($data['last_maintenance_km']) && $data['last_maintenance_km'] !== '' && $data['last_maintenance_km'] !== null)
            ? max(0, (int)$data['last_maintenance_km'])
            : ($isVehicle && $currentKm !== null ? $currentKm : null);
        $lastMaint = !empty($data['last_maintenance_at']) ? $data['last_maintenance_at'] : null;

        $stmt->execute([
            'name'          => trim($data['name']),
            'serial'        => !empty($data['serial_number']) ? trim($data['serial_number']) : null,
            'is_vehicle'    => $isVehicle,
            'chassis'       => ($isVehicle && !empty($data['chassis'])) ? strtoupper(trim((string)$data['chassis'])) : null,
            'plate'         => ($isVehicle && !empty($data['license_plate'])) ? strtoupper(trim((string)$data['license_plate'])) : null,
            'current_km'    => $currentKm,
            'notes'         => !empty($data['notes']) ? trim($data['notes']) : null,
            'interval_days' => $intervalDays,
            'interval_km'   => $intervalKm,
            'last_maint'    => $lastMaint,
            'last_maint_km' => $lastMaintKm,
            'active'        => isset($data['active']) ? (int)$data['active'] : 1,
        ]);

        $productId = (int)$this->db->lastInsertId();
        $this->addPhotos($productId, $photos);
        if ($isVehicle) {
            $this->replaceSubitems($productId, $subitems);
        }
        return $productId;
    }

    public function update(int $id, array $data, array $newPhotos = [], array $subitems = []): bool
    {
        $stmt = $this->db->prepare("
            UPDATE products
            SET name = :name,
                serial_number = :serial,
                is_vehicle = :is_vehicle,
                chassis = :chassis,
                license_plate = :plate,
                current_km = :current_km,
                notes = :notes,
                maintenance_interval_days = :interval_days,
                maintenance_interval_km = :interval_km,
                last_maintenance_at = :last_maint,
                last_maintenance_km = :last_maint_km,
                active = :active
            WHERE id = :id
        ");
        $isVehicle = !empty($data['is_vehicle']) ? 1 : 0;
        $intervalDays = !empty($data['maintenance_interval_days']) ? max(1, (int)$data['maintenance_interval_days']) : null;
        $intervalKm = ($isVehicle && !empty($data['maintenance_interval_km'])) ? max(1, (int)$data['maintenance_interval_km']) : null;
        $currentKm = ($isVehicle && isset($data['current_km']) && $data['current_km'] !== '' && $data['current_km'] !== null)
            ? max(0, (int)$data['current_km'])
            : null;
        $lastMaintKm = ($isVehicle && isset($data['last_maintenance_km']) && $data['last_maintenance_km'] !== '' && $data['last_maintenance_km'] !== null)
            ? max(0, (int)$data['last_maintenance_km'])
            : null;
        $lastMaint = !empty($data['last_maintenance_at']) ? $data['last_maintenance_at'] : null;

        $ok = $stmt->execute([
            'name'          => trim($data['name']),
            'serial'        => !empty($data['serial_number']) ? trim($data['serial_number']) : null,
            'is_vehicle'    => $isVehicle,
            'chassis'       => ($isVehicle && !empty($data['chassis'])) ? strtoupper(trim((string)$data['chassis'])) : null,
            'plate'         => ($isVehicle && !empty($data['license_plate'])) ? strtoupper(trim((string)$data['license_plate'])) : null,
            'current_km'    => $currentKm,
            'notes'         => !empty($data['notes']) ? trim($data['notes']) : null,
            'interval_days' => $intervalDays,
            'interval_km'   => $intervalKm,
            'last_maint'    => $lastMaint,
            'last_maint_km' => $lastMaintKm,
            'active'        => isset($data['active']) ? (int)$data['active'] : 1,
            'id'            => $id,
        ]);

        if (!empty($newPhotos)) {
            $this->addPhotos($id, $newPhotos);
        }

        $this->replaceSubitems($id, $isVehicle ? $subitems : []);

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
        foreach ($this->getMileageLogs($id, 500) as $ml) {
            if (!empty($ml['odometer_photo'])) {
                $abs = __DIR__ . '/../../public/' . ltrim((string)$ml['odometer_photo'], '/');
                if (file_exists($abs)) {
                    @unlink($abs);
                }
            }
        }
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function recordMaintenanceCompletion(int $productId, string $dateYmd): bool
    {
        $stmt = $this->db->prepare("
            UPDATE products
            SET last_maintenance_at = :dt,
                last_maintenance_km = CASE
                    WHEN is_vehicle = 1 AND current_km IS NOT NULL THEN current_km
                    ELSE last_maintenance_km
                END
            WHERE id = :id
        ");
        return $stmt->execute(['dt' => $dateYmd, 'id' => $productId]);
    }

    public function getVehicles(bool $onlyActive = true): array
    {
        $sql = "
            SELECT p.*,
                   CASE
                       WHEN p.maintenance_interval_km IS NOT NULL AND p.maintenance_interval_km > 0 AND p.current_km IS NOT NULL
                       THEN GREATEST(0, CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED))
                       ELSE NULL
                   END as km_since_maintenance,
                   CASE
                       WHEN p.active = 1
                            AND p.maintenance_interval_km IS NOT NULL
                            AND p.maintenance_interval_km > 0
                            AND p.current_km IS NOT NULL
                            AND (CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED)) >= p.maintenance_interval_km
                       THEN 1
                       ELSE 0
                   END as is_km_overdue
            FROM products p
            WHERE p.is_vehicle = 1
        ";
        if ($onlyActive) {
            $sql .= " AND p.active = 1";
        }
        $sql .= " ORDER BY p.name ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMileageLogs(int $productId = 0, int $limit = 100): array
    {
        $sql = "
            SELECT m.*, p.name as product_name, p.license_plate, p.chassis, u.name as user_name
            FROM vehicle_mileage_logs m
            JOIN products p ON p.id = m.product_id
            LEFT JOIN users u ON u.id = m.user_id
            WHERE 1=1
        ";
        $params = [];
        if ($productId > 0) {
            $sql .= " AND m.product_id = :pid";
            $params['pid'] = $productId;
        }
        $sql .= " ORDER BY m.recorded_at DESC, m.id DESC LIMIT " . max(1, min(500, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recordMileage(int $productId, int $userId, int $mileageKm, ?string $photoPath = null, ?string $notes = null, ?string $recordedAt = null): int
    {
        $dt = !empty($recordedAt) ? $recordedAt : date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            INSERT INTO vehicle_mileage_logs (product_id, user_id, mileage_km, odometer_photo, notes, recorded_at)
            VALUES (:pid, :uid, :km, :photo, :notes, :rec_at)
        ");
        $stmt->execute([
            'pid'    => $productId,
            'uid'    => $userId > 0 ? $userId : null,
            'km'     => max(0, $mileageKm),
            'photo'  => $photoPath ?: null,
            'notes'  => !empty($notes) ? mb_substr(trim($notes), 0, 255, 'UTF-8') : null,
            'rec_at' => $dt,
        ]);
        $logId = (int)$this->db->lastInsertId();

        $upd = $this->db->prepare("UPDATE products SET current_km = :km WHERE id = :pid");
        $upd->execute(['km' => max(0, $mileageKm), 'pid' => $productId]);

        return $logId;
    }

    public function deleteMileageLog(int $logId): ?int
    {
        $stmt = $this->db->prepare("SELECT product_id, odometer_photo FROM vehicle_mileage_logs WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $logId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        if (!empty($row['odometer_photo'])) {
            $abs = __DIR__ . '/../../public/' . ltrim((string)$row['odometer_photo'], '/');
            if (file_exists($abs)) {
                @unlink($abs);
            }
        }

        $del = $this->db->prepare("DELETE FROM vehicle_mileage_logs WHERE id = :id");
        $del->execute(['id' => $logId]);

        $pid = (int)$row['product_id'];
        $latestStmt = $this->db->prepare("SELECT mileage_km FROM vehicle_mileage_logs WHERE product_id = :pid ORDER BY recorded_at DESC, id DESC LIMIT 1");
        $latestStmt->execute(['pid' => $pid]);
        $latestKm = $latestStmt->fetchColumn();
        if ($latestKm !== false) {
            $upd = $this->db->prepare("UPDATE products SET current_km = :km WHERE id = :pid");
            $upd->execute(['km' => (int)$latestKm, 'pid' => $pid]);
        }

        return $pid;
    }

    public function getOverdueMaintenance(): array
    {
        $sql = "
            SELECT p.*,
                   DATEDIFF(CURDATE(), p.last_maintenance_at) as days_elapsed,
                   (DATEDIFF(CURDATE(), p.last_maintenance_at) - p.maintenance_interval_days) as days_overdue,
                   DATE_ADD(p.last_maintenance_at, INTERVAL p.maintenance_interval_days DAY) as due_date,
                   CASE
                       WHEN p.is_vehicle = 1 AND p.maintenance_interval_km IS NOT NULL AND p.maintenance_interval_km > 0 AND p.current_km IS NOT NULL
                       THEN GREATEST(0, CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED))
                       ELSE NULL
                   END as km_elapsed,
                   CASE
                       WHEN p.is_vehicle = 1 AND p.maintenance_interval_km IS NOT NULL AND p.maintenance_interval_km > 0 AND p.current_km IS NOT NULL
                       THEN (CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED)) - p.maintenance_interval_km
                       ELSE NULL
                   END as km_overdue,
                   CASE
                       WHEN p.maintenance_interval_days IS NOT NULL AND p.maintenance_interval_days > 0 AND p.last_maintenance_at IS NOT NULL AND DATEDIFF(CURDATE(), p.last_maintenance_at) > p.maintenance_interval_days
                       THEN 1
                       ELSE 0
                   END as is_days_overdue,
                   CASE
                       WHEN p.is_vehicle = 1 AND p.maintenance_interval_km IS NOT NULL AND p.maintenance_interval_km > 0 AND p.current_km IS NOT NULL AND (CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED)) >= p.maintenance_interval_km
                       THEN 1
                       ELSE 0
                   END as is_km_overdue
            FROM products p
            WHERE p.active = 1
              AND (
                  (
                      p.maintenance_interval_days IS NOT NULL
                      AND p.maintenance_interval_days > 0
                      AND p.last_maintenance_at IS NOT NULL
                      AND DATEDIFF(CURDATE(), p.last_maintenance_at) > p.maintenance_interval_days
                  )
                  OR
                  (
                      p.is_vehicle = 1
                      AND p.maintenance_interval_km IS NOT NULL
                      AND p.maintenance_interval_km > 0
                      AND p.current_km IS NOT NULL
                      AND (CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED)) >= p.maintenance_interval_km
                  )
              )
            ORDER BY is_km_overdue DESC, COALESCE(days_overdue, 0) DESC, p.name ASC
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
