<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\MailService;
use App\Services\UpdateService;
use Exception;

class SettingController
{
    private Setting $settingModel;
    private User $userModel;
    private MailService $mailService;
    private LicenseService $licenseService;
    private UpdateService $updateService;

    public function __construct()
    {
        Security::requireRole(['admin']);
        $this->settingModel = new Setting();
        $this->userModel = new User();
        $this->mailService = new MailService();
        $this->licenseService = new LicenseService($this->settingModel);
        $this->updateService = new UpdateService();
    }

    public function index(): void
    {
        $settings = $this->settingModel->getAll();
        $users = $this->userModel->all();
        $tab = $_GET['tab'] ?? 'empresa';

        $uploadDir = $settings['upload_dir'] ?? 'public/uploads/attachments';
        $fullUploadPath = realpath(__DIR__ . '/../../' . $uploadDir) ?: (__DIR__ . '/../../' . $uploadDir);
        $isUploadWritable = is_dir($fullUploadPath) && is_writable($fullUploadPath);

        $licenseSummary = $this->licenseService->getSummary();
        $updateLocalStatus = $this->updateService->getLocalStatus();

        require __DIR__ . '/../../views/settings/index.php';
    }

    public function saveCompany(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('configuracoes?tab=empresa');
        }

        $data = [
            'company_name'       => trim($_POST['company_name'] ?? ''),
            'company_trade_name' => trim($_POST['company_trade_name'] ?? ''),
            'company_cnpj'       => trim($_POST['company_cnpj'] ?? ''),
            'company_email'      => trim($_POST['company_email'] ?? ''),
            'company_phone'      => trim($_POST['company_phone'] ?? ''),
            'company_address'    => trim($_POST['company_address'] ?? ''),
        ];

