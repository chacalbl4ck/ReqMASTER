<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\ActivityLog;
use App\Models\Supplier;
use App\Models\Contract;
use App\Models\Requisition;

class SupplierController
{
    private Supplier $supplierModel;
    private Contract $contractModel;
    private Requisition $requisitionModel;

    public function __construct()
    {
        Security::requireLogin();
        $this->supplierModel = new Supplier();
        $this->contractModel = new Contract();
        $this->requisitionModel = new Requisition();
    }

    public function index(): void
    {
        $status = $_GET['status'] ?? null;
        $search = trim($_GET['search'] ?? '');

        $suppliers = $this->supplierModel->all($status, $search);

        require __DIR__ . '/../../views/suppliers/index.php';
    }

    public function create(): void
    {
        require __DIR__ . '/../../views/suppliers/create.php';
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('fornecedores');
        }

        $name = trim($_POST['name'] ?? '');
        $document = trim($_POST['document'] ?? '');
        $category = trim($_POST['category'] ?? '');

        if (empty($name) || empty($document) || empty($category)) {
            flash('danger', 'Por favor, preencha os campos obrigatórios (Razão Social/Nome, Documento e Categoria).');
            redirect('fornecedores/novo');
        }

        try {
            $id = $this->supplierModel->create($_POST);
            flash('success', "Fornecedor / Prestador cadastrado com sucesso!");
            redirect("fornecedores/ver?id={$id}");
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() == 23000) {
                flash('danger', 'Já existe um cadastro com este CPF/CNPJ.');
            } else {
                flash('danger', 'Erro ao salvar fornecedor: ' . $e->getMessage());
            }
            redirect('fornecedores/novo');
        }
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $supplier = $this->supplierModel->findById($id);

        if (!$supplier) {
            flash('danger', 'Fornecedor não encontrado.');
            redirect('fornecedores');
        }

        $contracts = $this->contractModel->findBySupplier($id, false);
        $requisitions = $this->requisitionModel->all(['supplier_id' => $id]);

        require __DIR__ . '/../../views/suppliers/show.php';
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $supplier = $this->supplierModel->findById($id);

        if (!$supplier) {
            flash('danger', 'Fornecedor não encontrado.');
            redirect('fornecedores');
        }

        require __DIR__ . '/../../views/suppliers/edit.php';
    }

    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('fornecedores');
        }

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $document = trim($_POST['document'] ?? '');
        $category = trim($_POST['category'] ?? '');

        if (empty($name) || empty($document) || empty($category)) {
            flash('danger', 'Por favor, preencha os campos obrigatórios.');
            redirect("fornecedores/editar?id={$id}");
        }

        try {
            $this->supplierModel->update($id, $_POST);
            flash('success', "Cadastro de {$name} atualizado com sucesso!");
            redirect("fornecedores/ver?id={$id}");
        } catch (\Exception $e) {
            flash('danger', 'Erro ao atualizar fornecedor: ' . $e->getMessage());
            redirect("fornecedores/editar?id={$id}");
        }
    }

    public function delete(): void
    {
        Security::requireRole(['admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('fornecedores');
        }

        $id = (int)($_POST['id'] ?? 0);
        try {
            $this->supplierModel->delete($id);
            flash('success', 'Fornecedor excluído com sucesso.');
        } catch (\Exception $e) {
            flash('danger', 'Não é possível excluir este fornecedor pois ele possui contratos ou requisições vinculadas.');
        }

        redirect('fornecedores');
    }

    public function toggleStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('fornecedores');
        }

        $id = (int)($_POST['id'] ?? 0);
        $supplier = $this->supplierModel->findById($id);

        if (!$supplier) {
            flash('danger', 'Fornecedor não encontrado.');
            redirect('fornecedores');
        }

        $this->supplierModel->toggleStatus($id);
        $newStatus = ($supplier['status'] === 'ativo') ? 'inativo' : 'ativo';
        (new ActivityLog())->log('Fornecedor', "Fornecedor '{$supplier['name']}' alterado para {$newStatus}.");

        flash('success', "Fornecedor {$supplier['name']} marcado como " . ucfirst($newStatus) . ".");

        $back = $_POST['redirect_to'] ?? 'fornecedores';
        redirect($back === 'show' ? "fornecedores/ver?id={$id}" : 'fornecedores');
    }

    public function consultarCnpj(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $cnpj = preg_replace('/\D/', '', $_GET['cnpj'] ?? '');
        if (strlen($cnpj) !== 14) {
            echo json_encode(['success' => false, 'message' => 'CNPJ deve conter 14 dígitos.']);
            exit;
        }

        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "User-Agent: ReqMaster-App/2.0\r\nAccept: application/json\r\n"
            ]
        ];
        $context = stream_context_create($opts);

        // 1. Tenta BrasilAPI
        $urlBrasilApi = "https://brasilapi.com.br/api/cnpj/v1/{$cnpj}";
        $response = @file_get_contents($urlBrasilApi, false, $context);

        if ($response !== false) {
            $data = json_decode($response, true);
            if (!empty($data['razao_social'])) {
                $logradouro = trim(($data['descricao_tipo_de_logradouro'] ?? '') . ' ' . ($data['logradouro'] ?? ''));
                $numero = $data['numero'] ?? '';
                $complemento = $data['complemento'] ?? '';
                $bairro = $data['bairro'] ?? '';

                $addressParts = array_filter([$logradouro, $numero ? 'Nº ' . $numero : '', $complemento, $bairro]);
                $fullAddress = implode(', ', $addressParts);

                $category = $data['cnae_fiscal_descricao'] ?? 'Comércio / Serviços';
                if (!empty($data['cnaes_secundarios']) && is_array($data['cnaes_secundarios'])) {
                    // opcional
                }

                echo json_encode([
                    'success'    => true,
                    'name'       => $data['razao_social'] ?? '',
                    'trade_name' => $data['nome_fantasia'] ?? '',
                    'document'   => Security::formatDocument($cnpj),
                    'email'      => strtolower($data['email'] ?? ''),
                    'phone'      => $data['ddd_telefone_1'] ?? $data['ddd_telefone_2'] ?? '',
                    'address'    => $fullAddress,
                    'city'       => $data['municipio'] ?? '',
                    'state'      => $data['uf'] ?? '',
                    'zip_code'   => $data['cep'] ?? '',
                    'category'   => $category
                ]);
                exit;
            }
        }

        // 2. Fallback: ReceitaWS
        $urlReceitaWs = "https://receitaws.com.br/v1/cnpj/{$cnpj}";
        $responseWs = @file_get_contents($urlReceitaWs, false, $context);
        if ($responseWs !== false) {
            $dataWs = json_decode($responseWs, true);
            if (!empty($dataWs['nome'])) {
                $addressParts = array_filter([
                    $dataWs['logradouro'] ?? '',
                    !empty($dataWs['numero']) ? 'Nº ' . $dataWs['numero'] : '',
                    $dataWs['complemento'] ?? '',
                    $dataWs['bairro'] ?? ''
                ]);

                $cat = '';
                if (!empty($dataWs['atividade_principal'][0]['text'])) {
                    $cat = $dataWs['atividade_principal'][0]['text'];
                }

                echo json_encode([
                    'success'    => true,
                    'name'       => $dataWs['nome'],
                    'trade_name' => $dataWs['fantasia'] ?? '',
                    'document'   => Security::formatDocument($cnpj),
                    'email'      => strtolower($dataWs['email'] ?? ''),
                    'phone'      => $dataWs['telefone'] ?? '',
                    'address'    => implode(', ', $addressParts),
                    'city'       => $dataWs['municipio'] ?? '',
                    'state'      => $dataWs['uf'] ?? '',
                    'zip_code'   => $dataWs['cep'] ?? '',
                    'category'   => $cat ?: 'Comércio / Serviços'
                ]);
                exit;
            }
        }

        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível localizar os dados para este CNPJ nas bases da Receita Federal.'
        ]);
        exit;
    }
}
