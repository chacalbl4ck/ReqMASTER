<?php
declare(strict_types=1);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="h-full flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-2xl p-8 border border-slate-100">
        <!-- Logo / Cabeçalho -->
        <div class="text-center mb-8">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/30 mb-3">
                <i class="fas fa-file-invoice text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Req<span class="text-indigo-600">Master</span></h1>
            <p class="text-xs text-slate-500 mt-1">Gestão Inteligente de Requisições, Fornecedores & Contratos</p>
        </div>

        <!-- Alertas Flash -->
        <?php if ($flash): ?>
            <?php 
                $bgClass = $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200';
            ?>
            <div class="p-3 mb-5 rounded-xl border <?= $bgClass ?> text-xs flex items-center gap-2">
                <i class="fas fa-info-circle"></i>
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Formulário de Login -->
        <form method="POST" action="<?= url('login') ?>" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">E-mail Corporativo</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fas fa-envelope text-sm"></i>
                    </span>
                    <input type="email" name="email" id="emailInput" required value="admin@empresa.com" class="w-full pl-9 pr-3 py-2.5 text-sm rounded-xl border border-slate-300 focus:border-indigo-600 focus:ring-indigo-600 shadow-sm" placeholder="seu.email@empresa.com">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Senha de Acesso</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fas fa-lock text-sm"></i>
                    </span>
                    <input type="password" name="password" id="passwordInput" required value="admin123" class="w-full pl-9 pr-3 py-2.5 text-sm rounded-xl border border-slate-300 focus:border-indigo-600 focus:ring-indigo-600 shadow-sm" placeholder="••••••••">
                </div>
            </div>

            <div class="flex items-center justify-between text-xs pt-1">
                <label class="flex items-center text-slate-600">
                    <input type="checkbox" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 mr-2">
                    Lembrar credenciais
                </label>
                <span class="text-slate-400">PHP 8.5 &bull; MySQL</span>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-600/30 transition duration-150">
                <i class="fas fa-sign-in-alt mr-2"></i> Entrar no Sistema
            </button>
        </form>

        <!-- Atalhos Rápidos para Demonstração / Testes -->
        <div class="mt-8 pt-6 border-t border-slate-100">
            <p class="text-xs font-medium text-slate-400 text-center mb-3">Acesso Rápido para Demonstração (Clique para preencher):</p>
            <div class="grid grid-cols-3 gap-2">
                <button type="button" onclick="fillCredentials('admin@empresa.com', 'admin123')" class="p-2 text-center rounded-lg border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50 text-[11px] font-medium text-slate-700 transition">
                    <i class="fas fa-user-shield block mb-1 text-indigo-600"></i> Admin
                </button>
                <button type="button" onclick="fillCredentials('carlos.aprovador@empresa.com', 'admin123')" class="p-2 text-center rounded-lg border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50 text-[11px] font-medium text-slate-700 transition">
                    <i class="fas fa-user-check block mb-1 text-emerald-600"></i> Aprovador
                </button>
                <button type="button" onclick="fillCredentials('mariana.solicitante@empresa.com', 'admin123')" class="p-2 text-center rounded-lg border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50 text-[11px] font-medium text-slate-700 transition">
                    <i class="fas fa-user-edit block mb-1 text-blue-600"></i> Solicitante
                </button>
            </div>
            <p class="text-[11px] text-slate-400 text-center mt-3">Senha padrão: <code class="bg-slate-100 px-1 py-0.5 rounded text-slate-600 font-mono">admin123</code></p>
        </div>
    </div>

    <script>
        function fillCredentials(email, pwd) {
            document.getElementById('emailInput').value = email;
            document.getElementById('passwordInput').value = pwd;
        }
    </script>
</body>
</html>