        // Processa upload seguro de logotipo
        if (!empty($_FILES['company_logo']['name']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $check = Security::validateUploadedFile(
                $_FILES['company_logo'],
                ['png', 'jpg', 'jpeg', 'webp'],
                ['image/'],
                5 * 1024 * 1024
            );
            if (!$check['valid']) {
                flash('danger', $check['message']);
                redirect('configuracoes?tab=empresa');
            }

            $logoDir = __DIR__ . '/../../public/uploads/logos';
            if (!is_dir($logoDir)) {
                @mkdir($logoDir, 0755, true);
            }

            $fileName = 'logo_' . bin2hex(random_bytes(8)) . '.' . $check['ext'];
            $destination = $logoDir . '/' . $fileName;

            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $destination)) {
                $data['company_logo'] = 'uploads/logos/' . $fileName;
            }
        }

        $this->settingModel->setMany($data);
        flash('success', 'Dados da empresa e logotipo atualizados com sucesso!');
        redirect('configuracoes?tab=empresa');
    }

    public function saveMail(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('configuracoes?tab=email');
        }

        $data = [
            'smtp_host'           => trim($_POST['smtp_host'] ?? ''),
            'smtp_port'           => trim($_POST['smtp_port'] ?? '587'),
            'smtp_user'           => trim($_POST['smtp_user'] ?? ''),
            'smtp_pass'           => $_POST['smtp_pass'] ?? '',
            'smtp_secure'         => trim($_POST['smtp_secure'] ?? 'tls'),
            'smtp_from_name'      => trim($_POST['smtp_from_name'] ?? ''),
            'smtp_from_email'     => trim($_POST['smtp_from_email'] ?? ''),
            'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
        ];

        $this->settingModel->setMany($data);
        flash('success', 'Configurações de e-mail e notificações salvas com sucesso!');
        redirect('configuracoes?tab=email');
    }

    public function testMail(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('configuracoes?tab=email');
        }

        $currentUser = Security::getUser();
        $targetEmail = trim($_POST['test_email'] ?? ($currentUser['email'] ?? ''));

        if (empty($targetEmail)) {
            flash('danger', 'Informe um e-mail válido para o teste.');
            redirect('configuracoes?tab=email');
        }

        try {
            $companyName = $this->settingModel->get('company_name', 'ReqMaster');
            $subject = "Teste de Conexão SMTP - {$companyName}";
            $body = "
                <div style='font-family:sans-serif;max-width:550px;padding:20px;border:1px solid #e2e8f0;border-radius:10px;'>
                    <h2 style='color:#16a34a;margin-top:0;'>✅ Conexão SMTP Bem-Sucedida!</h2>
                    <p>Este é um e-mail de teste enviado pelo sistema <strong>ReqMaster</strong>.</p>
                    <p>Suas configurações de envio estão funcionando perfeitamente.</p>
                    <hr style='border:0;border-top:1px solid #e2e8f0;margin:15px 0;'>
                    <span style='font-size:12px;color:#64748b;'>Data e Hora do envio: " . date('d/m/Y H:i:s') . "</span>
                </div>
            ";

            $sent = $this->mailService->send($targetEmail, $currentUser['name'] ?? 'Administrador', $subject, $body);
            if ($sent) {
                flash('success', "E-mail de teste enviado com sucesso para {$targetEmail}!");
            } else {
                flash('danger', "O servidor não confirmou o envio para {$targetEmail}. Verifique os parâmetros.");
            }
        } catch (Exception $e) {
            flash('danger', "Erro no teste de SMTP: " . $e->getMessage());
        }

        redirect('configuracoes?tab=email');
    }

    public function saveStorage(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('configuracoes?tab=anexos');
        }

        $dir = trim($_POST['upload_dir'] ?? 'public/uploads/attachments');
        $fullPath = __DIR__ . '/../../' . $dir;

        if (!is_dir($fullPath)) {
            @mkdir($fullPath, 0777, true);
        }

        $this->settingModel->set('upload_dir', $dir);
        flash('success', 'Diretório de armazenamento de anexos atualizado!');
        redirect('configuracoes?tab=anexos');
    }

    public function saveUser(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('configuracoes?tab=usuarios');
        }

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'solicitante';
        $department = trim($_POST['department'] ?? 'Geral');
        $phone = trim($_POST['phone'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name) || empty($email)) {
            flash('danger', 'Nome e e-mail são obrigatórios.');
            redirect('configuracoes?tab=usuarios');
        }

        $db = \App\Config\Database::getConnection();

        if ($id > 0) {
            // Edição
            $sql = "UPDATE users SET name = :name, email = :email, role = :role, department = :department, phone = :phone, active = :active";
            $params = [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'department' => $department,
                'phone' => $phone,
                'active' => $active,
                'id' => $id
            ];

            if (!empty($password)) {
                $sql .= ", password = :pwd";
                $params['pwd'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            flash('success', "Usuário {$name} atualizado com sucesso!");
        } else {
            // Criação
            if (empty($password)) {
                flash('danger', 'Defina uma senha para o novo usuário.');
                redirect('configuracoes?tab=usuarios');
            }

            try {
                $this->userModel->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'role' => $role,
                    'department' => $department,
                    'phone' => $phone,
                    'active' => $active
                ]);
                flash('success', "Novo usuário {$name} cadastrado com sucesso!");
            } catch (\PDOException $e) {
                flash('danger', 'E-mail já cadastrado no sistema.');
            }
        }

        redirect('configuracoes?tab=usuarios');
    }

    public function deleteUser(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('configuracoes?tab=usuarios');
        }

        $id = (int)($_POST['id'] ?? 0);
        $currentUser = Security::getUser();

        if ($id === (int)$currentUser['id']) {
            flash('danger', 'Você não pode excluir sua própria conta de usuário.');
            redirect('configuracoes?tab=usuarios');
        }

        $db = \App\Config\Database::getConnection();
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $id]);
            flash('success', 'Usuário removido com sucesso.');
        } catch (Exception $e) {
            flash('danger', 'Não foi possível excluir o usuário (pode haver requisições vinculadas).');
        }

        redirect('configuracoes?tab=usuarios');
    }

    public function toggleUserStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('configuracoes?tab=usuarios');
        }

        $id = (int)($_POST['id'] ?? 0);
        $currentUser = Security::getUser();

        if ($id === (int)$currentUser['id']) {
            flash('danger', 'Você não pode bloquear seu próprio usuário.');
            redirect('configuracoes?tab=usuarios');
        }

        $this->userModel->toggleStatus($id);
        flash('success', 'Status do usuário alterado com sucesso.');
        redirect('configuracoes?tab=usuarios');
    }

    public function resetUserPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('configuracoes?tab=usuarios');
        }

        $id = (int)($_POST['id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';

        if (empty($newPassword) || strlen($newPassword) < 4) {
            flash('danger', 'A nova senha deve ter no mínimo 4 caracteres.');
            redirect('configuracoes?tab=usuarios');
        }

        $this->userModel->resetPassword($id, $newPassword);
        flash('success', 'Senha do usuário redefinida com sucesso!');
        redirect('configuracoes?tab=usuarios');
    }

    public function saveColors(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('configuracoes?tab=cores');
        }

        $this->settingModel->setMany([
            'theme_primary_color'   => safe_hex_color($_POST['theme_primary_color'] ?? '#4f46e5', '#4f46e5'),
            'theme_secondary_color' => safe_hex_color($_POST['theme_secondary_color'] ?? '#4338ca', '#4338ca'),
            'theme_accent_color'    => safe_hex_color($_POST['theme_accent_color'] ?? '#6366f1', '#6366f1'),
            'req_primary_color'     => safe_hex_color($_POST['req_primary_color'] ?? '#4338ca', '#4338ca'),
            'req_bg_tint'           => safe_hex_color($_POST['req_bg_tint'] ?? '#eef2ff', '#eef2ff'),
            'req_border_color'      => safe_hex_color($_POST['req_border_color'] ?? '#cbd5e1', '#cbd5e1'),
        ]);

        flash('success', 'Esquema de cores do site e das requisições atualizado com sucesso!');
        redirect('configuracoes?tab=cores');
    }

    public function saveSecurity(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('configuracoes?tab=seguranca');
        }

        $isWebHosted = isset($_POST['is_web_hosted']) ? '1' : '0';

        $this->settingModel->setMany([
            'turnstile_enabled'    => isset($_POST['turnstile_enabled']) ? '1' : '0',
            'turnstile_site_key'   => trim($_POST['turnstile_site_key'] ?? ''),
            'turnstile_secret_key' => trim($_POST['turnstile_secret_key'] ?? ''),
            'is_web_hosted'        => $isWebHosted,
            'enable_qrcode_link'   => ($isWebHosted === '1' && isset($_POST['enable_qrcode_link'])) ? '1' : '0',
            'block_search_bots'    => ($isWebHosted === '1' && isset($_POST['block_search_bots'])) ? '1' : '0',
        ]);

        flash('success', 'Configurações de segurança, hospedagem Web e proteção salvas com sucesso!');
        redirect('configuracoes?tab=seguranca');
    }

    public function saveLicense(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('configuracoes?tab=licenca');
        }

        $key = trim($_POST['license_key'] ?? '');
        $res = $this->licenseService->activateKey($key);

        if ($res['success']) {
            flash('success', $res['message']);
        } else {
            flash('danger', $res['message']);
        }

        redirect('configuracoes?tab=licenca');
    }

    public function checkGithub(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $res = $this->updateService->checkUpdates();
        echo json_encode($res);
        exit;
    }

    public function applyUpdates(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            echo json_encode(['success' => false, 'message' => 'Requisição inválida ou token expirado.']);
            exit;
        }

        $res = $this->updateService->applyUpdates();
        echo json_encode($res);
        exit;
    }
}
