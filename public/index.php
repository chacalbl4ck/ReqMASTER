<?php
declare(strict_types=1);

// ========================================================
// REQMASTER - FRONT CONTROLLER & ROTEADOR
// Compatível com PHP 8.5+ e MySQL
// ========================================================

require_once __DIR__ . '/../config/config.php';

$dbConfigFile = __DIR__ . '/../config/db_config.php';
$installDir = __DIR__ . '/../install';

// Autoloader PSR-4 Simplificado
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));

    if (str_starts_with($relativeClass, 'Config\\')) {
        $file = __DIR__ . '/../config/' . str_replace('\\', '/', substr($relativeClass, 7)) . '.php';
    } else {
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    }
});

// Resolução de Rota
$route = '';
if (isset($_GET['route'])) {
    $route = trim($_GET['route'], '/');
} else {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');

    if ($scriptName !== '/' && $scriptName !== '\\' && str_starts_with($uri, $scriptName)) {
        $uri = substr($uri, strlen($scriptName));
    }
    $route = trim($uri, '/');
}

$GLOBALS['current_route'] = $route;

// Se não estiver configurado e não estiver acessando /install, redireciona para o instalador
if (!file_exists($dbConfigFile) && is_dir($installDir) && $route !== 'install') {
    header("Location: " . url('install'));
    exit;
}

use App\Helpers\Security;
use App\Models\Setting;
use App\Services\LicenseService;
use App\Services\UpdateService;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\RequisitionController;
use App\Controllers\SupplierController;
use App\Controllers\ContractController;
use App\Controllers\ReportController;
use App\Controllers\SettingController;
use App\Controllers\LicenseController;
use App\Controllers\CostCenterController;
use App\Controllers\LogController;
use App\Controllers\CatalogController;

// Cabeçalhos globais de segurança + verificação de bloqueio de indexação por bots
$blockBots = false;
if (file_exists($dbConfigFile)) {
    try {
        $settingCheck = new Setting();
        $blockBots = $settingCheck->get('is_web_hosted', '0') === '1'
                  && $settingCheck->get('block_search_bots', '0') === '1';
    } catch (\Throwable $e) {
        $blockBots = false;
    }
}
Security::sendSecurityHeaders($blockBots);

// Rota dinâmica robots.txt
if ($route === 'robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    if ($blockBots) {
        echo "User-agent: *\nDisallow: /\n";
    } else {
        echo "User-agent: *\nAllow: /\n";
    }
    exit;
}

// Verificação de Modo Manutenção durante atualização do sistema
$updateService = new UpdateService();
if ($updateService->isMaintenanceMode() && $route !== 'configuracoes/atualizacoes/aplicar') {
    http_response_code(503);
    header('Retry-After: 30');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="robots" content="noindex,nofollow"><title>Sistema em Manutenção</title></head>'
       . '<body style="font-family:sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;text-align:center;">'
       . '<div style="max-width:440px;background:#1e293b;padding:36px;border-radius:20px;border:1px solid #334155;">'
       . '<h1 style="margin-top:0;font-size:22px;">Sistema em Atualização</h1>'
       . '<p style="color:#94a3b8;font-size:14px;line-height:1.5;">Estamos aplicando uma atualização oficial de segurança e recursos. Este processo leva apenas alguns segundos. Tente recarregar em breve.</p>'
       . '</div></body></html>';
    exit;
}

// Verificação Antifraude e Periódica (5 minutos) do Sistema de Licenciamento
if (file_exists($dbConfigFile)) {
    $allowedWhenLocked = ['licenciamento', 'licenciamento/ativar', 'login', 'logout', '2fa', 'install'];
    if (!in_array($route, $allowedWhenLocked, true)) {
        try {
            $licenseService = new LicenseService($settingCheck ?? null);
            $licCheck = $licenseService->enforce();
            if (empty($licCheck['valid'])) {
                if (!empty($licCheck['reason'])) {
                    flash('warning', $licCheck['reason']);
                }
                redirect('licenciamento');
            }
        } catch (\Throwable $e) {
            redirect('licenciamento');
        }
    }
}

// Mensagem após apagar a pasta de instalação
if (isset($_GET['msg']) && $_GET['msg'] === 'install_deleted') {
    flash('success', 'Pasta de instalação removida com sucesso para a segurança do sistema!');
}

