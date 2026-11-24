<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\ActivityLog;
use App\Models\User;

class LogController
{
    private ActivityLog $logModel;
    private User $userModel;

    public function __construct()
    {
        Security::requireRole(['admin']);
        $this->logModel = new ActivityLog();
        $this->userModel = new User();
    }

    public function index(): void
    {
        $filters = [
            'search'    => trim($_GET['search'] ?? ''),
            'action'    => trim($_GET['action'] ?? ''),
            'user_id'   => $_GET['user_id'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? '',
        ];

        $logs = $this->logModel->getAllFiltered($filters, 300);
        $actions = $this->logModel->getDistinctActions();
        $users = $this->userModel->all();
        $stats = $this->logModel->getSummaryStats();

        require __DIR__ . '/../../views/logs/index.php';
    }

    public function exportCsv(): void
    {
        $filters = [
            'search'    => trim($_GET['search'] ?? ''),
            'action'    => trim($_GET['action'] ?? ''),
            'user_id'   => $_GET['user_id'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? '',
        ];

        $logs = $this->logModel->getAllFiltered($filters, 1000);
        $this->logModel->log('Exportação de Logs', 'Administrador exportou ' . count($logs) . ' registros de auditoria em CSV.');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="logs_sistema_' . date('Y-m-d_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['ID', 'Data/Hora', 'Ação', 'Usuário', 'Perfil', 'Requisição', 'Descrição', 'Endereço IP'], ';');

        foreach ($logs as $l) {
            fputcsv($out, [
                $l['id'],
                $l['created_at'],
                $l['action'],
                $l['user_name'] ?? 'Sistema / Convidado',
                $l['user_role'] ?? '-',
                $l['requisition_code'] ?? '-',
                $l['description'],
                $l['ip_address'] ?? '-',
            ], ';');
        }
        fclose($out);
        exit;
    }
}
