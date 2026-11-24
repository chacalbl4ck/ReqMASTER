<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Helpers\Security;

class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function login(): void
    {
        if (Security::isLoggedIn()) {
            redirect('');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('danger', 'Token CSRF inválido.');
                redirect('login');
            }

            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                flash('danger', 'Preencha o e-mail e a senha.');
                redirect('login');
            }

            $user = $this->userModel->findByEmail($email);

            if ($user && Security::verifyPassword($password, $user['password'], (int)$user['id'])) {
                if (empty($user['active'])) {
                    flash('danger', 'Usuário inativo. Contate o administrador.');
                    redirect('login');
                }

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user'] = [
                    'id'         => (int)$user['id'],
                    'name'       => $user['name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                    'department' => $user['department'],
                ];

                flash('success', "Bem-vindo(a), {$user['name']}!");
                redirect('');
            } else {
                flash('danger', 'E-mail ou senha incorretos.');
                redirect('login');
            }
        }

        require __DIR__ . '/../../views/auth/login.php';
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
        flash('info', 'Sessão encerrada com sucesso.');
        redirect('login');
    }
}
