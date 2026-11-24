<?php
declare(strict_types=1);
$currentUser = \App\Helpers\Security::getUser();
$flash = get_flash();
$currentRoute = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

$settingModel = new \App\Models\Setting();
$allSettings = $settingModel->getAll();
$companyLogo = $allSettings['company_logo'] ?? '';
$companyName = $allSettings['company_name'] ?? 'ReqMaster';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= bot_protection_meta($allSettings) ?>
    <title><?= APP_NAME ?></title>
    <!-- Tailwind CSS CDN com plugin forms -->
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
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
    <?= theme_css_vars($allSettings) ?>
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
                        <?php 
                        $logoPhysical = !empty($companyLogo) ? (__DIR__ . '/../../public/' . ltrim($companyLogo, '/')) : '';
                        if (!empty($companyLogo) && file_exists($logoPhysical)): 
                        ?>
                            <img src="<?= asset($companyLogo) ?>" alt="<?= htmlspecialchars($companyName) ?>" class="h-10 max-h-10 max-w-[180px] object-contain">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary-600 to-indigo-500 flex items-center justify-center text-white shadow-md shadow-primary-500/20">
                                <i class="fas fa-file-invoice text-lg"></i>
                            </div>
                            <span>Req<span class="text-slate-900">Master</span></span>
                        <?php endif; ?>
                    </a>

                    <!-- Menu de Navegação Desktop -->
                    <nav class="hidden md:ml-6 md:flex md:items-center md:space-x-1 lg:space-x-1.5">
                        <a href="<?= url() ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs xl:text-sm whitespace-nowrap transition duration-150 <?= is_active('') ? 'bg-indigo-600 text-white font-semibold shadow-sm shadow-indigo-600/25' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100 font-medium' ?>">
                            <i class="fas fa-chart-pie text-sm <?= is_active('') ? 'text-white' : 'text-slate-400' ?>"></i>
                            <span>Painel</span>
                        </a>
                        <a href="<?= url('requisicoes') ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs xl:text-sm whitespace-nowrap transition duration-150 <?= is_active('requisicoes') ? 'bg-indigo-600 text-white font-semibold shadow-sm shadow-indigo-600/25' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100 font-medium' ?>">
                            <i class="fas fa-clipboard-list text-sm <?= is_active('requisicoes') ? 'text-white' : 'text-slate-400' ?>"></i>
                            <span>Requisições</span>
                        </a>
                        <a href="<?= url('fornecedores') ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs xl:text-sm whitespace-nowrap transition duration-150 <?= is_active('fornecedores') ? 'bg-indigo-600 text-white font-semibold shadow-sm shadow-indigo-600/25' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100 font-medium' ?>" title="Fornecedores e Prestadores">
                            <i class="fas fa-building text-sm <?= is_active('fornecedores') ? 'text-white' : 'text-slate-400' ?>"></i>
                            <span>Para Quem Solicitar</span>
                        </a>
                        <a href="<?= url('contratos') ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs xl:text-sm whitespace-nowrap transition duration-150 <?= is_active('contratos') ? 'bg-indigo-600 text-white font-semibold shadow-sm shadow-indigo-600/25' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100 font-medium' ?>">
                            <i class="fas fa-file-contract text-sm <?= is_active('contratos') ? 'text-white' : 'text-slate-400' ?>"></i>
                            <span>Contratos</span>
                        </a>
                        <a href="<?= url('relatorios') ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs xl:text-sm whitespace-nowrap transition duration-150 <?= is_active('relatorios') ? 'bg-indigo-600 text-white font-semibold shadow-sm shadow-indigo-600/25' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100 font-medium' ?>">
                            <i class="fas fa-file-alt text-sm <?= is_active('relatorios') ? 'text-white' : 'text-slate-400' ?>"></i>
                            <span>Relatórios</span>
                        </a>
                        <?php if (\App\Helpers\Security::hasRole(['admin'])): ?>
                            <a href="<?= url('configuracoes') ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs xl:text-sm whitespace-nowrap transition duration-150 <?= is_active('configuracoes') ? 'bg-indigo-600 text-white font-semibold shadow-sm shadow-indigo-600/25' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100 font-medium' ?>">
                                <i class="fas fa-cog text-sm <?= is_active('configuracoes') ? 'text-white' : 'text-slate-400' ?>"></i>
                                <span>Configurações</span>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>

                <!-- Botão de Ação Rápida e Perfil -->
                <div class="flex items-center space-x-2.5">
                    <a href="<?= url('requisicoes/nova') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-sm shadow-indigo-600/30 whitespace-nowrap">
                        <i class="fas fa-plus text-xs"></i>
                        <span>Nova Requisição</span>
                    </a>

                    <!-- Perfil do Usuário -->
                    <div class="relative flex items-center pl-3 border-l border-slate-200">
                        <a href="<?= url('perfil') ?>" title="Clique para editar seu perfil" class="flex items-center group transition">
                            <div class="text-right mr-2.5 hidden sm:block">
                                <div class="text-xs sm:text-sm font-semibold text-slate-800 group-hover:text-indigo-600 leading-tight transition"><?= htmlspecialchars($currentUser['name'] ?? 'Usuário') ?></div>
                                <div class="text-[11px] text-slate-500 capitalize leading-tight"><?= htmlspecialchars($currentUser['role'] ?? 'solicitante') ?> &bull; <?= htmlspecialchars($currentUser['department'] ?? 'Geral') ?></div>
                            </div>
                            <?php if (!empty($currentUser['avatar']) && file_exists(__DIR__ . '/../../public/' . $currentUser['avatar'])): ?>
                                <img src="<?= asset($currentUser['avatar']) ?>" alt="Avatar" class="w-9 h-9 rounded-full object-cover border-2 border-indigo-200 group-hover:border-indigo-500 transition shadow-sm">
                            <?php else: ?>
                                <div class="w-9 h-9 rounded-full bg-indigo-50 group-hover:bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm border border-indigo-200 transition">
                                    <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </a>
                        <a href="<?= url('logout') ?>" title="Sair do sistema" class="ml-2.5 text-slate-400 hover:text-red-600 transition p-1.5 rounded-xl hover:bg-red-50">
                            <i class="fas fa-sign-out-alt text-base"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Mobile -->
        <div class="md:hidden border-t border-slate-200 bg-white px-3 py-2 flex overflow-x-auto gap-1.5 scrollbar-none">
            <a href="<?= url() ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg whitespace-nowrap transition <?= is_active('') ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">Painel</a>
            <a href="<?= url('requisicoes') ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg whitespace-nowrap transition <?= is_active('requisicoes') ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">Requisições</a>
            <a href="<?= url('fornecedores') ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg whitespace-nowrap transition <?= is_active('fornecedores') ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">Para Quem Solicitar</a>
            <a href="<?= url('contratos') ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg whitespace-nowrap transition <?= is_active('contratos') ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">Contratos</a>
            <a href="<?= url('relatorios') ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg whitespace-nowrap transition <?= is_active('relatorios') ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">Relatórios</a>
            <?php if (\App\Helpers\Security::hasRole(['admin'])): ?>
                <a href="<?= url('configuracoes') ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg whitespace-nowrap transition <?= is_active('configuracoes') ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">Configurações</a>
            <?php endif; ?>
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
