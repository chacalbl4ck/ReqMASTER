<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\Requisition;
use App\Models\Contract;

class DashboardController
{
    private Requisition $reqModel;
    private Contract $contractModel;

    public function __construct()
    {
        Security::requireLogin();
        $this->reqModel = new Requisition();
        $this->contractModel = new Contract();
    }

    public function index(): void
    {
        $metrics = $this->reqModel->getDashboardMetrics();
        $recentRequisitions = $this->reqModel->all(['search' => '']);
        $recentRequisitions = array_slice($recentRequisitions, 0, 8); // 8 mais recentes
        $expiringContracts = $this->contractModel->getExpiringSoon(60); // próximos 60 dias

        require __DIR__ . '/../../views/dashboard/index.php';
    }
}
