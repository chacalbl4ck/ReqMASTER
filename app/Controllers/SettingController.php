<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\Setting;
use App\Models\User;
use App\Services\MailService;
use Exception;

class SettingController
{
    private Setting $settingModel;
    private User $userModel;
    private MailService $mailService;

    public function __construct()
    {
        Security::requireRole(['admin']);
        $this->settingModel = new Setting();
        $this->userModel = new User();
        $this->mailService = new MailService();
    }

    public function index(): void
    {
        $settings = $this->settingModel->getAll();
        $users = $this->userModel->all();
        $tab = $_GET['tab'] ?? 'empresa';

        $uploadDir = $settings['upload_dir'] ?? 'public/uploads/attachments';
        $fullUploadPath = realpath(__DIR__ . '/../../' . $uploadDir) ?: (__DIR__ . '/../../' . $uploadDir);
        $isUploadWritable = is_dir($fullUploadPath) && is_writable($fullUploadPath);

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

        // Processa upload de logotipo se enviado
        if (!empty($_FILES['company_logo']['name']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['company_logo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

            if (!in_array($ext, $allowed, true)) {
                flash('danger', 'Formato de imagem inválido para o logo. Permitidos: PNG, JPG, JPEG, SVG, WEBP.');
                redirect('configuracoes?tab=empresa');
            }

            $logoDir = __DIR__ . '/../../public/uploads/logos';
            if (!is_dir($logoDir)) {
                @mkdir($logoDir, 0777, true);
            }

            $fileName = 'logo_' . time() . '.' . $ext;
            $destination = $logoDir . '/' . $fileName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
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

    public function checkGithub(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $repo = trim($_GET['repo'] ?? $this->settingModel->get('github_repo', ''));
        if (empty($repo)) {
            echo json_encode([
                'success' => false,
                'message' => 'Nenhum repositório GitHub configurado. Informe no formato "usuario/repositorio".'
            ]);
            exit;
        }

        // Salva o repo nas configurações
        $this->settingModel->set('github_repo', $repo);

        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "User-Agent: ReqMaster-UpdateChecker\r\nAccept: application/vnd.github.v3+json\r\n"
            ]
        ];
        $context = stream_context_create($opts);

        // 1. Tenta buscar a release mais recente
        $releaseUrl = "https://api.github.com/repos/{$repo}/releases/latest";
        $releaseRes = @file_get_contents($releaseUrl, false, $context);

        if ($releaseRes !== false) {
            $releaseData = json_decode($releaseRes, true);
            if (!empty($releaseData['tag_name'])) {
                echo json_encode([
                    'success'       => true,
                    'type'          => 'release',
                    'current_version' => APP_VERSION,
                    'latest_version'  => $releaseData['tag_name'],
                    'name'          => $releaseData['name'] ?? $releaseData['tag_name'],
                    'published_at'  => date('d/m/Y H:i', strtotime($releaseData['published_at'] ?? 'now')),
                    'body'          => $releaseData['body'] ?? 'Sem notas de versão.',
                    'html_url'      => $releaseData['html_url'] ?? "https://github.com/{$repo}"
                ]);
                exit;
            }
        }

        // 2. Se não houver releases, busca o último commit
        $commitUrl = "https://api.github.com/repos/{$repo}/commits?per_page=1";
        $commitRes = @file_get_contents($commitUrl, false, $context);

        if ($commitRes !== false) {
            $commits = json_decode($commitRes, true);
            if (!empty($commits[0])) {
                $lastCommit = $commits[0];
                echo json_encode([
                    'success'         => true,
                    'type'            => 'commit',
                    'current_version' => APP_VERSION,
                    'latest_commit'   => substr($lastCommit['sha'], 0, 7),
                    'message'         => $lastCommit['commit']['message'] ?? '',
                    'author'          => $lastCommit['commit']['author']['name'] ?? '',
                    'date'            => date('d/m/Y H:i', strtotime($lastCommit['commit']['author']['date'] ?? 'now')),
                    'html_url'        => $lastCommit['html_url'] ?? "https://github.com/{$repo}"
                ]);
                exit;
            }
        }

        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível consultar o GitHub para o repositório "' . htmlspecialchars($repo) . '". Verifique se o nome está correto e se o repositório é público.'
        ]);
        exit;
    }
}
