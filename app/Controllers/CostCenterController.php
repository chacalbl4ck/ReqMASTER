<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\ActivityLog;
use App\Models\CostCenter;
use PDOException;

class CostCenterController
{
    private CostCenter $costCenterModel;
    private ActivityLog $logModel;

    public function __construct()
    {
        Security::requireLogin();
        $this->costCenterModel = new CostCenter();
        $this->logModel = new ActivityLog();
    }

    public function index(): void
    {
        $costCenters = $this->costCenterModel->all(false);
        $isAdmin = Security::hasRole(['admin']);
        require __DIR__ . '/../../views/cost_centers/index.php';
    }

    public function store(): void
    {
        $isAjax = !empty($_POST['ajax']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Requisição inválida ou token expirado.']);
                exit;
            }
            flash('danger', 'Requisição inválida.');
            redirect('centros-de-custo');
        }

        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $active = isset($_POST['active']) ? 1 : ($id === 0 ? 1 : 0);

        if (empty($code) || empty($name)) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Código e Nome do Centro de Custo são obrigatórios.']);
                exit;
            }
            flash('danger', 'Código e Nome do Centro de Custo são obrigatórios.');
            redirect('centros-de-custo');
        }

        try {
            if ($id > 0) {
                $this->costCenterModel->update($id, [
                    'code'        => $code,
                    'name'        => $name,
                    'description' => $description,
                    'active'      => $active,
                ]);
                $this->logModel->log('Centro de Custo', "Centro de Custo '{$code} - {$name}' atualizado.");
                flash('success', "Centro de custo {$code} - {$name} atualizado!");
            } else {
                $newId = $this->costCenterModel->create([
                    'code'        => $code,
                    'name'        => $name,
                    'description' => $description,
                    'active'      => 1,
                ]);
                $this->logModel->log('Centro de Custo', "Novo Centro de Custo '{$code} - {$name}' cadastrado.");

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => true,
                        'id'      => $newId,
                        'code'    => $code,
                        'name'    => $name,
                        'label'   => "{$code} - {$name}",
                        'message' => 'Centro de custo cadastrado com sucesso!'
                    ]);
                    exit;
                }
                flash('success', "Centro de custo {$code} - {$name} cadastrado com sucesso!");
            }
        } catch (PDOException $e) {
            $msg = 'Já existe um centro de custo com este código.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            flash('danger', $msg);
        }

        redirect('centros-de-custo');
    }

    public function toggleStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('centros-de-custo');
        }

        $id = (int)($_POST['id'] ?? 0);
        $cc = $this->costCenterModel->findById($id);
        if ($cc) {
            $this->costCenterModel->toggleStatus($id);
            $this->logModel->log('Centro de Custo', "Status do Centro de Custo '{$cc['code']} - {$cc['name']}' alterado.");
            flash('success', 'Status do centro de custo atualizado.');
        }
        redirect('centros-de-custo');
    }

    public function delete(): void
    {
        Security::requireRole(['admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('centros-de-custo');
        }

        $id = (int)($_POST['id'] ?? 0);
        $cc = $this->costCenterModel->findById($id);
        if ($cc) {
            $this->costCenterModel->delete($id);
            $this->logModel->log('Centro de Custo', "Centro de Custo '{$cc['code']} - {$cc['name']}' excluído pelo administrador.");
            flash('success', 'Centro de custo excluído com sucesso.');
        }
        redirect('centros-de-custo');
    }
}