match ($route) {
    '', 'dashboard' => (new DashboardController())->index(),
    'login' => (new AuthController())->login(),
    '2fa' => (new AuthController())->verify2fa(),
    'logout' => (new AuthController())->logout(),
    'perfil' => (new AuthController())->profile(),
    'perfil/atualizar' => (new AuthController())->updateProfile(),
    'perfil/2fa/ativar' => (new AuthController())->enableTotp(),
    'perfil/2fa/desativar' => (new AuthController())->disableTotp(),

    // Licenciamento do Sistema
    'licenciamento' => (new LicenseController())->index(),
    'licenciamento/ativar' => (new LicenseController())->activate(),

    // Instalador Automatizado
    'install' => (function() {
        $installer = __DIR__ . '/../install/index.php';
        if (file_exists($installer)) {
            require_once $installer;
        } else {
            flash('info', 'A pasta de instalação já foi removida.');
            redirect('login');
        }
    })(),

    // Requisições
    'requisicoes' => (new RequisitionController())->index(),
    'requisicoes/nova' => (new RequisitionController())->create(),
    'requisicoes/salvar' => (new RequisitionController())->store(),
    'requisicoes/ver' => (new RequisitionController())->show(),
    'requisicoes/publica' => (new RequisitionController())->publicView(),
    'requisicoes/editar' => (new RequisitionController())->edit(),
    'requisicoes/atualizar' => (new RequisitionController())->update(),
    'requisicoes/status' => (new RequisitionController())->changeStatus(),
    'requisicoes/imprimir' => (new RequisitionController())->print(),
    'requisicoes/anexar' => (new RequisitionController())->addAttachment(),
    'requisicoes/anexo/excluir' => (new RequisitionController())->deleteAttachment(),
    'requisicoes/enviar-email' => (new RequisitionController())->sendEmail(),
    'requisicoes/excluir' => (new RequisitionController())->delete(),
    'requisicoes/assinar-certificado' => (new RequisitionController())->signCertificate(),

    // Fornecedores / Para Quem Solicita
    'fornecedores' => (new SupplierController())->index(),
    'fornecedores/novo' => (new SupplierController())->create(),
    'fornecedores/salvar' => (new SupplierController())->store(),
    'fornecedores/ver' => (new SupplierController())->show(),
    'fornecedores/editar' => (new SupplierController())->edit(),
    'fornecedores/atualizar' => (new SupplierController())->update(),
    'fornecedores/toggle-status' => (new SupplierController())->toggleStatus(),
    'fornecedores/excluir' => (new SupplierController())->delete(),
    'fornecedores/consultar-cnpj' => (new SupplierController())->consultarCnpj(),

    // Contratos
    'contratos' => (new ContractController())->index(),
    'contratos/novo' => (new ContractController())->create(),
    'contratos/salvar' => (new ContractController())->store(),
    'contratos/ver' => (new ContractController())->show(),
    'contratos/editar' => (new ContractController())->edit(),
    'contratos/atualizar' => (new ContractController())->update(),
    'contratos/excluir' => (new ContractController())->delete(),
    'contratos/api-por-fornecedor' => (new ContractController())->apiBySupplier(),

    // Catálogo de Produtos & Serviços
    'produtos' => (new CatalogController())->products(),
    'produtos/ver' => (new CatalogController())->showProduct(),
    'produtos/salvar' => (new CatalogController())->saveProduct(),
    'produtos/foto/excluir' => (new CatalogController())->deleteProductPhoto(),
    'produtos/status' => (new CatalogController())->toggleProductStatus(),
    'produtos/excluir' => (new CatalogController())->deleteProduct(),
    'servicos' => (new CatalogController())->services(),
    'servicos/salvar' => (new CatalogController())->saveService(),
    'servicos/status' => (new CatalogController())->toggleServiceStatus(),
    'servicos/excluir' => (new CatalogController())->deleteService(),

    // Configurações Globais
    'configuracoes' => (new SettingController())->index(),
    'configuracoes/empresa' => (new SettingController())->saveCompany(),
    'configuracoes/cores' => (new SettingController())->saveColors(),
    'configuracoes/email' => (new SettingController())->saveMail(),
    'configuracoes/email/teste' => (new SettingController())->testMail(),
    'configuracoes/anexos' => (new SettingController())->saveStorage(),
    'configuracoes/usuarios/salvar' => (new SettingController())->saveUser(),
    'configuracoes/usuarios/excluir' => (new SettingController())->deleteUser(),
    'configuracoes/usuarios/toggle-status' => (new SettingController())->toggleUserStatus(),
    'configuracoes/usuarios/reset-senha' => (new SettingController())->resetUserPassword(),
    'configuracoes/seguranca' => (new SettingController())->saveSecurity(),
    'configuracoes/licenca' => (new SettingController())->saveLicense(),
    'configuracoes/github/verificar' => (new SettingController())->checkGithub(),
    'configuracoes/atualizacoes/aplicar' => (new SettingController())->applyUpdates(),

    // Relatórios
    'relatorios' => (new ReportController())->index(),
    'relatorios/exportar-csv' => (new ReportController())->exportCsv(),
    'relatorios/manutencoes' => (new ReportController())->maintenance(),
    'relatorios/manutencoes-csv' => (new ReportController())->exportMaintenanceCsv(),

    // Centros de Custo
    'centros-de-custo' => (new CostCenterController())->index(),
    'centros-de-custo/salvar' => (new CostCenterController())->store(),
    'centros-de-custo/status' => (new CostCenterController())->toggleStatus(),
    'centros-de-custo/excluir' => (new CostCenterController())->delete(),

    // Logs do Sistema (Administrador)
    'logs' => (new LogController())->index(),
    'logs/exportar-csv' => (new LogController())->exportCsv(),

    // 404
    default => (function() {
        http_response_code(404);
        require __DIR__ . '/../views/layout/header.php';
        echo '<div class="text-center py-16">
            <h1 class="text-6xl font-black text-indigo-600">404</h1>
            <p class="text-lg font-bold text-slate-800 mt-2">Página não encontrada</p>
            <p class="text-xs text-slate-400 mt-1">A rota solicitada não existe ou foi removida.</p>
            <a href="' . url() . '" class="inline-block mt-4 px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-semibold">Voltar ao Painel</a>
        </div>';
        require __DIR__ . '/../views/layout/footer.php';
    })(),
};
