<?php
declare(strict_types=1);

if (!empty($isLoggedIn)) {
    require __DIR__ . '/../layout/header.php';
} else {
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= bot_protection_meta($settings ?? []) ?>
    <?= favicon_tags() ?>
    <title>Sobre o Sistema - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/tailwind.min.css') . '?v=' . APP_VERSION ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <?= theme_css_vars($settings ?? []) ?>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-full bg-slate-50 text-slate-900 py-10 px-4">
<?php } ?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <?php if (empty($isLoggedIn)): ?>
                <a href="<?= url('licenciamento') ?>" class="text-xs font-semibold text-indigo-600 hover:underline inline-flex items-center gap-1 mb-1">
                    <i class="fas fa-arrow-left"></i> Voltar ao Sistema / Licenciamento
                </a>
            <?php endif; ?>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Sobre o Sistema</h1>
            <p class="text-xs sm:text-sm text-slate-500">Informações institucionais, licenciamento comercial, suporte e direitos autorais.</p>
        </div>
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-800 text-xs font-bold">
            <i class="fas fa-code-branch"></i> ReqMaster v<?= APP_VERSION ?>
        </span>
    </div>

    <!-- Card Principal: Desenvolvedor & Identidade -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-slate-900 p-6 sm:p-8 text-white flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <img src="<?= asset('img/reqmaster-logo.png') ?>" alt="ReqMaster" class="w-14 h-14 rounded-2xl bg-white p-1 object-contain shadow-lg shrink-0">
                <div>
                    <h2 class="text-xl sm:text-2xl font-black tracking-tight">CRM Soluções em Tecnologia</h2>
                    <p class="text-sm text-indigo-100 mt-0.5">Desenvolvido por <strong>Caio Monteiro</strong> &bull; 2027</p>
                </div>
            </div>
            <div class="px-4 py-2 rounded-2xl bg-white/10 border border-white/20 text-xs font-extrabold tracking-widest uppercase">
                DEUS SEJA LOUVADO
            </div>
        </div>

        <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Suporte & Contato -->
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100 pb-2 flex items-center gap-2">
                    <i class="fas fa-headset text-indigo-600"></i> Suporte Técnico & Contato Comercial
                </h3>

                <div class="space-y-3 text-sm">
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[11px] font-semibold text-slate-400 uppercase">E-mail para Suporte e Licenças</span>
                            <a href="mailto:caiormleo@outlook.com.br" class="font-bold text-slate-900 hover:text-indigo-600 break-all">
                                caiormleo@outlook.com.br
                            </a>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                            <i class="fab fa-whatsapp text-lg"></i>
                        </div>
                        <div>
                            <span class="block text-[11px] font-semibold text-slate-400 uppercase">WhatsApp</span>
                            <span class="font-bold text-slate-900">@caior.monteiro</span>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-200 text-slate-800 flex items-center justify-center shrink-0">
                            <i class="fab fa-github text-lg"></i>
                        </div>
                        <div>
                            <span class="block text-[11px] font-semibold text-slate-400 uppercase">Repositório GitHub</span>
                            <a href="https://github.com/chacalbl4ck/reqmaster" target="_blank" rel="noopener noreferrer" class="font-bold text-indigo-700 hover:underline">
                                chacalbl4ck/reqmaster
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Licenciamento & Direitos Autorais -->
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100 pb-2 flex items-center gap-2">
                    <i class="fas fa-certificate text-amber-600"></i> Licença Comercial & Direitos Autorais
                </h3>

                <div class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-200 space-y-2.5 text-xs text-slate-700">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-indigo-950 text-sm">Licença Anual Oficial</span>
                        <span class="px-3 py-1 rounded-full bg-indigo-600 text-white font-extrabold text-sm">
                            R$ 400,00 / ano
                        </span>
                    </div>
                    <ul class="space-y-1.5 pt-1 text-slate-700">
                        <li class="flex items-center gap-2">
                            <i class="fas fa-check-circle text-emerald-600"></i>
                            <span><strong>Usuários e empresas ilimitadas</strong></span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-clock text-indigo-600"></i>
                            <span><strong>Suporte técnico:</strong> Segunda a Sexta, das 9h às 17h</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-shield-alt text-amber-600"></i>
                            <span>Atualizações oficiais e validação contínua de integridade</span>
                        </li>
                    </ul>
                </div>

                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-950 space-y-1.5">
                    <div class="font-bold flex items-center gap-1.5 text-rose-900">
                        <i class="fas fa-copyright"></i> Propriedade Intelectual & Direitos Autorais
                    </div>
                    <p class="leading-relaxed">
                        O <strong>ReqMaster</strong> é um software comercial pago — <strong>não é de uso livre</strong> e é integralmente <strong>protegido por direitos autorais</strong>. É proibida a reprodução, redistribuição ou uso sem chave de licença válida emitida pela <strong>CRM Soluções em Tecnologia (Caio Monteiro)</strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if (!empty($isLoggedIn)) {
    require __DIR__ . '/../layout/footer.php';
} else {
    echo '</body></html>';
}
