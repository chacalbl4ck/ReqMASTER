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
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);

        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (requisition_id, contract_id, user_id, action, description, ip_address)
            VALUES (:req_id, :contract_id, :user_id, :action, :description, :ip)
        ");
        $stmt->execute([
            'req_id'      => $requisitionId,
            'contract_id' => $contractId,
            'user_id'     => $userId,
            'action'      => $action,
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
}
