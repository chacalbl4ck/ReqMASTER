<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class ActivityLog extends Model
{
    public function log(
        string $action,
        string $description,
        ?int $requisitionId = null,
        ?int $contractId = null,
        ?int $userId = null
    ): void {
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45);
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);

        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (requisition_id, contract_id, user_id, action, description, ip_address)
            VALUES (:req_id, :contract_id, :user_id, :action, :description, :ip)
        ");
        $stmt->execute([
            'req_id'      => $requisitionId,
            'contract_id' => $contractId,
            'user_id'     => $userId,
            'action'      => mb_substr($action, 0, 80, 'UTF-8'),
            'description' => $description,
            'ip'          => $ip
        ]);
    }

    public function getByRequisition(int $requisitionId): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*, u.name as user_name, u.role as user_role
            FROM activity_logs l
            LEFT JOIN users u ON u.id = l.user_id
            WHERE l.requisition_id = :id
            ORDER BY l.created_at DESC
        ");
        $stmt->execute(['id' => $requisitionId]);
        return $stmt->fetchAll();
    }

    public function getAllFiltered(array $filters = [], int $limit = 300): array
    {
        $sql = "
            SELECT l.*,
                   u.name as user_name,
                   u.email as user_email,
                   u.role as user_role,
                   r.code as requisition_code,
                   c.contract_number
            FROM activity_logs l
            LEFT JOIN users u ON u.id = l.user_id
            LEFT JOIN requisitions r ON r.id = l.requisition_id
            LEFT JOIN contracts c ON c.id = l.contract_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['action'])) {
            $sql .= " AND l.action = :action";
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND l.user_id = :user_id";
            $params['user_id'] = (int)$filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(l.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(l.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (l.description LIKE :s OR l.action LIKE :s2 OR l.ip_address LIKE :s3 OR u.name LIKE :s4 OR r.code LIKE :s5)";
            $term = '%' . $filters['search'] . '%';
            $params['s'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
            $params['s5'] = $term;
        }

        $sql .= " ORDER BY l.created_at DESC, l.id DESC LIMIT " . max(1, min(1000, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDistinctActions(): array
    {
        $stmt = $this->db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getSummaryStats(): array
    {
        $total = (int)$this->db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
        $last24h = (int)$this->db->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
        $uniqueIps = (int)$this->db->query("SELECT COUNT(DISTINCT ip_address) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $securityAlerts = (int)$this->db->query("SELECT COUNT(*) FROM activity_logs WHERE action LIKE '%Falha%' OR action LIKE '%2FA%' OR action LIKE '%Licen%'")->fetchColumn();

        return [
            'total'           => $total,
            'last_24h'        => $last24h,
            'unique_ips'      => $uniqueIps,
            'security_events' => $securityAlerts,
        ];
    }
}
