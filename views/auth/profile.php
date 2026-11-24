<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';

$avatarUrl = !empty($user['avatar']) ? asset($user['avatar']) : null;
$avatarPhysical = !empty($user['avatar']) ? (__DIR__ . '/../../public/' . ltrim($user['avatar'], '/')) : '';
$hasAvatar = !empty($avatarUrl) && file_exists($avatarPhysical);
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Cabeçalho do Perfil -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Meu Perfil de Usuário</h1>
            <p class="text-xs sm:text-sm text-slate-500">Atualize seus dados pessoais, foto de identificação e senha de acesso.</p>
        </div>
        <a href="<?= url() ?>" class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-slate-600 bg-white hover:bg-slate-50 border border-slate-200 transition shadow-sm">
            &larr; Voltar ao Painel
        </a>
    </div>

    <!-- Card de Edição -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
        <form method="POST" action="<?= url('perfil/atualizar') ?>" enctype="multipart/form-data" class="space-y-8">
            <?= csrf_field() ?>

            <!-- Seção 1: Foto e Identificação -->
            <div class="p-6 rounded-2xl bg-slate-50/70 border border-slate-200/70 flex flex-col sm:flex-row items-center gap-6">
                <div class="relative shrink-0">
                    <?php if ($hasAvatar): ?>
                        <img src="<?= $avatarUrl ?>" alt="Foto de Perfil" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md">
                    <?php else: ?>
                        <div class="w-24 h-24 rounded-full bg-gradient-to-tr from-indigo-600 to-indigo-500 text-white flex items-center justify-center font-bold text-3xl shadow-md border-4 border-white">
                            <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex-1 space-y-2 text-center sm:text-left">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Alterar Foto de Perfil</label>
                    <input type="file" name="avatar" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                    <p class="text-[11px] text-slate-400">Formatos aceitos: JPG, PNG, WEBP ou GIF. Tamanho ideal: 400x400px.</p>
                </div>

                <div class="hidden sm:flex flex-col gap-1.5 border-l border-slate-200 pl-6 text-xs">
                    <span class="text-slate-400 font-medium">Cargo no Sistema:</span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 capitalize">
                        <i class="fas fa-shield-alt text-[10px]"></i> <?= htmlspecialchars($user['role'] ?? 'solicitante') ?>
                    </span>
                    <span class="text-slate-400 font-medium mt-1">Setor / Departamento:</span>
                    <span class="text-slate-800 font-semibold"><?= htmlspecialchars($user['department'] ?? 'Geral') ?></span>
                </div>
            </div>

            <!-- Seção 2: Dados Pessoais -->
            <div>
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">
                    Informações Pessoais & Contato
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Nome Completo <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" required value="<?= htmlspecialchars($user['name'] ?? '') ?>" class="w-full text-sm rounded-xl border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/20 py-2.5 px-3.5 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">E-mail Corporativo <span class="text-rose-500">*</span></label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="w-full text-sm rounded-xl border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/20 py-2.5 px-3.5 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Telefone / WhatsApp</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="(00) 00000-0000" class="w-full text-sm rounded-xl border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/20 py-2.5 px-3.5 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Departamento</label>
                        <input type="text" disabled value="<?= htmlspecialchars($user['department'] ?? 'Geral') ?>" class="w-full text-sm rounded-xl border border-slate-200 bg-slate-100 text-slate-500 py-2.5 px-3.5 cursor-not-allowed">
                    </div>
                </div>
            </div>

            <!-- Seção 3: Troca de Senha -->
            <div>
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2 mb-2">
                    Segurança & Alteração de Senha
                </h2>
                <p class="text-xs text-slate-400 mb-4">Deixe os campos abaixo em branco caso deseje manter sua senha atual.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Nova Senha</label>
                        <input type="password" name="password" minlength="4" class="w-full text-sm rounded-xl border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/20 py-2.5 px-3.5 shadow-sm" placeholder="••••••••">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1.5">Confirmar Nova Senha</label>
                        <input type="password" name="password_confirm" minlength="4" class="w-full text-sm rounded-xl border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/20 py-2.5 px-3.5 shadow-sm" placeholder="••••••••">
                    </div>
                </div>
            </div>

            <!-- Botão Salvar -->
            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="<?= url() ?>" class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/20 transition flex items-center gap-2">
                    <i class="fas fa-save"></i>
                    <span>Salvar Alterações</span>
                </button>
            </div>
        </form>
    </div>

    <?php if (($user['role'] ?? '') === 'admin'): ?>
        <!-- Card de Autenticação em 2 Etapas (TOTP) para Administradores -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-5">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i class="fas fa-shield-halved text-indigo-600"></i> Autenticação em 2 Etapas (TOTP)
                    </h2>
                    <p class="text-xs text-slate-500">Proteja sua conta de administrador exigindo código temporário de aplicativo autenticador no login.</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?= !empty($user['totp_enabled']) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' ?>">
                    <span class="w-2 h-2 rounded-full <?= !empty($user['totp_enabled']) ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                    <?= !empty($user['totp_enabled']) ? '2FA Ativo' : '2FA Desativado' ?>
                </span>
            </div>

            <?php if (!empty($newRecoveryCodes) && is_array($newRecoveryCodes)): ?>
                <div class="p-5 rounded-2xl bg-amber-50 border border-amber-200 space-y-2">
                    <div class="text-xs font-bold text-amber-900 flex items-center gap-1.5">
                        <i class="fas fa-key text-amber-600"></i> Guarde seus Códigos de Recuperação (Uso Único)
                    </div>
                    <p class="text-xs text-amber-800">Caso perca acesso ao seu celular, utilize um destes códigos para entrar:</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-1 font-mono text-xs font-bold text-slate-800">
                        <?php foreach ($newRecoveryCodes as $rc): ?>
                            <div class="p-2 rounded-lg bg-white border border-amber-200 text-center select-all"><?= htmlspecialchars($rc) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($user['totp_enabled'])): ?>
                <div class="p-5 rounded-2xl bg-emerald-50/70 border border-emerald-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div class="text-xs text-emerald-900 space-y-1">
                        <div class="font-bold text-sm flex items-center gap-1.5">
                            <i class="fas fa-check-circle text-emerald-600"></i> Proteção TOTP Ativada
                        </div>
                        <p class="text-emerald-700">Sua conta solicita o código de 6 dígitos do aplicativo autenticador a cada novo acesso.</p>
                    </div>
                </div>

                <form method="POST" action="<?= url('perfil/2fa/desativar') ?>" class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row items-end gap-3" onsubmit="return confirm('Tem certeza que deseja desativar a autenticação em 2 etapas?');">
                    <?= csrf_field() ?>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Senha Atual (para confirmar desativação)</label>
                        <input type="password" name="current_password" required placeholder="Digite sua senha atual" class="w-full text-sm rounded-xl py-2 px-3">
                    </div>
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition whitespace-nowrap">
                        <i class="fas fa-times-circle mr-1"></i> Desativar 2FA
                    </button>
                </form>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                    <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col items-center text-center space-y-3">
                        <div id="totpQrContainer" class="p-3 bg-white rounded-2xl border border-slate-200 shadow-inner"></div>
                        <div class="text-[11px] text-slate-500">
                            Escaneie com <strong>Google Authenticator</strong>, <strong>Authy</strong> ou <strong>Microsoft Authenticator</strong>.
                        </div>
                        <div class="w-full p-2 rounded-lg bg-white border border-slate-200 text-[11px] font-mono text-slate-700 break-all select-all">
                            Chave Manual: <strong><?= htmlspecialchars((string)$pendingTotpSecret) ?></strong>
                        </div>
                    </div>

                    <form method="POST" action="<?= url('perfil/2fa/ativar') ?>" class="space-y-4">
                        <?= csrf_field() ?>
                        <div class="text-xs text-slate-600 space-y-2 leading-relaxed">
                            <p><strong>Passo 1:</strong> Abra seu aplicativo autenticador e escaneie o QR Code ao lado (ou insira a chave manual).</p>
                            <p><strong>Passo 2:</strong> Digite abaixo o código de 6 dígitos gerado pelo app para concluir a ativação.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Código de Verificação (6 dígitos)</label>
                            <input type="text" name="totp_code" required maxlength="6" pattern="\d{6}" placeholder="000000" class="w-full text-center font-mono text-xl font-bold tracking-[0.3em] rounded-xl py-2.5">
                        </div>

                        <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md transition flex items-center justify-center gap-2">
                            <i class="fas fa-lock"></i> Ativar Autenticação em 2 Etapas
                        </button>
                    </form>
                </div>

                <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const container = document.getElementById('totpQrContainer');
                        if (container && typeof QRCode !== 'undefined') {
                            new QRCode(container, {
                                text: <?= json_encode((string)$pendingTotpUri) ?>,
                                width: 170,
                                height: 170,
                                colorDark: "#0f172a",
                                colorLight: "#ffffff",
                                correctLevel: QRCode.CorrectLevel.M
                            });
                        }
                    });
                </script>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
