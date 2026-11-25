<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\Requisition;
use App\Models\Contract;
use App\Models\Product;

class DashboardController
{
    private Requisition $reqModel;
    private Contract $contractModel;
    private Product $productModel;

    public function __construct()
    {
        Security::requireLogin();
        $this->reqModel = new Requisition();
        $this->contractModel = new Contract();
        $this->productModel = new Product();
    }

    public function index(): void
    {
        $metrics = $this->reqModel->getDashboardMetrics();
        $recentRequisitions = $this->reqModel->all(['search' => '', 'limit' => 8]);
        $expiringContracts = $this->contractModel->getExpiringSoon(60); // próximos 60 dias
        $overdueProducts = $this->productModel->getOverdueMaintenance();

        require __DIR__ . '/../../views/dashboard/index.php';
    }
}
