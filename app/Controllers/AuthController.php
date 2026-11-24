<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Security;
use App\Models\Setting;
use App\Models\User;
use App\Services\TotpService;

class AuthController
{
    private User $userModel;
    private TotpService $totpService;

    public function __construct()
    {
        $this->userModel = new User();
        $this->totpService = new TotpService();
    }

    public function login(): void
    {
        if (Security::isLoggedIn()) {
            redirect('');
        }

        $settingModel = new Setting();
        $settings = $settingModel->getAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('danger', 'Token CSRF inválido.');
                redirect('login');
            }

            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            // Proteção contra força bruta por IP e e-mail
            $waitMinutes = Security::isRateLimited('login', $email);
            if ($waitMinutes !== null) {
                flash('danger', "Muitas tentativas de acesso malsucedidas. Tente novamente em {$waitMinutes} minuto(s).");
                redirect('login');
            }

            if (empty($email) || empty($password)) {
                flash('danger', 'Preencha o e-mail e a senha.');
                redirect('login');
            }

            // Verificação Cloudflare Turnstile (se ativado)
            $turnstileEnabled = ($settings['turnstile_enabled'] ?? '0') === '1';
            $turnstileSecretKey = $settings['turnstile_secret_key'] ?? '';

            if ($turnstileEnabled && !empty($turnstileSecretKey)) {
                $token = $_POST['cf-turnstile-response'] ?? '';
                if (empty($token)) {
                    flash('danger', 'Por favor, complete a verificação anti-robô (Cloudflare Turnstile).');
                    redirect('login');
                }

                $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
                $postData = http_build_query([
                    'secret'   => $turnstileSecretKey,
                    'response' => $token,
                    'remoteip' => Security::getClientIp()
                ]);

                $opts = [
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                        'content' => $postData,
                        'timeout' => 5
                    ]
                ];
                $ctx = stream_context_create($opts);
                $result = @file_get_contents($verifyUrl, false, $ctx);
                $resData = $result ? json_decode($result, true) : null;

