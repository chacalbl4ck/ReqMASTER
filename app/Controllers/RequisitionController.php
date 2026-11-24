<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\Contract;
use App\Models\ActivityLog;

class RequisitionController
{
    private Requisition $reqModel;
    private Supplier $supplierModel;
    private Contract $contractModel;
    private ActivityLog $logModel;

    public function __construct()
    {
        Security::requireLogin();
        $this->reqModel = new Requisition();
        $this->supplierModel = new Supplier();
        $this->contractModel = new Contract();
        $this->logModel = new ActivityLog();
    }

    public function index(): void
    {
        $filters = [
            'status'      => $_GET['status'] ?? null,
            'type'        => $_GET['type'] ?? null,
            'supplier_id' => $_GET['supplier_id'] ?? null,
            'contract_id' => $_GET['contract_id'] ?? null,
            'priority'    => $_GET['priority'] ?? null,
            'search'      => trim($_GET['search'] ?? ''),
            'date_from'   => $_GET['date_from'] ?? null,
            'date_to'     => $_GET['date_to'] ?? null,
        ];

        // Se solicitante comum, pode ver suas requisições ou todas dependendo do fluxo
        $requisitions = $this->reqModel->all($filters);
        $suppliers = $this->supplierModel->getActiveList();

        require __DIR__ . '/../../views/requisitions/index.php';
    }

    public function create(): void
    {
        $suppliers = $this->supplierModel->getActiveList();
        $selectedSupplierId = (int)($_GET['supplier_id'] ?? 0);
        $selectedContractId = (int)($_GET['contract_id'] ?? 0);
        $reqType = $_GET['type'] ?? 'material'; // material ou servico

        $contracts = [];
        if ($selectedSupplierId > 0) {
            $contracts = $this->contractModel->findBySupplier($selectedSupplierId, true);
        }

        require __DIR__ . '/../../views/requisitions/create.php';
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('requisicoes');
        }

        $user = Security::getUser();
        $type = $_POST['type'] ?? 'material';
        $title = trim($_POST['title'] ?? '');
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $contractId = !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null;
        $department = trim($_POST['department'] ?? ($user['department'] ?? 'Geral'));
        $priority = $_POST['priority'] ?? 'media';
        $justification = trim($_POST['justification'] ?? '');
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

        if (empty($title) || empty($supplierId) || empty($justification)) {
            flash('danger', 'Por favor, preencha todos os campos obrigatórios (Título, Fornecedor/Prestador e Justificativa).');
            redirect('requisicoes/nova');
        }

        // Processa itens enviados dinamicamente
        $rawDescriptions = $_POST['item_description'] ?? [];
        $rawUnits = $_POST['item_unit'] ?? [];
        $rawQuantities = $_POST['item_quantity'] ?? [];
        $rawPrices = $_POST['item_price'] ?? [];
        $rawSpecs = $_POST['item_specs'] ?? [];

        $items = [];
        $totalEstimated = 0.0;

        foreach ($rawDescriptions as $index => $desc) {
            $desc = trim($desc);
            if (empty($desc)) continue;

            $qtyStr = str_replace(',', '.', (string)($rawQuantities[$index] ?? 1));
            $qty = max(0.01, (float)$qtyStr);

            $priceStr = str_replace(['R$', ' ', '.', ','], ['', '', '', '.'], (string)($rawPrices[$index] ?? 0));
            $price = max(0.00, (float)$priceStr);

            $totalEstimated += ($qty * $price);

            $items[] = [
                'description'     => $desc,
                'unit'            => trim($rawUnits[$index] ?? 'un'),
                'quantity'        => $qty,
                'unit_price'      => $price,
                'technical_specs' => trim($rawSpecs[$index] ?? '')
            ];
        }

        if (empty($items)) {
            flash('danger', 'Adicione pelo menos um item ou serviço à requisição.');
            redirect('requisicoes/nova');
        }

        // Validação de saldo do contrato (se vinculado)
        if ($contractId) {
            $contract = $this->contractModel->findById($contractId);
            if ($contract) {
                if ($contract['status'] !== 'ativo') {
                    flash('danger', 'O contrato selecionado não está ativo.');
                    redirect('requisicoes/nova');
                }
                if ($totalEstimated > $contract['balance_value']) {
                    flash('warning', 'Atenção: O valor total da requisição (' . formatMoney($totalEstimated) . ') excede o saldo remanescente do contrato (' . formatMoney($contract['balance_value']) . ').');
                }
            }
        }

        $data = [
            'user_id'       => $user['id'],
            'supplier_id'   => $supplierId,
            'contract_id'   => $contractId,
            'type'          => $type,
            'title'         => $title,
            'department'    => $department,
            'priority'      => $priority,
            'justification' => $justification,
            'deadline'      => $deadline,
            'status'        => 'pendente'
        ];

        try {
            $requisitionId = $this->reqModel->createWithItems($data, $items);
            flash('success', "Requisição criada com sucesso! Código gerado.");
            redirect("requisicoes/ver?id={$requisitionId}");
        } catch (\Exception $e) {
            flash('danger', 'Erro ao salvar requisição: ' . $e->getMessage());
            redirect('requisicoes/nova');
        }
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $requisition = $this->reqModel->findById($id);

        if (!$requisition) {
            flash('danger', 'Requisição não encontrada.');
            redirect('requisicoes');
        }

        $logs = $this->logModel->getByRequisition($id);
        $currentUser = Security::getUser();

        require __DIR__ . '/../../views/requisitions/show.php';
    }

    public function changeStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação inválida.');
            redirect('requisicoes');
        }

        $id = (int)($_POST['id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $user = Security::getUser();

        $allowedStatuses = ['pendente', 'em_analise', 'aprovada', 'rejeitada', 'em_execucao', 'concluida', 'cancelada'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            flash('danger', 'Status inválido.');
            redirect("requisicoes/ver?id={$id}");
        }

        // Permissão de Aprovação / Rejeição restrita a admin ou aprovador
        if (in_array($newStatus, ['aprovada', 'rejeitada', 'em_analise']) && !Security::hasRole(['admin', 'aprovador'])) {
            flash('danger', 'Apenas gestores ou aprovadores podem aprovar ou rejeitar requisições.');
            redirect("requisicoes/ver?id={$id}");
        }

        try {
            $this->reqModel->updateStatus($id, $newStatus, (int)$user['id'], $reason);
            $statusLabels = [
                'aprovada' => 'aprovada com sucesso',
                'rejeitada' => 'rejeitada',
                'em_execucao' => 'movida para em execução',
                'concluida' => 'marcada como concluída',
                'cancelada' => 'cancelada'
            ];
            $msg = $statusLabels[$newStatus] ?? "atualizada para '{$newStatus}'";
            flash('success', "Requisição {$msg}!");
        } catch (\Exception $e) {
            flash('danger', 'Erro ao atualizar status: ' . $e->getMessage());
        }

        redirect("requisicoes/ver?id={$id}");
    }

    public function print(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $requisition = $this->reqModel->findById($id);

        if (!$requisition) {
            die('Requisição não encontrada.');
        }

        require __DIR__ . '/../../views/requisitions/print.php';
    }
}
