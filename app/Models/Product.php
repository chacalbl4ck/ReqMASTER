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
        $row['subitems'] = $this->getSubitems($id, $row);
        $row['mileage_logs'] = $this->getMileageLogs($id, 50);
        $row['maintenance_history'] = $this->getMaintenanceHistory($id);
        return $row;
    }

    public function evaluateSubitemStatus(array $sub, array $product): array
    {
        $subIntervalKm = !empty($sub['maintenance_interval_km']) ? (int)$sub['maintenance_interval_km'] : null;
        $vehIntervalKm = !empty($product['maintenance_interval_km']) ? (int)$product['maintenance_interval_km'] : null;
        $vehIntervalDays = !empty($product['maintenance_interval_days']) ? (int)$product['maintenance_interval_days'] : null;
        $currentKm = ($product['current_km'] !== null && $product['current_km'] !== '') ? (int)$product['current_km'] : null;

        $baseKm = ($sub['last_maintenance_km'] !== null && $sub['last_maintenance_km'] !== '')
            ? (int)$sub['last_maintenance_km']
            : (($product['last_maintenance_km'] !== null && $product['last_maintenance_km'] !== '') ? (int)$product['last_maintenance_km'] : 0);

        $kmElapsed = $currentKm !== null ? max(0, $currentKm - $baseKm) : null;
        $lastDate = !empty($sub['last_maintenance_at']) ? $sub['last_maintenance_at'] : ($product['last_maintenance_at'] ?? null);
        $daysElapsed = $lastDate ? max(0, (int)((time() - strtotime($lastDate)) / 86400)) : null;

        $ruleSource = 'none';
        $effectiveKm = null;
        $isOverdue = false;
        $overdueLabel = '';

        if ($subIntervalKm !== null && $subIntervalKm > 0) {
            $ruleSource = 'subitem_km';
            $effectiveKm = $subIntervalKm;
            if ($kmElapsed !== null && $kmElapsed >= $subIntervalKm) {
                $isOverdue = true;
                $overdueLabel = number_format($kmElapsed, 0, ',', '.') . ' / ' . number_format($subIntervalKm, 0, ',', '.') . ' km';
            }
        } elseif ($vehIntervalKm !== null && $vehIntervalKm > 0) {
            $ruleSource = 'vehicle_km';
            $effectiveKm = $vehIntervalKm;
            if ($kmElapsed !== null && $kmElapsed >= $vehIntervalKm) {
                $isOverdue = true;
                $overdueLabel = number_format($kmElapsed, 0, ',', '.') . ' / ' . number_format($vehIntervalKm, 0, ',', '.') . ' km';
            }
        } elseif ($vehIntervalDays !== null && $vehIntervalDays > 0) {
            $ruleSource = 'vehicle_days';
            if ($daysElapsed !== null && $daysElapsed > $vehIntervalDays) {
                $isOverdue = true;
                $overdueLabel = "{$daysElapsed} / {$vehIntervalDays} dias";
            }
        }

        $sub['effective_interval_km'] = $effectiveKm;
        $sub['base_km'] = $baseKm;
        $sub['km_elapsed'] = $kmElapsed;
        $sub['days_elapsed'] = $daysElapsed;
        $sub['rule_source'] = $ruleSource;
        $sub['is_overdue'] = $isOverdue;
        $sub['overdue_label'] = $overdueLabel;

        return $sub;
    }

    public function getSubitems(int $productId, ?array $productRow = null): array
    {
        $stmt = $this->db->prepare("SELECT * FROM product_subitems WHERE product_id = :id ORDER BY id ASC");
        $stmt->execute(['id' => $productId]);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($productRow === null) {
            $pStmt = $this->db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
            $pStmt->execute(['id' => $productId]);
            $productRow = $pStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        foreach ($subs as &$s) {
            $s = $this->evaluateSubitemStatus($s, $productRow);
        }
        unset($s);

        return $subs;
    }

    public function getOverdueSubitems(int $productId = 0): array
    {
        $sql = "
            SELECT ps.*,
                   p.name as product_name,
                   p.license_plate,
                   p.current_km,
                   p.maintenance_interval_km as vehicle_interval_km,
                   p.maintenance_interval_days as vehicle_interval_days,
                   p.last_maintenance_km as vehicle_last_km,
                   p.last_maintenance_at as vehicle_last_at
            FROM product_subitems ps
            JOIN products p ON p.id = ps.product_id
            WHERE p.active = 1 AND p.is_vehicle = 1
        ";
        $params = [];
        if ($productId > 0) {
            $sql .= " AND p.id = :pid";
            $params['pid'] = $productId;
        }
        $sql .= " ORDER BY p.name ASC, ps.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $overdue = [];
        foreach ($rows as $r) {
            $prod = [
                'current_km'                => $r['current_km'],
                'maintenance_interval_km'   => $r['vehicle_interval_km'],
                'maintenance_interval_days' => $r['vehicle_interval_days'],
                'last_maintenance_km'       => $r['vehicle_last_km'],
                'last_maintenance_at'       => $r['vehicle_last_at'],
            ];
            $eval = $this->evaluateSubitemStatus($r, $prod);
            if (!empty($eval['is_overdue'])) {
                $overdue[] = $eval;
            }
        }
        return $overdue;
    }

    public function recordSubitemMaintenance(int $subitemId, ?int $atKm = null, ?string $dateYmd = null): ?int
    {
        $stmt = $this->db->prepare("
            SELECT ps.*, p.current_km
            FROM product_subitems ps
            JOIN products p ON p.id = ps.product_id
            WHERE ps.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $subitemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $km = $atKm !== null ? max(0, $atKm) : ($row['current_km'] !== null ? (int)$row['current_km'] : null);
        $dt = $dateYmd ?: date('Y-m-d');

        $upd = $this->db->prepare("
            UPDATE product_subitems
            SET last_maintenance_km = :km,
                last_maintenance_at = :dt
            WHERE id = :id
        ");
        $upd->execute(['km' => $km, 'dt' => $dt, 'id' => $subitemId]);

        return (int)$row['product_id'];
    }

    public function replaceSubitems(int $productId, array $subitems, ?int $fallbackLastKm = null): void
    {
        $del = $this->db->prepare("DELETE FROM product_subitems WHERE product_id = :id");
        $del->execute(['id' => $productId]);

        if (empty($subitems)) {
            return;
        }

        $ins = $this->db->prepare("
            INSERT INTO product_subitems (product_id, name, maintenance_interval_km, last_maintenance_km, last_maintenance_at, notes)
            VALUES (:pid, :name, :int_km, :last_km, :last_at, :notes)
        ");
        foreach ($subitems as $sub) {
            $name = trim((string)($sub['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $intKm = !empty($sub['maintenance_interval_km']) ? max(1, (int)$sub['maintenance_interval_km']) : null;
            $lastKm = (isset($sub['last_maintenance_km']) && $sub['last_maintenance_km'] !== '' && $sub['last_maintenance_km'] !== null)
                ? max(0, (int)$sub['last_maintenance_km'])
                : ($intKm !== null && $fallbackLastKm !== null ? $fallbackLastKm : null);
            $lastAt = !empty($sub['last_maintenance_at']) ? $sub['last_maintenance_at'] : null;

            $ins->execute([
                'pid'     => $productId,
                'name'    => mb_substr($name, 0, 200, 'UTF-8'),
                'int_km'  => $intKm,
                'last_km' => $lastKm,
                'last_at' => $lastAt,
                'notes'   => !empty($sub['notes']) ? mb_substr(trim((string)$sub['notes']), 0, 255, 'UTF-8') : null,
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
            $this->replaceSubitems($productId, $subitems, $lastMaintKm ?? $currentKm);
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

        $this->replaceSubitems($id, $isVehicle ? $subitems : [], $lastMaintKm ?? $currentKm);

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

    public function recordMileage(int $productId, int $userId, int $mileageKm, ?string $photoPath = null, ?string $notes = null, ?string $recordedAt = null, ?float $fuelLiters = null): int
    {
        $dt = !empty($recordedAt) ? $recordedAt : date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            INSERT INTO vehicle_mileage_logs (product_id, user_id, mileage_km, fuel_liters, odometer_photo, notes, recorded_at)
            VALUES (:pid, :uid, :km, :liters, :photo, :notes, :rec_at)
        ");
        $stmt->execute([
            'pid'    => $productId,
            'uid'    => $userId > 0 ? $userId : null,
            'km'     => max(0, $mileageKm),
            'liters' => ($fuelLiters !== null && $fuelLiters > 0) ? round($fuelLiters, 2) : null,
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
                       WHEN p.maintenance_interval_days IS NOT NULL
                            AND p.maintenance_interval_days > 0
                            AND p.last_maintenance_at IS NOT NULL
                            AND DATEDIFF(CURDATE(), p.last_maintenance_at) > p.maintenance_interval_days
                       THEN 1
                       ELSE 0
                   END as is_days_overdue,
                   CASE
                       WHEN p.is_vehicle = 1
                            AND p.maintenance_interval_km IS NOT NULL
                            AND p.maintenance_interval_km > 0
                            AND p.current_km IS NOT NULL
                            AND (CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED)) >= p.maintenance_interval_km
                       THEN 1
                       ELSE 0
                   END as is_km_overdue
            FROM products p
            WHERE p.active = 1
              AND (
                  (p.maintenance_interval_days IS NOT NULL AND p.maintenance_interval_days > 0 AND p.last_maintenance_at IS NOT NULL AND DATEDIFF(CURDATE(), p.last_maintenance_at) > p.maintenance_interval_days)
                  OR
                  (p.is_vehicle = 1 AND p.maintenance_interval_km IS NOT NULL AND p.maintenance_interval_km > 0 AND p.current_km IS NOT NULL AND (CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED)) >= p.maintenance_interval_km)
                  OR
                  (p.is_vehicle = 1 AND EXISTS (SELECT 1 FROM product_subitems ps WHERE ps.product_id = p.id))
              )
            ORDER BY p.name ASC
        ";
        $candidates = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if (empty($candidates)) {
            return [];
        }

        $overdueSubMap = [];
        foreach ($this->getOverdueSubitems() as $sub) {
            $overdueSubMap[(int)$sub['product_id']][] = $sub;
        }

        $result = [];
        foreach ($candidates as $p) {
            $pid = (int)$p['id'];
            $overdueSubs = $overdueSubMap[$pid] ?? [];
            $isDaysLate = !empty($p['is_days_overdue']);
            $isKmLate = !empty($p['is_km_overdue']);

            if ($isDaysLate || $isKmLate || !empty($overdueSubs)) {
                $p['days_elapsed'] = $p['days_since_maintenance'] ?? null;
                $p['days_overdue'] = ($p['days_since_maintenance'] !== null && !empty($p['maintenance_interval_days']))
                    ? ((int)$p['days_since_maintenance'] - (int)$p['maintenance_interval_days'])
                    : null;
                $p['km_elapsed'] = $p['km_since_maintenance'] ?? null;
                $p['km_overdue'] = ($p['km_since_maintenance'] !== null && !empty($p['maintenance_interval_km']))
                    ? ((int)$p['km_since_maintenance'] - (int)$p['maintenance_interval_km'])
                    : null;
                $p['overdue_subitems'] = $overdueSubs;
                $result[] = $p;
            }
        }

        return $result;
    }

    public function getAllSubitemNames(): array
    {
        $stmt = $this->db->query("SELECT DISTINCT name FROM product_subitems ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getProductMaintenanceStatusList(array $filters = []): array
    {
        $products = $this->all(true);
        $overdueSubMap = [];
        foreach ($this->getOverdueSubitems() as $sub) {
            $overdueSubMap[(int)$sub['product_id']][] = $sub;
        }

        $filterPid = !empty($filters['product_id']) ? (int)$filters['product_id'] : 0;
        $filterSub = mb_strtolower(trim((string)($filters['subitem_name'] ?? '')), 'UTF-8');
        $filterOverdue = (string)($filters['overdue_status'] ?? '');

        $rows = [];
        foreach ($products as $p) {
            if ($filterPid > 0 && (int)$p['id'] !== $filterPid) {
                continue;
            }

            $enrichedSubs = [];
            foreach ($p['subitems'] ?? [] as $s) {
                $enrichedSubs[] = $this->evaluateSubitemStatus($s, $p);
            }

            if ($filterSub !== '') {
                $matchedSub = false;
                foreach ($enrichedSubs as $es) {
                    if (str_contains(mb_strtolower($es['name'], 'UTF-8'), $filterSub)) {
                        $matchedSub = true;
                        break;
                    }
                }
                if (!$matchedSub) {
                    continue;
                }
            }

            $hasOverdueSub = !empty($overdueSubMap[(int)$p['id']]);
            $isOverdue = !empty($p['is_maintenance_overdue']) || $hasOverdueSub;

            if ($filterOverdue === 'overdue' && !$isOverdue) {
                continue;
            }
            if ($filterOverdue === 'ok' && $isOverdue) {
                continue;
            }

            $p['evaluated_subitems'] = $enrichedSubs;
            $p['overdue_subitems'] = $overdueSubMap[(int)$p['id']] ?? [];
            $p['effective_overdue'] = $isOverdue;
            $rows[] = $p;
        }

        return $rows;
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
                   p.is_vehicle,
                   p.license_plate,
                   p.current_km,
                   p.maintenance_interval_days,
                   p.maintenance_interval_km,
                   p.last_maintenance_at,
                   p.last_maintenance_km,
                   s.name as supplier_name,
                   u.name as requester_name,
                   CASE
                       WHEN p.active = 1 AND (
                           (p.maintenance_interval_days IS NOT NULL AND p.maintenance_interval_days > 0 AND p.last_maintenance_at IS NOT NULL AND DATEDIFF(CURDATE(), p.last_maintenance_at) > p.maintenance_interval_days)
                           OR
                           (p.is_vehicle = 1 AND p.maintenance_interval_km IS NOT NULL AND p.maintenance_interval_km > 0 AND p.current_km IS NOT NULL AND (CAST(p.current_km AS SIGNED) - CAST(COALESCE(p.last_maintenance_km, 0) AS SIGNED)) >= p.maintenance_interval_km)
                           OR EXISTS (
                               SELECT 1 FROM product_subitems ps2
                               WHERE ps2.product_id = p.id
                                 AND p.is_vehicle = 1
                                 AND p.current_km IS NOT NULL
                                 AND COALESCE(NULLIF(ps2.maintenance_interval_km, 0), NULLIF(p.maintenance_interval_km, 0), 0) > 0
                                 AND (CAST(p.current_km AS SIGNED) - CAST(COALESCE(ps2.last_maintenance_km, p.last_maintenance_km, 0) AS SIGNED)) >= COALESCE(NULLIF(ps2.maintenance_interval_km, 0), NULLIF(p.maintenance_interval_km, 0))
                           )
                       )
                       THEN 1
                       ELSE 0
                   END as product_is_overdue
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
        if (!empty($filters['subitem_name'])) {
            $sql .= " AND (
                EXISTS (SELECT 1 FROM product_subitems ps WHERE ps.product_id = p.id AND ps.name LIKE :sub1)
                OR EXISTS (SELECT 1 FROM requisition_items ri WHERE ri.requisition_id = r.id AND ri.description LIKE :sub2)
            )";
            $term = '%' . trim((string)$filters['subitem_name']) . '%';
            $params['sub1'] = $term;
            $params['sub2'] = $term;
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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $overdueFilter = (string)($filters['overdue_status'] ?? '');
        if ($overdueFilter === 'overdue') {
            $rows = array_values(array_filter($rows, fn($r) => !empty($r['product_is_overdue'])));
        } elseif ($overdueFilter === 'ok') {
            $rows = array_values(array_filter($rows, fn($r) => empty($r['product_is_overdue'])));
        }

        return $rows;
    }

    public function getOdometerReport(array $filters = []): array
    {
        $sql = "
            SELECT m.*, p.name as product_name, p.license_plate, p.chassis,
                   p.maintenance_interval_km, p.last_maintenance_km,
                   u.name as user_name
            FROM vehicle_mileage_logs m
            JOIN products p ON p.id = m.product_id
            LEFT JOIN users u ON u.id = m.user_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['product_id'])) {
            $sql .= " AND m.product_id = :pid";
            $params['pid'] = (int)$filters['product_id'];
        }
        if (!empty($filters['license_plate'])) {
            $sql .= " AND p.license_plate LIKE :plate";
            $params['plate'] = '%' . trim((string)$filters['license_plate']) . '%';
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(m.recorded_at) >= :dfrom";
            $params['dfrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(m.recorded_at) <= :dto";
            $params['dto'] = $filters['date_to'];
        }

        $sql .= " ORDER BY m.product_id ASC, m.recorded_at ASC, m.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $ascRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $prevByVehicle = [];
        foreach ($ascRows as &$r) {
            $pid = (int)$r['product_id'];
            $km = (int)$r['mileage_km'];
            $r['km_delta'] = isset($prevByVehicle[$pid]) ? max(0, $km - $prevByVehicle[$pid]) : null;
            $prevByVehicle[$pid] = $km;
        }
        unset($r);

        usort($ascRows, fn($a, $b) => strcmp((string)$b['recorded_at'], (string)$a['recorded_at']) ?: ((int)$b['id'] <=> (int)$a['id']));
        return $ascRows;
    }

    public function getVehicleUsageAndFuelReport(array $filters = []): array
    {
        $vehicles = $this->getVehicles(false);
        $filterPid = !empty($filters['product_id']) ? (int)$filters['product_id'] : 0;
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));

        $overdueSubMap = [];
        foreach ($this->getOverdueSubitems() as $sub) {
            $overdueSubMap[(int)$sub['product_id']][] = $sub;
        }

        $report = [];
        foreach ($vehicles as $v) {
            $pid = (int)$v['id'];
            if ($filterPid > 0 && $pid !== $filterPid) {
                continue;
            }

            // Leituras de odômetro no período
            $mSql = "SELECT mileage_km, fuel_liters, recorded_at FROM vehicle_mileage_logs WHERE product_id = :pid";
            $mParams = ['pid' => $pid];
            if ($dateFrom !== '') {
                $mSql .= " AND DATE(recorded_at) >= :dfrom";
                $mParams['dfrom'] = $dateFrom;
            }
            if ($dateTo !== '') {
                $mSql .= " AND DATE(recorded_at) <= :dto";
                $mParams['dto'] = $dateTo;
            }
            $mSql .= " ORDER BY recorded_at ASC, id ASC";
            $mStmt = $this->db->prepare($mSql);
            $mStmt->execute($mParams);
            $mLogs = $mStmt->fetchAll(PDO::FETCH_ASSOC);

            $initialKm = !empty($mLogs) ? (int)$mLogs[0]['mileage_km'] : ($v['last_maintenance_km'] !== null ? (int)$v['last_maintenance_km'] : ($v['current_km'] !== null ? (int)$v['current_km'] : null));
            $finalKm = !empty($mLogs) ? (int)$mLogs[count($mLogs) - 1]['mileage_km'] : ($v['current_km'] !== null ? (int)$v['current_km'] : null);
            $kmDriven = ($initialKm !== null && $finalKm !== null) ? max(0, $finalKm - $initialKm) : 0;

            $litersFromLogs = 0.0;
            $lastFuelKm = null;
            foreach ($mLogs as $ml) {
                $fl = (float)($ml['fuel_liters'] ?? 0);
                if ($fl > 0) {
                    $litersFromLogs += $fl;
                    $lastFuelKm = (int)$ml['mileage_km'];
                }
            }

            // Litros em requisições vinculadas ao veículo (unidade 'l' ou itens com descrição de combustível)
            $rSql = "
                SELECT ri.quantity, ri.unit, ri.description, r.created_at
                FROM requisition_items ri
                JOIN requisitions r ON r.id = ri.requisition_id
                WHERE r.status NOT IN ('rejeitada', 'cancelada')
                  AND (
                      r.linked_product_id = :pid
                      OR (:plate != '' AND (r.title LIKE :plate1 OR ri.description LIKE :plate2))
                  )
                  AND (
                      LOWER(ri.unit) = 'l'
                      OR LOWER(ri.description) LIKE '%gasolina%'
                      OR LOWER(ri.description) LIKE '%etanol%'
                      OR LOWER(ri.description) LIKE '%diesel%'
                      OR LOWER(ri.description) LIKE '%combust%'
                  )
            ";
            $plate = trim((string)($v['license_plate'] ?? ''));
            $rParams = [
                'pid'    => $pid,
                'plate'  => $plate,
                'plate1' => '%' . $plate . '%',
                'plate2' => '%' . $plate . '%',
            ];
            if ($dateFrom !== '') {
                $rSql .= " AND DATE(r.created_at) >= :dfrom";
                $rParams['dfrom'] = $dateFrom;
            }
            if ($dateTo !== '') {
                $rSql .= " AND DATE(r.created_at) <= :dto";
                $rParams['dto'] = $dateTo;
            }
            $rStmt = $this->db->prepare($rSql);
            $rStmt->execute($rParams);
            $litersFromReqs = 0.0;
            foreach ($rStmt->fetchAll(PDO::FETCH_ASSOC) as $ri) {
                $litersFromReqs += (float)$ri['quantity'];
            }

            $totalLiters = round($litersFromLogs + $litersFromReqs, 2);
            $kmPerLiter = ($totalLiters > 0 && $kmDriven > 0) ? round($kmDriven / $totalLiters, 2) : null;
            $kmSinceLastFuel = ($lastFuelKm !== null && $finalKm !== null) ? max(0, $finalKm - $lastFuelKm) : null;

            $overdueSubs = $overdueSubMap[$pid] ?? [];
            $isDaysLate = (!empty($v['maintenance_interval_days']) && !empty($v['last_maintenance_at']) && ((time() - strtotime($v['last_maintenance_at'])) / 86400) > (int)$v['maintenance_interval_days']);
            $isKmLate = !empty($v['is_km_overdue']);
            $needsMaintenance = $isDaysLate || $isKmLate || !empty($overdueSubs);

            $report[] = [
                'product_id'          => $pid,
                'name'                => $v['name'],
                'license_plate'       => $v['license_plate'],
                'chassis'             => $v['chassis'],
                'readings_count'      => count($mLogs),
                'initial_km'          => $initialKm,
                'final_km'            => $finalKm,
                'km_driven'           => $kmDriven,
                'liters_from_logs'    => $litersFromLogs,
                'liters_from_reqs'    => $litersFromReqs,
                'total_liters'        => $totalLiters,
                'km_per_liter'        => $kmPerLiter,
                'km_since_last_fuel'  => $kmSinceLastFuel,
                'needs_maintenance'   => $needsMaintenance,
                'is_km_overdue'       => $isKmLate,
                'is_days_overdue'     => $isDaysLate,
                'overdue_subitems'    => $overdueSubs,
                'km_since_maint'      => $v['km_since_maintenance'] ?? null,
                'maintenance_int_km'  => $v['maintenance_interval_km'],
            ];
        }

        return $report;
    }
}
