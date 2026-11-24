<?php
declare(strict_types=1);
$currentUser = \App\Helpers\Security::getUser();
$flash = get_flash();
$currentRoute = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full flex flex-col text-slate-800 antialiased">
    <!-- Barra Superior / Topbar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logotipo e Navegação Principal -->
                <div class="flex items-center">
                    <a href="<?= url() ?>" class="flex items-center gap-2.5 text-primary-600 hover:text-primary-700 font-bold text-xl tracking-tight transition">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary-600 to-indigo-500 flex items-center justify-center text-white shadow-md shadow-primary-500/20">
                            <i class="fas fa-file-invoice text-lg"></i>
                        </div>
                        <span>Req<span class="text-slate-900">Master</span></span>
                    </a>

                    <!-- Menu de Navegação Desktop -->
                    <nav class="hidden md:ml-8 md:flex md:space-x-1">
                        <a href="<?= url() ?>" class="px-3 py-2 rounded-lg text-sm font-medium transition <?= (empty($_GET['route']) || $_GET['route'] === '/') ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fas fa-chart-pie mr-1.5 text-slate-400"></i> Painel
                        </a>
                        <a href="<?= url('requisicoes') ?>" class="px-3 py-2 rounded-lg text-sm font-medium transition <?= (isset($_GET['route']) && str_starts_with($_GET['route'], 'requisicoes')) ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fas fa-clipboard-list mr-1.5 text-slate-400"></i> Requisições
                        </a>
                        <a href="<?= url('fornecedores') ?>" class="px-3 py-2 rounded-lg text-sm font-medium transition <?= (isset($_GET['route']) && str_starts_with($_GET['route'], 'fornecedores')) ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fas fa-building mr-1.5 text-slate-400"></i> Para Quem Solicitar
                        </a>
                        <a href="<?= url('contratos') ?>" class="px-3 py-2 rounded-lg text-sm font-medium transition <?= (isset($_GET['route']) && str_starts_with($_GET['route'], 'contratos')) ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fas fa-file-contract mr-1.5 text-slate-400"></i> Contratos
                        </a>
                        <a href="<?= url('relatorios') ?>" class="px-3 py-2 rounded-lg text-sm font-medium transition <?= (isset($_GET['route']) && str_starts_with($_GET['route'], 'relatorios')) ? 'bg-primary-50 text-primary-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fas fa-file-alt mr-1.5 text-slate-400"></i> Relatórios
                        </a>
                    </nav>
                </div>

                <!-- Botão de Ação Rápida e Perfil -->
                <div class="flex items-center space-x-3">
                    <a href="<?= url('requisicoes/nova') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 transition shadow-sm shadow-primary-600/30">
                        <i class="fas fa-plus text-xs"></i>
                        <span>Nova Requisição</span>
                    </a>

                    <!-- Perfil do Usuário -->
                    <div class="relative flex items-center pl-3 border-l border-slate-200">
                        <div class="text-right mr-3 hidden sm:block">
                            <div class="text-sm font-medium text-slate-800 leading-tight"><?= htmlspecialchars($currentUser['name'] ?? 'Usuário') ?></div>
                            <div class="text-xs text-slate-500 capitalize"><?= htmlspecialchars($currentUser['role'] ?? 'solicitante') ?> &bull; <?= htmlspecialchars($currentUser['department'] ?? 'Geral') ?></div>
                        </div>
                        <div class="w-9 h-9 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-600 text-sm border border-slate-300">
                            <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <a href="<?= url('logout') ?>" title="Sair do sistema" class="ml-3 text-slate-400 hover:text-red-600 transition p-1.5 rounded-lg hover:bg-red-50">
                            <i class="fas fa-sign-out-alt text-base"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Mobile -->
        <div class="md:hidden border-t border-slate-200 bg-white px-4 py-2 flex overflow-x-auto gap-2">
            <a href="<?= url() ?>" class="px-3 py-1.5 text-xs font-medium rounded-md whitespace-nowrap <?= (empty($_GET['route']) || $_GET['route'] === '/') ? 'bg-primary-50 text-primary-700' : 'text-slate-600' ?>">Painel</a>
            <a href="<?= url('requisicoes') ?>" class="px-3 py-1.5 text-xs font-medium rounded-md whitespace-nowrap <?= (isset($_GET['route']) && str_starts_with($_GET['route'], 'requisicoes')) ? 'bg-primary-50 text-primary-700' : 'text-slate-600' ?>">Requisições</a>
            <a href="<?= url('fornecedores') ?>" class="px-3 py-1.5 text-xs font-medium rounded-md whitespace-nowrap <?= (isset($_GET['route']) && str_starts_with($_GET['route'], 'fornecedores')) ? 'bg-primary-50 text-primary-700' : 'text-slate-600' ?>">Fornecedores</a>
            <a href="<?= url('contratos') ?>" class="px-3 py-1.5 text-xs font-medium rounded-md whitespace-nowrap <?= (isset($_GET['route']) && str_starts_with($_GET['route'], 'contratos')) ? 'bg-primary-50 text-primary-700' : 'text-slate-600' ?>">Contratos</a>
            <a href="<?= url('relatorios') ?>" class="px-3 py-1.5 text-xs font-medium rounded-md whitespace-nowrap <?= (isset($_GET['route']) && str_starts_with($_GET['route'], 'relatorios')) ? 'bg-primary-50 text-primary-700' : 'text-slate-600' ?>">Relatórios</a>
        </div>
    </header>

    <!-- Alertas Flash -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
        <?php if ($flash): ?>
            <?php 
                $bgClass = match($flash['type']) {
                    'success' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                    'danger' => 'bg-rose-50 text-rose-800 border-rose-200',
                    'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
                    default => 'bg-blue-50 text-blue-800 border-blue-200'
                };
                $iconClass = match($flash['type']) {
                    'success' => 'fa-check-circle text-emerald-500',
                    'danger' => 'fa-exclamation-circle text-rose-500',
                    'warning' => 'fa-exclamation-triangle text-amber-500',
                    default => 'fa-info-circle text-blue-500'
                };
            ?>
            <div class="flex items-center p-4 rounded-xl border <?= $bgClass ?> shadow-sm mb-4 transition-all">
                <i class="fas <?= $iconClass ?> text-lg mr-3"></i>
                <div class="text-sm font-medium flex-1"><?= htmlspecialchars($flash['message']) ?></div>
                <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 ml-3">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Conteúdo Principal -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
