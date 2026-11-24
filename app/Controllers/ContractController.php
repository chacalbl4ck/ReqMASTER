<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\Requisition;
use App\Models\ActivityLog;

class ContractController
{
    private Contract $contractModel;
    private Supplier $supplierModel;
    private Requisition $requisitionModel;

    public function __construct()
    {
        Security::requireLogin();
        $this->contractModel = new Contract();
        $this->supplierModel = new Supplier();
        $this->requisitionModel = new Requisition();
    }

    public function index(): void
    {
        $status = $_GET['status'] ?? null;
        $supplierId = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
        $search = trim($_GET['search'] ?? '');

        $contracts = $this->contractModel->all($supplierId, $status, $search);
        $suppliers = $this->supplierModel->getActiveList();

        require __DIR__ . '/../../views/contracts/index.php';
    }

    public function create(): void
    {
        $suppliers = $this->supplierModel->getActiveList();
        $selectedSupplierId = (int)($_GET['supplier_id'] ?? 0);

        require __DIR__ . '/../../views/contracts/create.php';
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('contratos');
        }

        $contractNumber = trim($_POST['contract_number'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $totalValue = (float)str_replace(['R$', ' ', '.', ','], ['', '', '', '.'], $_POST['total_value'] ?? '0');
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';

        if (empty($contractNumber) || empty($title) || empty($supplierId) || empty($startDate) || empty($endDate)) {
            flash('danger', 'Preencha todos os campos obrigatórios do contrato.');
            redirect('contratos/novo');
        }

        $data = [
            'supplier_id'     => $supplierId,
            'contract_number' => $contractNumber,
            'title'           => $title,
            'type'            => $_POST['type'] ?? 'servico',
            'total_value'     => $totalValue,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'renewal_clause'  => trim($_POST['renewal_clause'] ?? ''),
            'notes'           => trim($_POST['notes'] ?? ''),
            'status'          => $_POST['status'] ?? 'ativo'
        ];

        try {
            $id = $this->contractModel->create($data);
            
            $log = new ActivityLog();
            $log->log('Criação', "Contrato {$contractNumber} registrado com teto de " . formatMoney($totalValue), null, $id);

            flash('success', "Contrato {$contractNumber} cadastrado com sucesso!");
            redirect("contratos/ver?id={$id}");
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() == 23000) {
                flash('danger', 'Já existe um contrato cadastrado com este número.');
            } else {
                flash('danger', 'Erro ao salvar contrato: ' . $e->getMessage());
            }
            redirect('contratos/novo');
        }
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $contract = $this->contractModel->findById($id);

        if (!$contract) {
            flash('danger', 'Contrato não encontrado.');
            redirect('contratos');
        }

        $requisitions = $this->requisitionModel->all(['contract_id' => $id]);

        require __DIR__ . '/../../views/contracts/show.php';
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $contract = $this->contractModel->findById($id);

        if (!$contract) {
            flash('danger', 'Contrato não encontrado.');
            redirect('contratos');
        }

        $suppliers = $this->supplierModel->getActiveList();
        require __DIR__ . '/../../views/contracts/edit.php';
    }

    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('contratos');
        }

        $id = (int)($_POST['id'] ?? 0);
        $contractNumber = trim($_POST['contract_number'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $totalValue = (float)str_replace(['R$', ' ', '.', ','], ['', '', '', '.'], $_POST['total_value'] ?? '0');
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';

        if (empty($contractNumber) || empty($title) || empty($supplierId) || empty($startDate) || empty($endDate)) {
            flash('danger', 'Preencha todos os campos obrigatórios.');
            redirect("contratos/editar?id={$id}");
        }

        $data = [
            'supplier_id'     => $supplierId,
            'contract_number' => $contractNumber,
            'title'           => $title,
            'type'            => $_POST['type'] ?? 'servico',
            'total_value'     => $totalValue,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'renewal_clause'  => trim($_POST['renewal_clause'] ?? ''),
            'notes'           => trim($_POST['notes'] ?? ''),
            'status'          => $_POST['status'] ?? 'ativo'
        ];

        try {
            $this->contractModel->update($id, $data);
            $this->contractModel->recalculateUsedValue($id);

            flash('success', "Contrato atualizado com sucesso!");
            redirect("contratos/ver?id={$id}");
        } catch (\Exception $e) {
            flash('danger', 'Erro ao atualizar contrato: ' . $e->getMessage());
            redirect("contratos/editar?id={$id}");
        }
    }

    public function apiBySupplier(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $supplierId = (int)($_GET['supplier_id'] ?? 0);

        if (!$supplierId) {
            echo json_encode([]);
            exit;
        }

        $contracts = $this->contractModel->findBySupplier($supplierId, true);
        echo json_encode($contracts);
        exit;
    }
}