                if (!$resData || empty($resData['success'])) {
                    flash('danger', 'Falha na verificação de segurança anti-robô. Tente novamente.');
                    redirect('login');
                }
            }

            $user = $this->userModel->findByEmail($email);

            if ($user && Security::verifyPassword($password, $user['password'], (int)$user['id'])) {
                if (empty($user['active'])) {
                    flash('danger', 'Esta conta de usuário está inativa ou bloqueada. Contate o administrador.');
                    redirect('login');
                }

                Security::clearAttempts('login', $email);

                // Se for administrador com 2FA TOTP habilitado, exige verificação em 2 etapas
                if ($user['role'] === 'admin' && !empty($user['totp_enabled']) && !empty($user['totp_secret'])) {
                    session_regenerate_id(true);
                    $_SESSION['pending_2fa'] = [
                        'user_id'    => (int)$user['id'],
                        'expires_at' => time() + 300, // 5 minutos
                    ];
                    redirect('2fa');
                }

                $this->completeUserSession($user);
                flash('success', "Bem-vindo(a), {$user['name']}!");
                redirect('');
            } else {
                Security::recordFailedAttempt('login', $email, 5, 15);
                flash('danger', 'E-mail ou senha incorretos.');
                redirect('login');
            }
        }

        $turnstileEnabled = ($settings['turnstile_enabled'] ?? '0') === '1';
        $turnstileSiteKey = $settings['turnstile_site_key'] ?? '';
        $companyLogo = $settings['company_logo'] ?? '';
        $companyName = $settings['company_name'] ?? 'ReqMaster';

        require __DIR__ . '/../../views/auth/login.php';
    }

    public function verify2fa(): void
    {
        if (Security::isLoggedIn()) {
            redirect('');
        }

        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!$pending || empty($pending['user_id']) || ($pending['expires_at'] ?? 0) < time()) {
            unset($_SESSION['pending_2fa']);
            flash('warning', 'Sessão de verificação em 2 etapas expirada. Faça login novamente.');
            redirect('login');
        }

        $user = $this->userModel->findById((int)$pending['user_id']);
        if (!$user || empty($user['active']) || empty($user['totp_enabled']) || empty($user['totp_secret'])) {
            unset($_SESSION['pending_2fa']);
            redirect('login');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('danger', 'Token CSRF inválido.');
                redirect('2fa');
            }

            $waitMinutes = Security::isRateLimited('totp_2fa', (string)$user['id']);
            if ($waitMinutes !== null) {
                flash('danger', "Muitas tentativas incorretas de código 2FA. Aguarde {$waitMinutes} minuto(s).");
                redirect('2fa');
            }

            $code = trim($_POST['totp_code'] ?? '');
            $isValid = false;

            if ($this->totpService->verifyCode((string)$user['totp_secret'], $code, 1)) {
                $isValid = true;
            } elseif (!empty($user['totp_recovery_codes'])) {
                // Verifica se informou um código de recuperação de uso único
                $recoveryList = json_decode((string)$user['totp_recovery_codes'], true);
                if (is_array($recoveryList)) {
                    $normalized = strtoupper($code);
                    $idx = array_search($normalized, $recoveryList, true);
                    if ($idx !== false) {
                        unset($recoveryList[$idx]);
                        $db = Database::getConnection();
                        $stmt = $db->prepare("UPDATE users SET totp_recovery_codes = :rc WHERE id = :id");
                        $stmt->execute([
                            'rc' => json_encode(array_values($recoveryList)),
                            'id' => $user['id']
                        ]);
                        $isValid = true;
                    }
                }
            }

            if ($isValid) {
                Security::clearAttempts('totp_2fa', (string)$user['id']);
                unset($_SESSION['pending_2fa']);
                $this->completeUserSession($user);
                flash('success', "Autenticação 2FA confirmada! Bem-vindo(a), {$user['name']}.");
                redirect('');
            } else {
                Security::recordFailedAttempt('totp_2fa', (string)$user['id'], 5, 15);
                flash('danger', 'Código de autenticação 2FA inválido ou expirado.');
                redirect('2fa');
            }
        }

        $settingModel = new Setting();
        $settings = $settingModel->getAll();
        require __DIR__ . '/../../views/auth/2fa.php';
    }

    private function completeUserSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user'] = [
            'id'         => (int)$user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'department' => $user['department'],
            'avatar'     => $user['avatar'] ?? null,
        ];
    }

    public function profile(): void
    {
        Security::requireLogin();
        $currentUser = Security::getUser();
        $user = $this->userModel->findById((int)$currentUser['id']);

        // Se for admin e ainda não tiver 2FA ativo, prepara um segredo pendente em sessão para exibição do QR Code
        $pendingTotpSecret = null;
        $pendingTotpUri = null;
        if (($user['role'] ?? '') === 'admin' && empty($user['totp_enabled'])) {
            if (empty($_SESSION['setup_totp_secret'])) {
                $_SESSION['setup_totp_secret'] = $this->totpService->generateSecret();
            }
            $pendingTotpSecret = $_SESSION['setup_totp_secret'];
            $settingModel = new Setting();
            $companyName = $settingModel->get('company_name', 'ReqMaster') ?: 'ReqMaster';
            $pendingTotpUri = $this->totpService->getProvisioningUri($pendingTotpSecret, (string)$user['email'], $companyName . ' (ReqMaster)');
        }

        $newRecoveryCodes = $_SESSION['fresh_recovery_codes'] ?? null;
        unset($_SESSION['fresh_recovery_codes']);

        require __DIR__ . '/../../views/auth/profile.php';
    }

    public function enableTotp(): void
    {
        Security::requireRole(['admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('perfil');
        }

        $currentUser = Security::getUser();
        $secret = $_SESSION['setup_totp_secret'] ?? '';
        $code = trim($_POST['totp_code'] ?? '');

        if (empty($secret) || empty($code)) {
            flash('danger', 'Informe o código de 6 dígitos gerado pelo seu aplicativo autenticador.');
            redirect('perfil');
        }

        if (!$this->totpService->verifyCode($secret, $code, 1)) {
            flash('danger', 'Código TOTP incorreto. Verifique o horário do dispositivo e tente novamente.');
            redirect('perfil');
        }

        $recoveryCodes = $this->totpService->generateRecoveryCodes(6);
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE users
            SET totp_secret = :sec, totp_enabled = 1, totp_recovery_codes = :rc
            WHERE id = :id
        ");
        $stmt->execute([
            'sec' => $secret,
            'rc'  => json_encode($recoveryCodes),
            'id'  => (int)$currentUser['id'],
        ]);

        unset($_SESSION['setup_totp_secret']);
        $_SESSION['fresh_recovery_codes'] = $recoveryCodes;
        flash('success', 'Autenticação em 2 Etapas (TOTP) ativada com sucesso! Guarde seus códigos de recuperação.');
        redirect('perfil');
    }

    public function disableTotp(): void
    {
        Security::requireRole(['admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('perfil');
        }

        $currentUser = Security::getUser();
        $user = $this->userModel->findById((int)$currentUser['id']);
        $confirmPwd = $_POST['current_password'] ?? '';

        if (!$user || !Security::verifyPassword($confirmPwd, $user['password'], (int)$user['id'])) {
            flash('danger', 'Senha atual incorreta. Não foi possível desativar o 2FA.');
            redirect('perfil');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_recovery_codes = NULL WHERE id = :id");
        $stmt->execute(['id' => (int)$user['id']]);

        flash('warning', 'Autenticação em 2 Etapas (TOTP) desativada na sua conta.');
        redirect('perfil');
    }

    public function updateProfile(): void
    {
        Security::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('perfil');
        }

        $currentUser = Security::getUser();
        $userId = (int)$currentUser['id'];

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Nome e e-mail válido são obrigatórios.');
            redirect('perfil');
        }

        if (!empty($password)) {
            if ($password !== $passwordConfirm) {
                flash('danger', 'A confirmação da nova senha não confere.');
                redirect('perfil');
            }
            $pwdCheck = Security::checkPasswordStrength($password);
            if (!$pwdCheck['strong']) {
                flash('danger', 'A nova senha precisa ser forte (' . implode(', ', $pwdCheck['errors']) . ').');
                redirect('perfil');
            }
        }

        $existing = $this->userModel->findByEmail($email);
        if ($existing && (int)$existing['id'] !== $userId) {
            flash('danger', 'Este e-mail já está sendo utilizado por outro usuário.');
            redirect('perfil');
        }

        $data = [
            'name'  => $name,
            'email' => $email,
            'phone' => $phone
        ];

        if (!empty($password)) {
            $data['password'] = $password;
        }

        // Upload seguro de Foto de Perfil com verificação de Magic Bytes
        if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $check = Security::validateUploadedFile(
                $_FILES['avatar'],
                ['png', 'jpg', 'jpeg', 'webp', 'gif'],
                ['image/'],
                5 * 1024 * 1024
            );
            if (!$check['valid']) {
                flash('danger', $check['message']);
                redirect('perfil');
            }

            $avatarDir = __DIR__ . '/../../public/uploads/avatars';
            if (!is_dir($avatarDir)) {
                @mkdir($avatarDir, 0755, true);
            }
            $filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $check['ext'];
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $avatarDir . '/' . $filename)) {
                $data['avatar'] = 'uploads/avatars/' . $filename;
            }
        }

        $this->userModel->updateProfile($userId, $data);

        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        if (isset($data['avatar'])) {
            $_SESSION['user']['avatar'] = $data['avatar'];
        }

        flash('success', 'Perfil atualizado com sucesso!');
        redirect('perfil');
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        session_start();
        session_regenerate_id(true);
        flash('info', 'Sessão encerrada com sucesso.');
        redirect('login');
    }
}
