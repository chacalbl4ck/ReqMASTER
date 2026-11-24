<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\Contract;
use App\Models\Product;

class ReportController
{
    private Requisition $reqModel;
    private Supplier $supplierModel;
    private Contract $contractModel;

    public function __construct()
    {
        Security::requireLogin();
        $this->reqModel = new Requisition();
        $this->supplierModel = new Supplier();
        $this->contractModel = new Contract();
    }

    public function index(): void
    {
        $filters = [
            'status'      => $_GET['status'] ?? null,
            'type'        => $_GET['type'] ?? null,
            'supplier_id' => $_GET['supplier_id'] ?? null,
            'contract_id' => $_GET['contract_id'] ?? null,
            'date_from'   => $_GET['date_from'] ?? date('Y-01-01'),
            'date_to'     => $_GET['date_to'] ?? date('Y-m-d'),
        ];

        $requisitions = $this->reqModel->all($filters);
        $suppliers = $this->supplierModel->getActiveList();
        $contracts = $this->contractModel->all();

        // Totais agregados
        $totalAmount = array_sum(array_column($requisitions, 'total_amount'));
        $totalItems = count($requisitions);

        require __DIR__ . '/../../views/reports/index.php';
    }

    public function exportCsv(): void
    {
        $filters = [
            'status'      => $_GET['status'] ?? null,
            'type'        => $_GET['type'] ?? null,
            'supplier_id' => $_GET['supplier_id'] ?? null,
            'contract_id' => $_GET['contract_id'] ?? null,
            'date_from'   => $_GET['date_from'] ?? null,
            'date_to'     => $_GET['date_to'] ?? null,
        ];

        $requisitions = $this->reqModel->all($filters);

        $filename = 'relatorio_requisicoes_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        // BOM UTF-8 para Excel abrir com acentuação correta
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Cabeçalhos
        fputcsv($output, [
            'Código',
            'Tipo',
            'Título',
            'Departamento',
            'Solicitante',
            'Para Quem (Fornecedor)',
            'CNPJ/CPF',
            'Contrato Vinculado',
            'Prioridade',
            'Status',
            'Valor Total (R$)',
            'Data Solicitação',
            'Aprovado Por'
        ], ';');

        foreach ($requisitions as $r) {
            fputcsv($output, [
                $r['code'],
                $r['type'] === 'servico' ? 'Serviço' : 'Material',
                $r['title'],
                $r['department'],
                $r['requester_name'],
                $r['supplier_name'],
                $r['supplier_document'],
                $r['contract_number'] ? ($r['contract_number'] . ' - ' . $r['contract_title']) : 'Sem Contrato',
                ucfirst($r['priority']),
                ucfirst($r['status']),
                number_format((float)$r['total_amount'], 2, ',', '.'),
                formatDate($r['created_at']),
                $r['approver_name'] ?? '-'
            ], ';');
        }

        fclose($output);
        exit;
    }

    public function maintenance(): void
    {
        $productModel = new Product();
        $filters = [
            'product_id'       => $_GET['product_id'] ?? '',
            'maintenance_type' => $_GET['maintenance_type'] ?? '',
            'status'           => $_GET['status'] ?? '',
            'date_from'        => $_GET['date_from'] ?? '',
            'date_to'          => $_GET['date_to'] ?? '',
        ];

        $records = $productModel->getMaintenanceReport($filters);
        $products = $productModel->all(false);
        $overdueProducts = $productModel->getOverdueMaintenance();

        $totalOrders = count($records);
        $completedOrders = 0;
        $totalMaintenanceCost = 0.0;
        foreach ($records as $rec) {
            if (($rec['status'] ?? '') === 'concluida') {
                $completedOrders++;
            }
            if (!empty($rec['has_values']) && !in_array($rec['status'] ?? '', ['rejeitada', 'cancelada'], true)) {
                $totalMaintenanceCost += (float)($rec['total_amount'] ?? 0);
            }
        }

        require __DIR__ . '/../../views/reports/maintenance.php';
    }

    public function exportMaintenanceCsv(): void
    {
        $productModel = new Product();
        $filters = [
            'product_id'       => $_GET['product_id'] ?? '',
            'maintenance_type' => $_GET['maintenance_type'] ?? '',
            'status'           => $_GET['status'] ?? '',
            'date_from'        => $_GET['date_from'] ?? '',
            'date_to'          => $_GET['date_to'] ?? '',
        ];
        $records = $productModel->getMaintenanceReport($filters);

        $filename = 'relatorio_manutencoes_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, [
            'Código OS',
            'Produto / Equipamento',
            'Número de Série',
            'Tipo de Manutenção',
            'Título da Solicitação',
            'Prestador / Fornecedor',
            'Solicitante',
            'Status',
            'Data Solicitação',
            'Data Conclusão',
            'Última Manutenção do Produto',
            'Valor Total (R$)'
        ], ';');

        foreach ($records as $r) {
            fputcsv($output, [
                $r['code'],
                $r['product_name'],
                $r['serial_number'] ?: '-',
                ($r['maintenance_type'] ?? 'reparo') === 'preventiva' ? 'Manutenção Preventiva' : 'Reparo / Corretiva',
                $r['title'],
                $r['supplier_name'] ?? '-',
                $r['requester_name'] ?? '-',
                statusLabel($r['status']),
                formatDate($r['created_at']),
                !empty($r['completed_at']) ? formatDate($r['completed_at']) : '-',
                !empty($r['last_maintenance_at']) ? formatDate($r['last_maintenance_at']) : '-',
                !empty($r['has_values']) ? number_format((float)$r['total_amount'], 2, ',', '.') : 'Sem valor'
            ], ';');
        }

        fclose($output);
        exit;
    }
}
