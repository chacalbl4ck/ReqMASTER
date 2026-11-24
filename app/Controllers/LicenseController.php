<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\Setting;
use App\Services\LicenseService;

class LicenseController
{
    private Setting $settingModel;
    private LicenseService $licenseService;

    public function __construct()
    {
        $this->settingModel = new Setting();
        $this->licenseService = new LicenseService($this->settingModel);
    }

    public function index(): void
    {
        $summary = $this->licenseService->getSummary();
        $isLocked = ($summary['lock_mode'] ?? '1') === '1' || ($summary['status'] ?? 'unlicensed') !== 'active';
        $licenseStatus = $summary['status'];
        $licenseKey = $summary['key'];
        $machineToken = $summary['fingerprint'];
        $lastCheck = $summary['last_check'];

        $settings = $this->settingModel->getAll();
        $isAdmin = Security::hasRole(['admin']);

        require __DIR__ . '/../../views/license/index.php';
    }

    public function activate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('licenciamento');
        }

        $ip = Security::getClientIp();
        $waitMin = Security::isRateLimited('license_activate', $ip);
        if ($waitMin !== null) {
            flash('danger', "Muitas tentativas inválidas. Aguarde {$waitMin} minuto(s) para tentar novamente.");
            redirect('licenciamento');
        }

        $key = trim($_POST['license_key'] ?? '');
        if (empty($key)) {
            flash('danger', 'Por favor, informe a chave de licença no formato XXXX-XXXX-XXXX-XXXX.');
            redirect('licenciamento');
        }

        $result = $this->licenseService->activateKey($key);
        if (!$result['success']) {
            Security::recordFailedAttempt('license_activate', $ip, 6, 10);
            flash('danger', $result['message']);
            redirect('licenciamento');
        }

        Security::clearAttempts('license_activate', $ip);
        flash('success', $result['message']);
        redirect('');
    }

    public function toggleLock(): void
    {
        Security::requireRole(['admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('configuracoes?tab=licenca');
        }

        $current = $this->settingModel->get('license_lock_mode', '1');
        if ($current === '0') {
            $this->licenseService->lockManually();
            flash('warning', 'O sistema foi travado! Apenas a tela de licenciamento ficará acessível até nova validação.');
            redirect('licenciamento');
        } else {
            // Para destravar, exige validação ativa na base oficial
            $recheck = $this->licenseService->forceRecheck();
            if ($recheck['success']) {
                flash('success', 'Licença validada no servidor oficial e sistema desbloqueado!');
                redirect('configuracoes?tab=licenca');
            } else {
                flash('danger', $recheck['message']);
                redirect('licenciamento');
            }
        }
    }
}
