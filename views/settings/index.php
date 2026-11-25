<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';

$activeTab = $tab ?? 'empresa';
$logoPath = $settings['company_logo'] ?? '';
$logoPhysical = !empty($logoPath) ? (__DIR__ . '/../../public/' . ltrim($logoPath, '/')) : '';
$hasLogo = !empty($logoPath) && file_exists($logoPhysical);
$isSystemLocked = ($settings['license_lock_mode'] ?? '0') === '1';
?>

<div class="space-y-6">
    <!-- Cabeçalho das Configurações -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Configurações do Sistema</h1>
            <p class="text-xs sm:text-sm text-slate-500">Gerencie identidade visual, permissões, pasta de anexos, e-mails, Cloudflare Turnstile, licenciamento e atualizações.</p>
        </div>
    </div>

    <!-- Navegação por Abas -->
    <div class="border-b border-slate-200 bg-white rounded-2xl p-2 shadow-sm flex overflow-x-auto gap-1.5 scrollbar-none">
        <a href="<?= url('configuracoes?tab=empresa') ?>" class="px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap <?= $activeTab === 'empresa' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100' ?>">
            <i class="fas fa-building"></i> Empresa & Logo
        </a>
        <a href="<?= url('configuracoes?tab=cores') ?>" class="px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap <?= $activeTab === 'cores' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100' ?>">
            <i class="fas fa-palette"></i> Cores & Tema
        </a>
        <a href="<?= url('configuracoes?tab=usuarios') ?>" class="px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap <?= $activeTab === 'usuarios' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100' ?>">
            <i class="fas fa-users-cog"></i> Usuários & Permissões
        </a>
        <a href="<?= url('configuracoes?tab=anexos') ?>" class="px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap <?= $activeTab === 'anexos' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100' ?>">
            <i class="fas fa-folder-open"></i> Pasta de Anexos
        </a>
        <a href="<?= url('configuracoes?tab=email') ?>" class="px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap <?= $activeTab === 'email' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100' ?>">
            <i class="fas fa-envelope-open-text"></i> E-mail (SMTP)
        </a>
        <a href="<?= url('configuracoes?tab=seguranca') ?>" class="px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap <?= $activeTab === 'seguranca' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100' ?>">
            <i class="fas fa-shield-alt"></i> Segurança & Web
        </a>
        <a href="<?= url('configuracoes?tab=licenca') ?>" class="px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap <?= $activeTab === 'licenca' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100' ?>">
            <i class="fas fa-certificate"></i> Licenciamento
        </a>
        <a href="<?= url('configuracoes?tab=atualizacoes') ?>" class="px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 whitespace-nowrap <?= in_array($activeTab, ['atualizacoes', 'github'], true) ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-100' ?>">
            <i class="fas fa-cloud-download-alt"></i> Atualizações
        </a>
    </div>

    <!-- ======================================================== -->
    <!-- ABA 1: DADOS DA MINHA EMPRESA & LOGOTIPO                -->
    <!-- ======================================================== -->
    <?php if ($activeTab === 'empresa'): ?>
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-base font-bold text-slate-800">Identidade Institucional da Empresa Solicitante</h2>
                <p class="text-xs text-slate-400">Estes dados e o logotipo serão exibidos no cabeçalho das requisições, ficha para impressão e PDF oficial.</p>
            </div>

            <form method="POST" action="<?= url('configuracoes/empresa') ?>" enctype="multipart/form-data" class="space-y-6">
                <?= csrf_field() ?>

                <!-- Logotipo Atual e Upload -->
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col sm:flex-row items-center gap-6">
                    <div class="w-36 h-28 rounded-2xl bg-white border border-slate-200 flex items-center justify-center p-2 overflow-hidden shadow-inner shrink-0">
                        <?php if ($hasLogo): ?>
                            <img src="<?= asset($logoPath) ?>" alt="Logo Empresa" class="max-w-full max-h-full object-contain">
                        <?php else: ?>
                            <div class="text-center text-slate-300">
                                <i class="fas fa-image text-3xl mb-1 block"></i>
                                <span class="text-[10px]">Sem logotipo</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 space-y-2">
                        <label class="block text-xs font-semibold text-slate-700 uppercase">Selecione o Logotipo da sua Empresa</label>
                        <input type="file" name="company_logo" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                        <p class="text-[11px] text-slate-400">Formatos aceitos: PNG (com fundo transparente recomendado), JPG, JPEG, SVG ou WEBP.</p>
                    </div>
                </div>

                <!-- Formulário de Dados -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Razão Social da Empresa <span class="text-rose-500">*</span></label>
                        <input type="text" name="company_name" required value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>" placeholder="Razão Social da Empresa" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome Fantasia</label>
                        <input type="text" name="company_trade_name" value="<?= htmlspecialchars($settings['company_trade_name'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">CNPJ da Empresa</label>
                        <input type="text" name="company_cnpj" value="<?= htmlspecialchars(\App\Helpers\Security::formatDocument($settings['company_cnpj'] ?? '')) ?>" class="mask-doc w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="00.000.000/0000-00">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Telefone / WhatsApp</label>
                        <input type="text" name="company_phone" value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">E-mail Institucional</label>
                        <input type="email" name="company_email" value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Endereço Completo</label>
                        <input type="text" name="company_address" value="<?= htmlspecialchars($settings['company_address'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Rua, número, cidade/UF, CEP">
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md shadow-indigo-600/30 transition">
                        <i class="fas fa-save mr-1.5"></i> Salvar Identidade da Empresa
                    </button>
                </div>
            </form>
        </div>

    <!-- ======================================================== -->
    <!-- ABA 1B: ESQUEMA DE CORES DO SITE & REQUISIÇÕES           -->
    <!-- ======================================================== -->
    <?php elseif ($activeTab === 'cores'): ?>
        <?php
            $tPrimary   = safe_hex_color($settings['theme_primary_color'] ?? '#4f46e5', '#4f46e5');
            $tSecondary = safe_hex_color($settings['theme_secondary_color'] ?? '#4338ca', '#4338ca');
            $tAccent    = safe_hex_color($settings['theme_accent_color'] ?? '#6366f1', '#6366f1');
            $rPrimary   = safe_hex_color($settings['req_primary_color'] ?? '#4338ca', '#4338ca');
            $rTint      = safe_hex_color($settings['req_bg_tint'] ?? '#eef2ff', '#eef2ff');
            $rBorder    = safe_hex_color($settings['req_border_color'] ?? '#cbd5e1', '#cbd5e1');
        ?>
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-palette text-indigo-600"></i> Esquema de Cores do Site e das Requisições
                </h2>
                <p class="text-xs text-slate-400">Personalize a paleta visual do sistema web e das fichas oficiais de requisição (A4 / PDF / Online).</p>
            </div>

            <!-- Presets rápidos -->
            <div class="space-y-2">
                <span class="block text-xs font-bold text-slate-600 uppercase">Temas Prontos (1 Clique):</span>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="applyColorPreset('#4f46e5','#4338ca','#6366f1','#4338ca','#eef2ff','#cbd5e1')" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold flex items-center gap-2 hover:bg-slate-50">
                        <span class="w-3.5 h-3.5 rounded-full inline-block" style="background-color: #4f46e5 !important;"></span> Índigo Padrão
                    </button>
                    <button type="button" onclick="applyColorPreset('#2563eb','#1d4ed8','#3b82f6','#1d4ed8','#eff6ff','#bfdbfe')" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold flex items-center gap-2 hover:bg-slate-50">
                        <span class="w-3.5 h-3.5 rounded-full inline-block" style="background-color: #2563eb !important;"></span> Azul Executivo
                    </button>
                    <button type="button" onclick="applyColorPreset('#059669','#047857','#10b981','#047857','#ecfdf5','#a7f3d0')" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold flex items-center gap-2 hover:bg-slate-50">
                        <span class="w-3.5 h-3.5 rounded-full inline-block" style="background-color: #059669 !important;"></span> Esmeralda
                    </button>
                    <button type="button" onclick="applyColorPreset('#0f172a','#1e293b','#334155','#0f172a','#f1f5f9','#cbd5e1')" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold flex items-center gap-2 hover:bg-slate-50">
                        <span class="w-3.5 h-3.5 rounded-full inline-block" style="background-color: #0f172a !important;"></span> Grafite Corporativo
                    </button>
                    <button type="button" onclick="applyColorPreset('#be123c','#9f1239','#e11d48','#9f1239','#fff1f2','#fecdd3')" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold flex items-center gap-2 hover:bg-slate-50">
                        <span class="w-3.5 h-3.5 rounded-full inline-block" style="background-color: #be123c !important;"></span> Rubi Executivo
                    </button>
                </div>
            </div>

            <form method="POST" action="<?= url('configuracoes/cores') ?>" class="space-y-6">
                <?= csrf_field() ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Cores do Site -->
                    <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">1. Cores da Interface do Site</h3>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Cor Primária</label>
                                <input type="color" name="theme_primary_color" id="cThemePrimary" value="<?= $tPrimary ?>" class="w-full h-10 rounded-lg cursor-pointer border border-slate-300">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Cor Secundária</label>
                                <input type="color" name="theme_secondary_color" id="cThemeSecondary" value="<?= $tSecondary ?>" class="w-full h-10 rounded-lg cursor-pointer border border-slate-300">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Cor Destaque</label>
                                <input type="color" name="theme_accent_color" id="cThemeAccent" value="<?= $tAccent ?>" class="w-full h-10 rounded-lg cursor-pointer border border-slate-300">
                            </div>
                        </div>
                    </div>

                    <!-- Cores das Requisições -->
                    <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">2. Cores das Fichas de Requisição</h3>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Cor Títulos</label>
                                <input type="color" name="req_primary_color" id="cReqPrimary" value="<?= $rPrimary ?>" class="w-full h-10 rounded-lg cursor-pointer border border-slate-300">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Fundo Suave</label>
                                <input type="color" name="req_bg_tint" id="cReqTint" value="<?= $rTint ?>" class="w-full h-10 rounded-lg cursor-pointer border border-slate-300">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Cor Bordas</label>
                                <input type="color" name="req_border_color" id="cReqBorder" value="<?= $rBorder ?>" class="w-full h-10 rounded-lg cursor-pointer border border-slate-300">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md transition">
                        <i class="fas fa-save mr-1.5"></i> Salvar Esquema de Cores
                    </button>
                </div>
            </form>
        </div>

    <!-- ======================================================== -->
    <!-- ABA 2: USUÁRIOS & PERMISSÕES (ADMINISTRAÇÃO COMPLETA)    -->
    <!-- ======================================================== -->
    <?php elseif ($activeTab === 'usuarios'): ?>
        <div class="space-y-6">
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-800">Controle de Usuários e Permissões</h2>
                        <p class="text-xs text-slate-400">Gerencie os colaboradores, defina cargos, redefina senhas ou bloqueie acessos.</p>
                    </div>
                    <button type="button" onclick="openUserModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5">
                        <i class="fas fa-user-plus"></i> Cadastrar Novo Usuário
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-4">Usuário</th>
                                <th class="py-3 px-4">E-mail Corporativo</th>
                                <th class="py-3 px-4">Departamento</th>
                                <th class="py-3 px-4">Telefone</th>
                                <th class="py-3 px-4 text-center">Cargo / Perfil</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($users as $u): ?>
                                <?php 
                                    $uAvatar = !empty($u['avatar']) ? asset($u['avatar']) : null;
                                    $uAvatarReal = !empty($u['avatar']) ? (__DIR__ . '/../../public/' . ltrim($u['avatar'], '/')) : '';
                                    $hasUAvatar = !empty($uAvatar) && file_exists($uAvatarReal);
                                    $isMe = (int)$u['id'] === (int)$currentUser['id'];
                                ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <?php if ($hasUAvatar): ?>
                                                <img src="<?= $uAvatar ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover border border-slate-200">
                                            <?php else: ?>
                                                <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs border border-indigo-200">
                                                    <?= strtoupper(substr($u['name'] ?? 'U', 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <span class="font-bold text-slate-800 block"><?= htmlspecialchars($u['name']) ?></span>
                                                <?php if ($isMe): ?>
                                                    <span class="text-[10px] text-indigo-600 font-semibold">(Sua conta)</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600 font-medium"><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="py-3 px-4 text-slate-500"><?= htmlspecialchars($u['department']) ?></td>
                                    <td class="py-3 px-4 text-slate-500"><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-center">
                                        <?php
                                            $roleBadge = match($u['role']) {
                                                'admin' => '<span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200"><i class="fas fa-shield-alt text-[10px] mr-1"></i>Admin</span>',
                                                'aprovador' => '<span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><i class="fas fa-check-circle text-[10px] mr-1"></i>Aprovador</span>',
                                                default => '<span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">Solicitante</span>',
                                            };
                                            echo $roleBadge;
                                        ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $u['active'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $u['active'] ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
                                            <?= $u['active'] ? 'Ativo' : 'Bloqueado' ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1">
                                            <!-- Editar Usuário -->
                                            <button type="button" onclick='editUser(<?= json_encode($u) ?>)' class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition" title="Editar Dados e E-mail">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>

                                            <!-- Resetar Senha -->
                                            <button type="button" onclick='openResetPasswordModal(<?= (int)$u['id'] ?>, "<?= htmlspecialchars($u['name']) ?>")' class="p-1.5 rounded-lg text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition" title="Resetar Senha deste Usuário">
                                                <i class="fas fa-key"></i>
                                            </button>

                                            <!-- Bloquear / Desbloquear -->
                                            <?php if (!$isMe): ?>
                                                <form method="POST" action="<?= url('configuracoes/usuarios/toggle-status') ?>" class="inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="p-1.5 rounded-lg <?= $u['active'] ? 'text-slate-400 hover:text-amber-600 hover:bg-amber-50' : 'text-slate-400 hover:text-emerald-600 hover:bg-emerald-50' ?> transition" title="<?= $u['active'] ? 'Bloquear Acesso' : 'Desbloquear Acesso' ?>">
                                                        <i class="fas <?= $u['active'] ? 'fa-ban' : 'fa-unlock' ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Excluir Usuário -->
                                                <form method="POST" action="<?= url('configuracoes/usuarios/excluir') ?>" class="inline" onsubmit="return confirm('Tem certeza de que deseja excluir permanentemente o usuário <?= htmlspecialchars($u['name']) ?>?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="Excluir Usuário">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal de Cadastro / Edição de Usuário -->
        <div id="userModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 border border-slate-100">
                <div class="flex justify-between items-center mb-4">
                    <h3 id="userModalTitle" class="font-bold text-lg text-slate-900">Novo Usuário</h3>
                    <button type="button" onclick="closeUserModal()" class="text-slate-400 hover:text-slate-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="userForm" method="POST" action="<?= url('configuracoes/usuarios/salvar') ?>" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="userId" value="0">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome Completo <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" id="userName" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">E-mail Corporativo <span class="text-rose-500">*</span></label>
                            <input type="email" name="email" id="userEmail" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Telefone</label>
                            <input type="text" name="phone" id="userPhone" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Departamento / Setor <span class="text-rose-500">*</span></label>
                            <input type="text" name="department" id="userDept" required value="Geral" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Papel / Perfil <span class="text-rose-500">*</span></label>
                            <select name="role" id="userRole" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="solicitante">Solicitante (Apenas cria requisições)</option>
                                <option value="aprovador">Aprovador (Aprova requisições e contratos)</option>
                                <option value="admin">Administrador (Controle Total do Sistema)</option>
                            </select>
                        </div>
                    </div>

                    <div id="passwordFieldContainer">
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">
                            Senha de Acesso <span id="pwdRequired" class="text-rose-500">*</span>
                        </label>
                        <input type="password" name="password" id="userPassword" minlength="4" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="••••••••">
                        <span id="pwdHint" class="text-[11px] text-slate-400 block mt-1 hidden">Deixe em branco para manter a senha atual.</span>
                    </div>

                    <div class="pt-2">
                        <label class="flex items-center text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" name="active" id="userActive" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mr-2">
                            <span>Usuário ativo (permite login no sistema)</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                        <button type="button" onclick="closeUserModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Cancelar</button>
                        <button type="submit" class="px-5 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm">Salvar Usuário</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal de Resetar Senha -->
        <div id="resetPasswordModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-6 border border-slate-100 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                        <i class="fas fa-key text-amber-500"></i> Resetar Senha
                    </h3>
                    <button type="button" onclick="closeResetPasswordModal()" class="text-slate-400 hover:text-slate-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <p class="text-xs text-slate-500">
                    Defina uma nova senha de acesso para o usuário <strong id="resetUserName" class="text-slate-800"></strong>:
                </p>

                <form method="POST" action="<?= url('configuracoes/usuarios/reset-senha') ?>" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="resetUserId" value="0">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nova Senha Provisória</label>
                        <input type="password" name="new_password" required minlength="4" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="••••••••">
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" onclick="closeResetPasswordModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Cancelar</button>
                        <button type="submit" class="px-5 py-2 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-sm">Salvar Nova Senha</button>
                    </div>
                </form>
            </div>
        </div>

    <!-- ======================================================== -->
    <!-- ABA 3: PASTA DE ANEXOS                                  -->
    <!-- ======================================================== -->
    <?php elseif ($activeTab === 'anexos'): ?>
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-base font-bold text-slate-800">Armazenamento de Anexos e Fotos</h2>
                <p class="text-xs text-slate-400">Configure a pasta do servidor onde os arquivos enviados nas requisições serão armazenados.</p>
            </div>

            <form method="POST" action="<?= url('configuracoes/anexos') ?>" class="space-y-6">
                <?= csrf_field() ?>

                <div class="space-y-4 max-w-2xl">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Caminho da Pasta de Upload no Servidor</label>
                        <input type="text" name="upload_dir" value="<?= htmlspecialchars($settings['upload_dir'] ?? 'public/uploads/attachments') ?>" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                        <p class="text-[11px] text-slate-400 mt-1">Padrão: <code>public/uploads/attachments</code> (relativo à raiz da aplicação).</p>
                    </div>

                    <!-- Diagnóstico de Permissão da Pasta -->
                    <div class="p-4 rounded-2xl border text-xs flex items-center gap-3 <?= $isUploadWritable ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
                        <i class="fas <?= $isUploadWritable ? 'fa-check-circle text-emerald-600' : 'fa-exclamation-triangle text-rose-600' ?> text-lg"></i>
                        <div>
                            <div class="font-bold">Status da Pasta no Servidor: <?= $isUploadWritable ? 'Permissão de Gravação OK' : 'Sem Permissão de Gravação' ?></div>
                            <div class="text-[11px] opacity-80">Caminho Absoluto: <code><?= htmlspecialchars($fullUploadPath) ?></code></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tamanho Máximo por Arquivo (MB)</label>
                            <input type="number" name="max_upload_size" min="1" max="100" value="<?= htmlspecialchars($settings['max_upload_size'] ?? '15') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Extensões Permitidas</label>
                            <input type="text" name="allowed_extensions" value="<?= htmlspecialchars($settings['allowed_extensions'] ?? 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,zip') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md shadow-indigo-600/30 transition">
                        <i class="fas fa-save mr-1.5"></i> Salvar Configurações de Armazenamento
                    </button>
                </div>
            </form>
        </div>

    <!-- ======================================================== -->
    <!-- ABA 4: SERVIDOR DE E-MAIL (SMTP)                        -->
    <!-- ======================================================== -->
    <?php elseif ($activeTab === 'email'): ?>
        <div class="space-y-6">
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-bold text-slate-800">Servidor de E-mail (SMTP Nativo em Sockets)</h2>
                    <p class="text-xs text-slate-400">Configure os parâmetros SMTP para disparo de notificações automáticas quando requisições forem criadas ou alteradas.</p>
                </div>

                <form method="POST" action="<?= url('configuracoes/email') ?>" class="space-y-6">
                    <?= csrf_field() ?>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Servidor SMTP (Host) <span class="text-rose-500">*</span></label>
                            <input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" placeholder="ex: smtp.gmail.com ou mail.suaempresa.com.br" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Porta SMTP <span class="text-rose-500">*</span></label>
                            <input type="number" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Usuário SMTP / E-mail</label>
                            <input type="text" name="smtp_user" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>" placeholder="usuario@provedor.com" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Senha SMTP / Senha de App</label>
                            <input type="password" name="smtp_pass" value="<?= htmlspecialchars($settings['smtp_pass'] ?? '') ?>" placeholder="••••••••••••" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Criptografia</label>
                            <select name="smtp_secure" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="tls" <?= ($settings['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (Porta 587)</option>
                                <option value="ssl" <?= ($settings['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL / SMTPS (Porta 465)</option>
                                <option value="none" <?= ($settings['smtp_secure'] ?? '') === 'none' ? 'selected' : '' ?>>Nenhuma (Porta 25)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome de Exibição do Remetente</label>
                            <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($settings['smtp_from_name'] ?? 'ReqMaster - Notificações') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">E-mail do Remetente</label>
                            <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>" placeholder="noreply@suaempresa.com.br" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="pt-2">
                        <label class="flex items-center text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" name="email_notifications" value="1" <?= ($settings['email_notifications'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mr-2">
                            <span><strong>Habilitar envio automático de e-mails</strong> para os aprovadores ao cadastrar requisições e para o solicitante ao aprovar/rejeitar.</span>
                        </label>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-100">
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md shadow-indigo-600/30 transition">
                            <i class="fas fa-save mr-1.5"></i> Salvar Configurações de E-mail
                        </button>
                    </div>
                </form>
            </div>

            <!-- Card de Teste de Envio -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-paper-plane text-emerald-600"></i> Testar Envio de E-mail
                </h3>
                <p class="text-xs text-slate-500">Envie um e-mail de teste para verificar se o seu servidor SMTP está respondendo corretamente.</p>

                <form method="POST" action="<?= url('configuracoes/email/teste') ?>" class="flex flex-col sm:flex-row gap-3">
                    <?= csrf_field() ?>
                    <input type="email" name="test_email" required value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" placeholder="Digite o e-mail de destino do teste" class="flex-1 text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition whitespace-nowrap">
                        <i class="fas fa-paper-plane mr-1.5"></i> Enviar E-mail de Teste
                    </button>
                </form>
            </div>
        </div>

    <!-- ======================================================== -->
    <!-- ABA 5: SEGURANÇA, HOSPEDAGEM WEB, BOTS & TURNSTILE       -->
    <!-- ======================================================== -->
    <?php elseif ($activeTab === 'seguranca'): ?>
        <?php $isWebHosted = ($settings['is_web_hosted'] ?? '0') === '1'; ?>
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-shield-alt text-indigo-600"></i> Segurança, Hospedagem Web & Proteção Anti-Robô
                </h2>
                <p class="text-xs text-slate-400">Configure publicação online (QR Code e bloqueio de indexação por buscadores) e proteção Cloudflare Turnstile.</p>
            </div>

            <form method="POST" action="<?= url('configuracoes/seguranca') ?>" class="space-y-6 max-w-2xl">
                <?= csrf_field() ?>

                <!-- Bloco A: Hospedagem na Web, QR Code e Bots -->
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="is_web_hosted" id="isWebHostedCheck" value="1" <?= $isWebHosted ? 'checked' : '' ?> onchange="toggleWebOptions()" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                        <div>
                            <span class="text-sm font-bold text-slate-800 block">Sistema hospedado na WEB</span>
                            <span class="text-xs text-slate-500">Marque esta opção se o sistema estiver acessível pela internet em um domínio ou servidor web externo.</span>
                        </div>
                    </label>

                    <div id="webHostedSubOptions" class="<?= $isWebHosted ? '' : 'hidden' ?> pl-7 pt-3 border-t border-slate-200 space-y-3">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" name="enable_qrcode_link" value="1" <?= ($settings['enable_qrcode_link'] ?? '0') === '1' ? 'checked' : '' ?> class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Gerar QR Code e link de visualização online nas requisições</span>
                                <span class="text-[11px] text-slate-500">Insere QR Code com token seguro nas fichas e disponibiliza o link direto nos e-mails enviados. Desmarque para desativar.</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" name="block_search_bots" value="1" <?= ($settings['block_search_bots'] ?? '0') === '1' ? 'checked' : '' ?> class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Solicitar não indexação por bots</span>
                                <span class="text-[11px] text-slate-500">Insere tags <code>noindex, nofollow</code> e cabeçalho <code>X-Robots-Tag</code> em todas as páginas para evitar indexação no Google/buscadores.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Bloco B: Dica de 2FA TOTP -->
                <div class="p-4 rounded-2xl bg-emerald-50/70 border border-emerald-200 text-xs text-emerald-900 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <strong class="block"><i class="fas fa-mobile-alt mr-1"></i> Autenticação em 2 Etapas (TOTP) para Administradores</strong>
                        <span class="text-emerald-700">Você pode ativar ou gerenciar o 2FA via aplicativo autenticador diretamente no seu perfil.</span>
                    </div>
                    <a href="<?= url('perfil') ?>" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold whitespace-nowrap">
                        Configurar 2FA no Perfil &rarr;
                    </a>
                </div>

                <!-- Bloco C: Cloudflare Turnstile -->
                <div class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-200/80 text-xs text-indigo-900 space-y-2">
                    <div class="font-bold flex items-center gap-1.5">
                        <i class="fab fa-cloudflare text-base text-amber-500"></i> Proteção Anti-Robô Cloudflare Turnstile
                    </div>
                    <p class="text-indigo-800/90 leading-relaxed">
                        Protege a tela de login contra bots e ataques automatizados usando suas chaves do painel <strong>Cloudflare &gt; Turnstile</strong>.
                    </p>
                </div>

                <div class="pt-1">
                    <label class="flex items-center text-xs text-slate-800 font-bold cursor-pointer">
                        <input type="checkbox" name="turnstile_enabled" value="1" <?= ($settings['turnstile_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mr-2.5 w-4 h-4">
                        <span>Habilitar Verificação Cloudflare Turnstile na Tela de Login</span>
                    </label>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Chave do Site (Site Key)</label>
                        <input type="text" name="turnstile_site_key" value="<?= htmlspecialchars($settings['turnstile_site_key'] ?? '') ?>" placeholder="0x4AAAAAA..." class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Chave Secreta (Secret Key)</label>
                        <input type="password" name="turnstile_secret_key" value="<?= htmlspecialchars($settings['turnstile_secret_key'] ?? '') ?>" placeholder="0x4AAAAAA..." class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                    </div>
                </div>

                <!-- Bloco D: Certificado Digital do Navegador (mTLS) -->
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                    <div class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                        <i class="fas fa-id-card text-emerald-600"></i> Certificado Digital no Navegador (mTLS VirtualHost)
                    </div>
                    <p class="text-[11px] text-slate-500">
                        Para evitar bloqueios TLS 1.3 (Post-Handshake Authentication), o seletor de certificados A1/A3 utiliza um VirtualHost SSL dedicado na porta configurada abaixo (padrão <code>8443</code>).
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 uppercase mb-1">Porta mTLS (Apache)</label>
                            <input type="text" name="mtls_port" value="<?= htmlspecialchars($settings['mtls_port'] ?? '8443') ?>" placeholder="8443" class="w-full text-xs rounded-xl font-mono">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-semibold text-slate-700 uppercase mb-1">URL mTLS Personalizada (Opcional)</label>
                            <input type="url" name="mtls_url" value="<?= htmlspecialchars($settings['mtls_url'] ?? '') ?>" placeholder="Ex: https://cert.seudominio.com/public/cert-auth/" class="w-full text-xs rounded-xl font-mono">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md transition">
                        <i class="fas fa-save mr-1.5"></i> Salvar Configurações de Segurança
                    </button>
                </div>
            </form>
        </div>

    <!-- ======================================================== -->
    <!-- ABA 6: LICENCIAMENTO & TRAVA DO SISTEMA                 -->
    <!-- ======================================================== -->
    <?php elseif ($activeTab === 'licenca'): ?>
        <div class="space-y-6">
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
                <div class="border-b border-slate-100 pb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-certificate text-indigo-600"></i> Licenciamento & Integridade
                        </h2>
                        <p class="text-xs text-slate-400">Validação contínua automática a cada 5 minutos contra o servidor oficial.</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?= $isSystemLocked ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' ?>">
                        <span class="w-2 h-2 rounded-full <?= $isSystemLocked ? 'bg-amber-500' : 'bg-emerald-500' ?>"></span>
                        <?= $isSystemLocked ? 'Sistema Travado / Não Licenciado' : 'Licença Verificada e Ativa' ?>
                    </span>
                </div>

                <!-- Resumo criptografado da instância e verificação NTP -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-slate-400 block uppercase font-semibold text-[10px]">ID da Instância</span>
                        <span class="font-mono font-bold text-slate-800"><?= htmlspecialchars($licenseSummary['fingerprint'] ?? '-') ?></span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-slate-400 block uppercase font-semibold text-[10px]">Validade da Licença</span>
                        <span class="font-bold text-emerald-700"><?= htmlspecialchars($licenseSummary['expires_at'] ?? 'Não definida') ?></span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-slate-400 block uppercase font-semibold text-[10px]">Localidade do CNPJ / Fuso</span>
                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($licenseSummary['cnpj_location'] ?? '-') ?> (<?= htmlspecialchars($licenseSummary['cnpj_timezone'] ?? '-') ?>)</span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-slate-400 block uppercase font-semibold text-[10px]">Última Hora Oficial (NTP)</span>
                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($licenseSummary['ntp_local_datetime'] ?? '-') ?></span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-slate-400 block uppercase font-semibold text-[10px]">Servidor NTP Utilizado</span>
                        <span class="font-mono text-slate-700"><?= htmlspecialchars($licenseSummary['ntp_server'] ?? '-') ?></span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-slate-400 block uppercase font-semibold text-[10px]">Próxima Revalidação</span>
                        <span class="font-semibold text-indigo-700"><?= (int)($licenseSummary['next_check_in'] ?? 0) ?> seg (ciclo 5 min)</span>
                    </div>
                </div>

                <!-- Formulário de Chave de Licença -->
                <form method="POST" action="<?= url('configuracoes/licenca') ?>" class="space-y-4 max-w-xl">
                    <?= csrf_field() ?>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Chave de Licença Oficial (16 dígitos)</label>
                        <input type="text" name="license_key" required maxlength="19"
                            value="<?= htmlspecialchars($settings['license_key'] ?? '') ?>"
                            placeholder="XXXX-XXXX-XXXX-XXXX"
                            oninput="let c=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'').substring(0,16);let p=c.match(/.{1,4}/g);this.value=p?p.join('-'):'';"
                            class="w-full text-sm rounded-xl font-mono uppercase tracking-widest">
                    </div>

                    <div class="flex flex-wrap justify-between items-center gap-3 pt-2">
                        <button type="button" onclick="openSettingsBuyModal()" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-md transition inline-flex items-center gap-1.5">
                            <i class="fas fa-shopping-cart"></i> Comprar Licença
                        </button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md transition">
                            <i class="fas fa-shield-check mr-1.5"></i> Validar & Ativar Licença
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <!-- ======================================================== -->
    <!-- ABA 7: ATUALIZAÇÕES OFICIAIS DO SISTEMA (CRIPTOGRAFADO)  -->
    <!-- ======================================================== -->
    <?php elseif (in_array($activeTab, ['atualizacoes', 'github'], true)): ?>
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-cloud-download-alt text-indigo-600 text-lg"></i> Central de Atualizações do Sistema
                </h2>
                <p class="text-xs text-slate-400">Verifique se existem atualizações disponíveis comparando os arquivos locais com o pacote oficial criptografado.</p>
            </div>

            <!-- Informativo Obrigatório de Backup Frequente -->
            <div class="p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-950 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-lg shrink-0 mt-0.5">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="text-xs space-y-1">
                        <div class="font-bold text-sm text-amber-900">Recomendação Importante: Realize Backups com Frequência!</div>
                        <p class="text-amber-800 leading-relaxed">
                            Antes de aplicar qualquer atualização e em sua rotina diária/semanal, faça um <strong>backup completo</strong> do banco de dados MySQL e das pastas <code>config/</code> (incluindo <code>vault/</code>) e <code>public/uploads/</code>.
                        </p>
                    </div>
                </div>
                <a href="https://github.com/chacalbl4ck/reqmaster/blob/main/BACKUP.md" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-sm transition shrink-0">
                    <i class="fas fa-book-open"></i> Guia de Backup Completo
                </a>
            </div>

            <div class="space-y-4 max-w-2xl">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-slate-400 block uppercase font-semibold text-[10px]">Build / Commit Local</span>
                        <span class="font-mono font-bold text-slate-800"><?= htmlspecialchars($updateLocalStatus['commit_short'] ?? 'Base Local') ?></span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-slate-400 block uppercase font-semibold text-[10px]">Última Sincronização</span>
                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($updateLocalStatus['updated_at'] ?? 'Nunca') ?></span>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="text-slate-400 block uppercase font-semibold text-[10px]">Versão & PHP</span>
                        <span class="font-semibold text-slate-800">v<?= APP_VERSION ?> / PHP <?= PHP_VERSION ?></span>
                    </div>
                </div>

                <button type="button" onclick="checkSystemUpdates()" id="btnCheckUpdates" class="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                    <i class="fas fa-sync-alt" id="updateSpinner"></i>
                    <span>Verificar Atualizações Disponíveis</span>
                </button>

                <!-- Resultado da verificação -->
                <div id="updateResultBox" class="hidden p-5 rounded-2xl border text-xs space-y-3 transition-all"></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Gerenciamento de Modal de Usuário
    function openUserModal() {
        document.getElementById('userModalTitle').textContent = 'Cadastrar Novo Usuário';
        document.getElementById('userId').value = '0';
        document.getElementById('userName').value = '';
        document.getElementById('userEmail').value = '';
        document.getElementById('userPhone').value = '';
        document.getElementById('userDept').value = 'Geral';
        document.getElementById('userRole').value = 'solicitante';
        document.getElementById('userPassword').value = '';
        document.getElementById('userActive').checked = true;
        document.getElementById('pwdRequired').classList.remove('hidden');
        document.getElementById('pwdHint').classList.add('hidden');
        document.getElementById('userPassword').required = true;
        document.getElementById('userModal').classList.remove('hidden');
    }

    function editUser(user) {
        document.getElementById('userModalTitle').textContent = 'Editar Usuário: ' + user.name;
        document.getElementById('userId').value = user.id;
        document.getElementById('userName').value = user.name;
        document.getElementById('userEmail').value = user.email;
        document.getElementById('userPhone').value = user.phone || '';
        document.getElementById('userDept').value = user.department;
        document.getElementById('userRole').value = user.role;
        document.getElementById('userPassword').value = '';
        document.getElementById('userPassword').required = false;
        document.getElementById('pwdRequired').classList.add('hidden');
        document.getElementById('pwdHint').classList.remove('hidden');
        document.getElementById('userActive').checked = user.active == 1;
        document.getElementById('userModal').classList.remove('hidden');
    }

    function closeUserModal() {
        document.getElementById('userModal').classList.add('hidden');
    }

    // Modal de Resetar Senha
    function openResetPasswordModal(id, name) {
        document.getElementById('resetUserId').value = id;
        document.getElementById('resetUserName').textContent = name;
        document.getElementById('resetPasswordModal').classList.remove('hidden');
    }

    function closeResetPasswordModal() {
        document.getElementById('resetPasswordModal').classList.add('hidden');
    }

    function toggleWebOptions() {
        const cb = document.getElementById('isWebHostedCheck');
        const box = document.getElementById('webHostedSubOptions');
        if (cb && box) {
            box.classList.toggle('hidden', !cb.checked);
        }
    }

    function applyColorPreset(tp, ts, ta, rp, rt, rb) {
        document.getElementById('cThemePrimary').value = tp;
        document.getElementById('cThemeSecondary').value = ts;
        document.getElementById('cThemeAccent').value = ta;
        document.getElementById('cReqPrimary').value = rp;
        document.getElementById('cReqTint').value = rt;
        document.getElementById('cReqBorder').value = rb;
    }

    function checkSystemUpdates() {
        const box = document.getElementById('updateResultBox');
        const spinner = document.getElementById('updateSpinner');

        spinner.classList.add('fa-spin');
        box.classList.remove('hidden');
        box.className = 'p-5 rounded-2xl border border-slate-200 bg-slate-50 text-slate-600 text-xs space-y-2';
        box.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Comparando arquivos do servidor com a base oficial criptografada...';

        fetch('<?= url('configuracoes/github/verificar') ?>')
            .then(res => res.json())
            .then(data => {
                spinner.classList.remove('fa-spin');
                if (!data.success) {
                    box.className = 'p-5 rounded-2xl border border-rose-200 bg-rose-50 text-rose-900 text-xs space-y-1';
                    box.innerHTML = `<div class="font-bold"><i class="fas fa-exclamation-circle text-rose-600 mr-1"></i> Erro na verificação:</div><p>${data.message}</p>`;
                    return;
                }

                if (!data.has_updates) {
                    box.className = 'p-5 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-950 text-xs space-y-2';
                    box.innerHTML = `
                        <div class="font-bold text-sm text-emerald-800 flex items-center gap-2">
                            <i class="fas fa-check-circle text-emerald-600"></i> Sistema Totalmente Atualizado!
                        </div>
                        <p>Todos os ${data.total_remote} arquivos monitorados correspondem exatamente à última versão (Build <code>${data.latest_commit}</code>).</p>
                    `;
                    return;
                }

                let filesHtml = '';
                data.files_to_update.forEach(f => {
                    const badge = f.status === 'added'
                        ? '<span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 font-bold text-[10px]">NOVO</span>'
                        : '<span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 font-bold text-[10px]">ATUALIZADO</span>';
                    filesHtml += `<li class="flex items-center justify-between py-1 border-b border-slate-100 last:border-0 font-mono text-[11px]"><span>${f.path}</span>${badge}</li>`;
                });

                box.className = 'p-5 rounded-2xl border border-indigo-200 bg-indigo-50/70 text-slate-800 text-xs space-y-3';
                box.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-sm text-indigo-900 flex items-center gap-2">
                            <i class="fas fa-download text-indigo-600"></i> ${data.files_to_update.length} arquivo(s) disponível(is) para atualização
                        </div>
                        <span class="font-mono text-[11px] bg-white px-2 py-0.5 rounded border border-indigo-200">Build ${data.latest_commit}</span>
                    </div>
                    <p class="text-slate-600"><strong>Notas da Build:</strong> ${data.commit_message} (${data.commit_date})</p>
                    <div class="bg-white rounded-xl border border-slate-200 p-3 max-h-52 overflow-y-auto">
                        <ul class="space-y-0.5">${filesHtml}</ul>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" onclick="document.getElementById('updateResultBox').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-700 font-semibold hover:bg-slate-50">
                            Não Atualizar Agora
                        </button>
                        <button type="button" onclick="applySystemUpdates()" id="btnApplyUpdates" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-sm flex items-center gap-1.5">
                            <i class="fas fa-bolt"></i> Sim, Colocar em Manutenção e Atualizar
                        </button>
                    </div>
                `;
            })
            .catch(() => {
                spinner.classList.remove('fa-spin');
                box.className = 'p-5 rounded-2xl border border-rose-200 bg-rose-50 text-rose-900 text-xs space-y-1';
                box.innerHTML = `<div class="font-bold">Erro de conexão:</div><p>Não foi possível verificar atualizações.</p>`;
            });
    }

    function applySystemUpdates() {
        if (!confirm('O site entrará brevemente em modo de manutenção para substituir os arquivos atualizados. Deseja prosseguir?')) {
            return;
        }
        const btn = document.getElementById('btnApplyUpdates');
        const box = document.getElementById('updateResultBox');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Atualizando em Modo Manutenção...';
        }

        const fd = new FormData();
        fd.append('csrf_token', '<?= csrf_token() ?>');

        fetch('<?= url('configuracoes/atualizacoes/aplicar') ?>', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                box.className = 'p-5 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-950 text-xs space-y-2';
                box.innerHTML = `
                    <div class="font-bold text-sm text-emerald-800 flex items-center gap-2">
                        <i class="fas fa-check-circle text-emerald-600"></i> ${data.message}
                    </div>
                    <p>Modo manutenção desativado e listagem criptografada do servidor atualizada.</p>
                    <button type="button" onclick="window.location.reload()" class="mt-2 px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold">
                        Recarregar Sistema
                    </button>
                `;
            } else {
                box.className = 'p-5 rounded-2xl border border-rose-200 bg-rose-50 text-rose-900 text-xs space-y-1';
                box.innerHTML = `<div class="font-bold">Falha ao aplicar atualização:</div><p>${data.message}</p>`;
            }
        })
        .catch(() => {
            box.className = 'p-5 rounded-2xl border border-rose-200 bg-rose-50 text-rose-900 text-xs space-y-1';
            box.innerHTML = `<div class="font-bold">Erro durante a atualização.</div>`;
        });
    }

    function openSettingsBuyModal() {
        document.getElementById('settingsBuyModal').classList.remove('hidden');
    }
    function closeSettingsBuyModal() {
        document.getElementById('settingsBuyModal').classList.add('hidden');
    }
</script>

<!-- Modal Comprar Licença -->
<div id="settingsBuyModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="max-w-lg w-full bg-white rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-5">
        <div class="flex justify-between items-start border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Comprar Licença ReqMaster</h3>
                    <p class="text-xs text-slate-500">CRM Soluções em Tecnologia &bull; Caio Monteiro &bull; 2027</p>
                </div>
            </div>
            <button type="button" onclick="closeSettingsBuyModal()" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-950 space-y-1.5">
            <div class="flex justify-between items-center">
                <span class="font-bold uppercase text-emerald-800">Licença Anual Oficial</span>
                <span class="text-base font-black text-emerald-700">R$ 400,00 / ano</span>
            </div>
            <p>Usuários e empresas ilimitadas &bull; Suporte de Seg a Sex, das 9h às 17h.</p>
            <p class="text-rose-700 font-semibold">Software pago, não é de uso livre e é protegido por direitos autorais.</p>
        </div>

        <div class="space-y-2.5 text-xs">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-2">
                <div>
                    <span class="text-[10px] font-semibold uppercase text-slate-400 block">E-mail para Suporte e Compra</span>
                    <a href="mailto:caiormleo@outlook.com.br?subject=Compra%20de%20Licen%C3%A7a%20ReqMaster" class="font-bold text-indigo-700 hover:underline text-sm">
                        caiormleo@outlook.com.br
                    </a>
                </div>
                <a href="mailto:caiormleo@outlook.com.br?subject=Compra%20de%20Licen%C3%A7a%20ReqMaster" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold">
                    Enviar E-mail
                </a>
            </div>
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-2">
                <div>
                    <span class="text-[10px] font-semibold uppercase text-slate-400 block">WhatsApp</span>
                    <span class="font-bold text-emerald-700 text-sm">@caior.monteiro</span>
                </div>
                <span class="text-slate-500 font-semibold"><i class="fab fa-whatsapp text-emerald-600 mr-1"></i> @caior.monteiro</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-2">
                <div>
                    <span class="text-[10px] font-semibold uppercase text-slate-400 block">GitHub</span>
                    <a href="https://github.com/chacalbl4ck/reqmaster" target="_blank" rel="noopener noreferrer" class="font-bold text-slate-800 hover:underline">
                        chacalbl4ck/reqmaster
                    </a>
                </div>
                <span class="text-[10px] font-bold text-indigo-700 uppercase">DEUS SEJA LOUVADO</span>
            </div>
        </div>

        <div class="flex justify-end pt-2 border-t border-slate-100">
            <button type="button" onclick="closeSettingsBuyModal()" class="px-5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                Fechar
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
