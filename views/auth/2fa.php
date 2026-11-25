<?php
declare(strict_types=1);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= bot_protection_meta($settings ?? []) ?>
    <?= favicon_tags() ?>
    <title>Verificação em 2 Etapas (2FA) - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/tailwind.min.css') . '?v=' . APP_VERSION ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?= theme_css_vars($settings ?? []) ?>
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="h-full flex items-center justify-center p-4 bg-slate-950 text-slate-800">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8 border border-slate-100 space-y-6">
        <div class="text-center space-y-2">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl shadow-sm border border-indigo-100">
                <i class="fas fa-shield-halved"></i>
            </div>
            <h1 class="text-xl font-bold text-slate-900">Verificação em 2 Etapas (2FA)</h1>
            <p class="text-xs text-slate-500">
                Digite o código de 6 dígitos exibido no seu aplicativo autenticador (Google Authenticator, Authy ou Microsoft Authenticator) para acessar como Administrador.
            </p>
        </div>

        <?php if ($flash): ?>
            <div class="p-3 rounded-xl border bg-rose-50 text-rose-800 border-rose-200 text-xs flex items-center gap-2">
                <i class="fas fa-exclamation-circle shrink-0"></i>
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('2fa') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5 text-center">Código TOTP ou Código de Recuperação</label>
                <input type="text" name="totp_code" required autofocus autocomplete="one-time-code"
                    placeholder="000000" maxlength="12"
                    class="w-full py-3 px-4 text-center text-2xl font-mono font-bold tracking-[0.35em] rounded-xl uppercase">
            </div>

            <button type="submit" class="w-full py-3 px-4 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg transition flex items-center justify-center gap-2">
                <i class="fas fa-check-circle"></i> Verificar e Entrar
            </button>
        </form>

        <div class="pt-4 border-t border-slate-100 text-center">
            <a href="<?= url('logout') ?>" class="text-xs text-slate-500 hover:text-slate-800 font-medium">
                &larr; Cancelar e voltar ao login
            </a>
        </div>
    </div>
</body>
</html>
