<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\LicenseService;

class LicenseController
{
    private Setting $settingModel;
    private LicenseService $licenseService;
    private ActivityLog $logModel;

    public function __construct()
    {
        $this->settingModel = new Setting();
        $this->licenseService = new LicenseService($this->settingModel);
        $this->logModel = new ActivityLog();
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
            $this->logModel->log('Falha de Licença', "Tentativa de ativação rejeitada para chave '{$key}': " . $result['message']);
            flash('danger', $result['message']);
            redirect('licenciamento');
        }

        Security::clearAttempts('license_activate', $ip);
        $this->logModel->log('Licença Ativada', "Chave '{$key}' validada e ativada com sucesso.");
        flash('success', $result['message']);
        redirect('');
    }
}
