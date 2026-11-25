<?php
declare(strict_types=1);
$flash = get_flash();
$turnstileEnabled = $turnstileEnabled ?? false;
$turnstileSiteKey = $turnstileSiteKey ?? '';
$companyLogo = $companyLogo ?? '';
$companyName = $companyName ?? 'ReqMaster';
$logoPhysical = !empty($companyLogo) ? (__DIR__ . '/../../public/' . ltrim($companyLogo, '/')) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= bot_protection_meta($settings ?? []) ?>
    <?= favicon_tags() ?>
    <title>Acesso Restrito - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/tailwind.min.css') . '?v=' . APP_VERSION ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php if ($turnstileEnabled && !empty($turnstileSiteKey)): ?>
        <!-- Cloudflare Turnstile Script -->
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <?= theme_css_vars($settings ?? []) ?>
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="h-full flex items-center justify-center p-4 bg-slate-950 text-slate-800">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8 sm:p-10 border border-slate-100">
        <!-- Logo / Cabeçalho -->
        <div class="text-center mb-8">
            <?php if (!empty($companyLogo) && file_exists($logoPhysical)): ?>
                <div class="flex items-center justify-center mb-4">
                    <img src="<?= asset($companyLogo) ?>" alt="<?= htmlspecialchars($companyName) ?>" class="max-h-16 max-w-[220px] object-contain">
                </div>
            <?php else: ?>
                <img src="<?= asset('img/reqmaster-logo.png') ?>" alt="ReqMaster" class="w-16 h-16 mx-auto rounded-2xl object-contain bg-white border border-slate-200 p-1 shadow-md mb-3">
                <h1 class="text-2xl font-bold text-slate-900">Req<span class="text-indigo-600">Master</span></h1>
            <?php endif; ?>
            <p class="text-xs text-slate-500 mt-1">Gestão Integrada de Requisições, Fornecedores & Contratos</p>
        </div>

        <!-- Alertas Flash -->
        <?php if ($flash): ?>
            <?php 
                $bgClass = match($flash['type']) {
                    'success' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                    'danger' => 'bg-rose-50 text-rose-800 border-rose-200',
                    'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
                    default => 'bg-blue-50 text-blue-800 border-blue-200'
                };
            ?>
            <div class="p-3.5 mb-5 rounded-2xl border <?= $bgClass ?> text-xs flex items-center gap-2.5">
                <i class="fas fa-info-circle text-sm shrink-0"></i>
                <span class="font-medium leading-relaxed"><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Formulário de Login -->
        <form method="POST" action="<?= url('login') ?>" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">E-mail Corporativo</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                        <i class="fas fa-envelope text-sm"></i>
                    </span>
                    <input type="email" name="email" id="emailInput" required autofocus class="w-full pl-10 pr-3.5 py-2.5 text-sm rounded-xl border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/20 shadow-sm transition" placeholder="seu.email@seudominio.com.br">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Senha de Acesso</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                        <i class="fas fa-lock text-sm"></i>
                    </span>
                    <input type="password" name="password" id="passwordInput" required class="w-full pl-10 pr-3.5 py-2.5 text-sm rounded-xl border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/20 shadow-sm transition" placeholder="Sua senha segura">
                </div>
            </div>

            <?php if ($turnstileEnabled && !empty($turnstileSiteKey)): ?>
                <!-- Verificação Anti-robô Cloudflare Turnstile -->
                <div class="pt-2 flex justify-center">
                    <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($turnstileSiteKey) ?>" data-theme="light"></div>
                </div>
            <?php endif; ?>

            <div class="flex items-center justify-between text-xs pt-1">
                <label class="flex items-center text-slate-600 cursor-pointer select-none">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 mr-2">
                    Lembrar credenciais
                </label>
                <span class="text-slate-400">Acesso Seguro SSL</span>
            </div>

            <button type="submit" class="w-full py-3 px-4 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-600/30 transition duration-150 flex items-center justify-center gap-2 mt-2">
                <i class="fas fa-sign-in-alt"></i>
                <span>Entrar no Sistema</span>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100 text-center">
            <p class="text-[11px] text-slate-400">&copy; <?= date('Y') ?> <?= htmlspecialchars($companyName) ?> &bull; ReqMaster v<?= APP_VERSION ?></p>
        </div>
    </div>
</body>
</html>
