<?php
declare(strict_types=1);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= bot_protection_meta($settings ?? []) ?>
    <?= favicon_tags() ?>
    <title>Licenciamento & Ativação - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        #licenseKeyInput {
            border: 1px solid #475569 !important;
            background-color: #020617 !important;
        }
        #licenseKeyInput:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25) !important;
            outline: none !important;
        }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl space-y-6">
        <!-- Ícone e Status de Licença -->
        <div class="text-center space-y-3">
            <div class="w-16 h-16 mx-auto rounded-3xl <?= $isLocked ? 'bg-amber-500/10 text-amber-500 border border-amber-500/20 shadow-lg shadow-amber-500/10' : 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20 shadow-lg shadow-indigo-500/10' ?> flex items-center justify-center text-2xl">
                <i class="fas <?= $isLocked ? 'fa-lock' : 'fa-certificate' ?>"></i>
            </div>

            <h1 class="text-2xl font-black tracking-tight text-white">
                <?= $isLocked ? 'Ativação de Licença Necessária' : 'Licenciamento Ativo' ?>
            </h1>
            <p class="text-xs text-slate-400 max-w-md mx-auto">
                <?= $isLocked
                    ? 'O sistema está bloqueado aguardando uma chave de licença oficial válida. Informe sua chave no formato de 16 dígitos para liberar o acesso.'
                    : 'Sua licença está verificada e operando com monitoramento contínuo de integridade.' ?>
            </p>
        </div>

        <!-- Alertas Flash -->
        <?php if ($flash): ?>
            <?php
                $bg = match($flash['type']) {
                    'success' => 'bg-emerald-950/80 text-emerald-300 border-emerald-800',
                    'danger' => 'bg-rose-950/80 text-rose-300 border-rose-800',
                    'warning' => 'bg-amber-950/80 text-amber-300 border-amber-800',
                    default => 'bg-blue-950/80 text-blue-300 border-blue-800'
                };
            ?>
            <div class="p-3.5 rounded-2xl border <?= $bg ?> text-xs flex items-center gap-2.5">
                <i class="fas fa-info-circle shrink-0"></i>
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Informações da Instância -->
        <div class="bg-slate-950/60 rounded-2xl p-4 border border-slate-800/80 text-xs space-y-2 font-mono">
            <div class="flex justify-between items-center text-slate-400">
                <span>ID da Instância:</span>
                <span class="text-indigo-400 font-bold"><?= htmlspecialchars($machineToken) ?></span>
            </div>
            <div class="flex justify-between items-center text-slate-400">
                <span>Versão do Software:</span>
                <span class="text-slate-300">ReqMaster v<?= APP_VERSION ?></span>
            </div>
            <div class="flex justify-between items-center text-slate-400">
                <span>Status da Licença:</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] <?= ($licenseStatus === 'active' && !$isLocked) ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= ($licenseStatus === 'active' && !$isLocked) ? 'bg-emerald-400' : 'bg-rose-400' ?>"></span>
                    <?= ($licenseStatus === 'active' && !$isLocked) ? 'Licença Ativa' : 'Não Licenciado / Bloqueado' ?>
                </span>
            </div>
            <div class="flex justify-between items-center text-slate-400">
                <span>Última Verificação:</span>
                <span class="text-slate-300"><?= htmlspecialchars($lastCheck) ?></span>
            </div>
        </div>

        <!-- Formulário de Ativação de Chave -->
        <form method="POST" action="<?= url('licenciamento/ativar') ?>" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Chave de Licença (16 dígitos)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                        <i class="fas fa-key text-xs"></i>
                    </span>
                    <input type="text" name="license_key" id="licenseKeyInput" required maxlength="19"
                        value="<?= htmlspecialchars($licenseKey) ?>"
                        placeholder="XXXX-XXXX-XXXX-XXXX"
                        oninput="formatLicenseInput(this)"
                        class="w-full pl-10 pr-3.5 py-3 text-sm rounded-xl text-white font-mono uppercase tracking-widest shadow-inner text-center">
                </div>
                <p class="text-[11px] text-slate-500 mt-1">Digite sua chave de 16 caracteres fornecida pelo distribuidor autorizado.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button type="submit" class="w-full py-3 px-4 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-600/25 transition duration-150 flex items-center justify-center gap-2">
                    <i class="fas fa-shield-check"></i>
                    <span>Validar Chave & Desbloquear</span>
                </button>
                <button type="button" onclick="openBuyLicenseModal()" class="w-full py-3 px-4 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 shadow-lg shadow-emerald-600/25 transition duration-150 flex items-center justify-center gap-2">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Comprar Licença</span>
                </button>
            </div>
        </form>

        <!-- Ações do Administrador e Sobre -->
        <div class="pt-4 border-t border-slate-800 text-center space-y-3">
            <?php if ($isAdmin): ?>
                <?php if (!$isLocked): ?>
                    <a href="<?= url('configuracoes?tab=licenca') ?>" class="inline-block px-4 py-2 rounded-xl text-xs font-semibold bg-slate-800 text-slate-300 hover:bg-slate-700 transition">
                        Voltar às Configurações &rarr;
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-xs text-slate-500">
                    Você é o administrador?
                    <a href="<?= url('login') ?>" class="text-indigo-400 hover:text-indigo-300 font-semibold underline underline-offset-2">Fazer Login de Administrador</a>
                </div>
            <?php endif; ?>
            <div class="text-[11px] text-slate-500 pt-1">
                CRM Soluções em Tecnologia &bull; Caio Monteiro &bull; 2027 &bull;
                <a href="<?= url('sobre') ?>" class="text-indigo-400 hover:text-indigo-300 underline underline-offset-2">Sobre o Sistema</a>
            </div>
        </div>
    </div>

    <!-- Modal Comprar Licença & Dados de Contato -->
    <div id="buyLicenseModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="max-w-lg w-full bg-slate-900 border border-slate-700 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-5 text-slate-100">
            <div class="flex justify-between items-start border-b border-slate-800 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-xl">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-white">Adquirir Licença Oficial</h3>
                        <p class="text-xs text-slate-400">CRM Soluções em Tecnologia &bull; Caio Monteiro</p>
                    </div>
                </div>
                <button type="button" onclick="closeBuyLicenseModal()" class="text-slate-400 hover:text-white text-lg">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="bg-emerald-950/40 border border-emerald-700/50 rounded-2xl p-4 space-y-2 text-xs">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-emerald-300 uppercase tracking-wider">Licença Anual</span>
                    <span class="text-lg font-black text-white">R$ 400,00 / ano</span>
                </div>
                <p class="text-slate-300">
                    &bull; <strong>Usuários e empresas ilimitadas</strong><br>
                    &bull; <strong>Suporte técnico:</strong> Segunda a Sexta, das 9h às 17h<br>
                    &bull; Software comercial pago, protegido por direitos autorais (não é de uso livre).
                </p>
            </div>

            <div class="space-y-2.5 text-xs">
                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between gap-3">
                    <div>
                        <span class="block text-[10px] text-slate-400 uppercase font-semibold">E-mail de Contato & Suporte</span>
                        <a href="mailto:caiormleo@outlook.com.br?subject=Compra%20de%20Licen%C3%A7a%20ReqMaster" class="text-sm font-bold text-indigo-400 hover:underline">
                            caiormleo@outlook.com.br
                        </a>
                    </div>
                    <a href="mailto:caiormleo@outlook.com.br?subject=Compra%20de%20Licen%C3%A7a%20ReqMaster" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shrink-0">
                        Enviar E-mail
                    </a>
                </div>

                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between gap-3">
                    <div>
                        <span class="block text-[10px] text-slate-400 uppercase font-semibold">WhatsApp</span>
                        <span class="text-sm font-bold text-emerald-400">@caior.monteiro</span>
                    </div>
                    <span class="px-3 py-1 rounded-xl bg-emerald-500/15 text-emerald-300 font-semibold border border-emerald-500/30">
                        <i class="fab fa-whatsapp mr-1"></i> @caior.monteiro
                    </span>
                </div>

                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between gap-3">
                    <div>
                        <span class="block text-[10px] text-slate-400 uppercase font-semibold">GitHub Oficial</span>
                        <a href="https://github.com/chacalbl4ck/reqmaster" target="_blank" rel="noopener noreferrer" class="text-sm font-bold text-slate-200 hover:text-white hover:underline">
                            chacalbl4ck/reqmaster
                        </a>
                    </div>
                    <span class="text-[10px] font-bold tracking-wider text-amber-400 uppercase">DEUS SEJA LOUVADO</span>
                </div>
            </div>

            <div class="flex justify-end pt-2 border-t border-slate-800">
                <button type="button" onclick="closeBuyLicenseModal()" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <script>
        function formatLicenseInput(el) {
            let clean = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 16);
            let parts = clean.match(/.{1,4}/g);
            el.value = parts ? parts.join('-') : '';
        }
        function openBuyLicenseModal() {
            document.getElementById('buyLicenseModal').classList.remove('hidden');
        }
        function closeBuyLicenseModal() {
            document.getElementById('buyLicenseModal').classList.add('hidden');
        }
    </script>
</body>
</html>
