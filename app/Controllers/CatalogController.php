<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ServiceCatalog;

class CatalogController
{
    private Product $productModel;
    private ServiceCatalog $serviceModel;
    private ActivityLog $logModel;

    public function __construct()
    {
        Security::requireLogin();
        $this->productModel = new Product();
        $this->serviceModel = new ServiceCatalog();
        $this->logModel = new ActivityLog();
    }

    // ========================================================
    // PRODUTOS / EQUIPAMENTOS
    // ========================================================

    public function products(): void
    {
        $search = trim($_GET['search'] ?? '');
        $products = $this->productModel->all(false, $search);
        $isAdmin = Security::hasRole(['admin']);
        require __DIR__ . '/../../views/catalog/products.php';
    }

    public function showProduct(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $product = $this->productModel->findById($id);
        if (!$product) {
            flash('danger', 'Produto não encontrado.');
            redirect('produtos');
        }
        $isAdmin = Security::hasRole(['admin']);
        require __DIR__ . '/../../views/catalog/product_show.php';
    }

    public function saveProduct(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('produtos');
        }

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $serialNumber = trim($_POST['serial_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $intervalDays = trim($_POST['maintenance_interval_days'] ?? '');
        $lastMaint = trim($_POST['last_maintenance_at'] ?? '');
        $active = isset($_POST['active']) ? 1 : ($id === 0 ? 1 : 0);

        if (empty($name)) {
            flash('danger', 'O nome do produto é obrigatório.');
            redirect('produtos');
        }

        $uploadedPhotos = [];
        if (!empty($_FILES['photos']['name'][0])) {
            $dir = __DIR__ . '/../../public/uploads/products';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $count = count($_FILES['photos']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name'     => $_FILES['photos']['name'][$i],
                        'type'     => $_FILES['photos']['type'][$i],
                        'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                        'error'    => $_FILES['photos']['error'][$i],
                        'size'     => $_FILES['photos']['size'][$i],
                    ];
                    $check = Security::validateUploadedFile($file, ['png', 'jpg', 'jpeg', 'webp', 'gif'], ['image/'], 10 * 1024 * 1024);
                    if (!$check['valid']) continue;

                    $clean = 'prod_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $check['ext'];
                    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $clean)) {
                        $uploadedPhotos[] = [
                            'filename'      => 'uploads/products/' . $clean,
                            'original_name' => $file['name'],
                        ];
                    }
                }
            }
        }

        $data = [
            'name'                      => $name,
            'serial_number'             => $serialNumber,
            'notes'                     => $notes,
            'maintenance_interval_days' => $intervalDays !== '' ? (int)$intervalDays : null,
            'last_maintenance_at'       => $lastMaint !== '' ? $lastMaint : null,
            'active'                    => $active,
        ];

        if ($id > 0) {
            $this->productModel->update($id, $data, $uploadedPhotos);
            $this->logModel->log('Produto Atualizado', "Produto '{$name}' atualizado no catálogo.");
            flash('success', "Produto '{$name}' atualizado com sucesso!");
        } else {
            $newId = $this->productModel->create($data, $uploadedPhotos);
            $this->logModel->log('Produto Cadastrado', "Produto '{$name}' (#{$newId}) cadastrado no catálogo.");
            flash('success', "Produto '{$name}' cadastrado com sucesso!");
        }

        $redirectTo = ($_POST['redirect_to'] ?? '') === 'show' && $id > 0 ? "produtos/ver?id={$id}" : 'produtos';
        redirect($redirectTo);
    }

    public function deleteProductPhoto(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('produtos');
        }

        $photoId = (int)($_POST['photo_id'] ?? 0);
        $productId = $this->productModel->deletePhoto($photoId);
        flash('success', 'Foto removida.');
        redirect($productId ? "produtos/ver?id={$productId}" : 'produtos');
    }

    public function toggleProductStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('produtos');
        }
        $id = (int)($_POST['id'] ?? 0);
        $p = $this->productModel->findById($id);
        if ($p) {
            $this->productModel->toggleStatus($id);
            $this->logModel->log('Produto Status', "Status do produto '{$p['name']}' alterado.");
            flash('success', 'Status do produto alterado.');
        }
        redirect('produtos');
    }

    public function deleteProduct(): void
    {
        Security::requireRole(['admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('produtos');
        }
        $id = (int)($_POST['id'] ?? 0);
        $p = $this->productModel->findById($id);
        if ($p) {
            $this->productModel->delete($id);
            $this->logModel->log('Produto Excluído', "Produto '{$p['name']}' excluído do catálogo.");
            flash('success', "Produto '{$p['name']}' excluído.");
        }
        redirect('produtos');
    }

    // ========================================================
    // SERVIÇOS PRÉ-CADASTRADOS
    // ========================================================

    public function services(): void
    {
        $search = trim($_GET['search'] ?? '');
        $services = $this->serviceModel->all(false, $search);
        $isAdmin = Security::hasRole(['admin']);
        require __DIR__ . '/../../views/catalog/services.php';
    }

    public function saveService(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('servicos');
        }

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $active = isset($_POST['active']) ? 1 : ($id === 0 ? 1 : 0);

        if (empty($name)) {
            flash('danger', 'O nome do serviço é obrigatório.');
            redirect('servicos');
        }

        $data = [
            'name'   => $name,
            'notes'  => $notes,
            'active' => $active,
        ];

        if ($id > 0) {
            $this->serviceModel->update($id, $data);
            $this->logModel->log('Serviço Atualizado', "Serviço '{$name}' atualizado no catálogo.");
            flash('success', "Serviço '{$name}' atualizado com sucesso!");
        } else {
            $this->serviceModel->create($data);
            $this->logModel->log('Serviço Cadastrado', "Serviço '{$name}' cadastrado no catálogo.");
            flash('success', "Serviço '{$name}' cadastrado com sucesso!");
        }

        redirect('servicos');
    }

    public function toggleServiceStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('servicos');
        }
        $id = (int)($_POST['id'] ?? 0);
        $s = $this->serviceModel->findById($id);
        if ($s) {
            $this->serviceModel->toggleStatus($id);
            $this->logModel->log('Serviço Status', "Status do serviço '{$s['name']}' alterado.");
            flash('success', 'Status do serviço alterado.');
        }
        redirect('servicos');
    }

    public function deleteService(): void
    {
        Security::requireRole(['admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('servicos');
        }
        $id = (int)($_POST['id'] ?? 0);
        $s = $this->serviceModel->findById($id);
        if ($s) {
            $this->serviceModel->delete($id);
            $this->logModel->log('Serviço Excluído', "Serviço '{$s['name']}' excluído do catálogo.");
            flash('success', "Serviço '{$s['name']}' excluído.");
        }
        redirect('servicos');
    }
}
