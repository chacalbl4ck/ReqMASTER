<?php
declare(strict_types=1);

// ========================================================
// REQMASTER - FRONT CONTROLLER & ROTEADOR
// Compatível com PHP 8.5+ e MySQL
// ========================================================

// Carrega configurações globais e sessão
require_once __DIR__ . '/../config/config.php';

// Se o banco ainda não foi configurado e a pasta install existe, redireciona para o setup
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
    
    // Tratamento especial para Config
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
// Suporta tanto parâmetro $_GET['route'] quanto REQUEST_URI
$route = '';
if (isset($_GET['route'])) {
    $route = trim($_GET['route'], '/');
} else {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    
    // Remove o caminho base da pasta onde o projeto está instalado
    if ($scriptName !== '/' && $scriptName !== '\\' && str_starts_with($uri, $scriptName)) {
        $uri = substr($uri, strlen($scriptName));
    }
    $route = trim($uri, '/');
}

// Se não estiver configurado e não estiver acessando /install, redireciona para o instalador
if (!file_exists($dbConfigFile) && is_dir($installDir) && $route !== 'install') {
    header("Location: " . url('install'));
    exit;
}

// Mensagem após apagar a pasta de instalação
if (isset($_GET['msg']) && $_GET['msg'] === 'install_deleted') {
    flash('success', 'Pasta de instalação removida com sucesso para a segurança do sistema!');
}

// Roteamento
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\RequisitionController;
use App\Controllers\SupplierController;
use App\Controllers\ContractController;
use App\Controllers\ReportController;

match ($route) {
    '', 'dashboard' => (new DashboardController())->index(),
    'login' => (new AuthController())->login(),
    'logout' => (new AuthController())->logout(),

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
    'requisicoes/status' => (new RequisitionController())->changeStatus(),
    'requisicoes/imprimir' => (new RequisitionController())->print(),

    // Fornecedores / Para Quem Solicita
    'fornecedores' => (new SupplierController())->index(),
    'fornecedores/novo' => (new SupplierController())->create(),
    'fornecedores/salvar' => (new SupplierController())->store(),
    'fornecedores/ver' => (new SupplierController())->show(),
    'fornecedores/editar' => (new SupplierController())->edit(),
    'fornecedores/atualizar' => (new SupplierController())->update(),
    'fornecedores/excluir' => (new SupplierController())->delete(),

    // Contratos
    'contratos' => (new ContractController())->index(),
    'contratos/novo' => (new ContractController())->create(),
    'contratos/salvar' => (new ContractController())->store(),
    'contratos/ver' => (new ContractController())->show(),
    'contratos/editar' => (new ContractController())->edit(),
    'contratos/atualizar' => (new ContractController())->update(),
    'contratos/api-por-fornecedor' => (new ContractController())->apiBySupplier(),

    // Relatórios
    'relatorios' => (new ReportController())->index(),
    'relatorios/exportar-csv' => (new ReportController())->exportCsv(),

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
