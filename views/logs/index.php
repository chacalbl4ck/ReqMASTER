<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';

$queryStr = http_build_query(array_filter($filters));
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fas fa-clipboard-check text-indigo-600"></i> Logs & Auditoria do Sistema
            </h1>
            <p class="text-xs sm:text-sm text-slate-500">Monitoramento completo de acessos, segurança, licenciamento, alterações administrativas e requisições.</p>
        </div>
        <a href="<?= url('logs/exportar-csv' . ($queryStr ? '?' . $queryStr : '')) ?>" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs sm:text-sm font-semibold shadow-sm transition flex items-center gap-2">
            <i class="fas fa-file-csv"></i> Exportar CSV
        </a>
    </div>

    <!-- KPIs de Auditoria -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-400 uppercase block">Total de Registros</span>
            <span class="text-2xl font-black text-slate-900 mt-1 block"><?= number_format((int)$stats['total'], 0, ',', '.') ?></span>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-400 uppercase block">Últimas 24 Horas</span>
            <span class="text-2xl font-black text-indigo-600 mt-1 block"><?= number_format((int)$stats['last_24h'], 0, ',', '.') ?></span>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-400 uppercase block">IPs Distintos (7d)</span>
            <span class="text-2xl font-black text-slate-800 mt-1 block"><?= number_format((int)$stats['unique_ips'], 0, ',', '.') ?></span>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
            <span class="text-[11px] font-semibold text-slate-400 uppercase block">Eventos de Segurança/Licença</span>
            <span class="text-2xl font-black text-amber-600 mt-1 block"><?= number_format((int)$stats['security_events'], 0, ',', '.') ?></span>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" action="<?= url('logs') ?>" class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
        <div class="lg:col-span-2">
            <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Buscar (Descrição, IP, Código)</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Ex: Login, REQ-2026, 127.0.0.1..." class="w-full py-1.5 px-2.5 text-xs rounded-lg">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Tipo de Ação</label>
            <select name="action" class="w-full py-1.5 px-2.5 text-xs rounded-lg">
                <option value="">Todas as Ações</option>
                <?php foreach ($actions as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= ($filters['action'] ?? '') === $act ? 'selected' : '' ?>>
                        <?= htmlspecialchars($act) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Usuário</label>
            <select name="user_id" class="w-full py-1.5 px-2.5 text-xs rounded-lg">
                <option value="">Todos</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (string)($filters['user_id'] ?? '') === (string)$u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">De / Até</label>
            <div class="flex gap-1">
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" class="w-1/2 py-1.5 px-1.5 text-[11px] rounded-lg">
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" class="w-1/2 py-1.5 px-1.5 text-[11px] rounded-lg">
            </div>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 py-2 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition">
                <i class="fas fa-filter mr-1"></i> Filtrar
            </button>
            <a href="<?= url('logs') ?>" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition" title="Limpar Filtros">
                <i class="fas fa-undo"></i>
            </a>
        </div>
    </form>

    <!-- Tabela de Logs -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Data / Hora</th>
                        <th class="py-3 px-4">Ação</th>
                        <th class="py-3 px-4">Usuário</th>
                        <th class="py-3 px-4">Descrição / Detalhes</th>
                        <th class="py-3 px-4">Vínculo</th>
                        <th class="py-3 px-4">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-slate-400">
                                Nenhum registro de log encontrado para os filtros selecionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <?php
                                $act = $l['action'];
                                $badgeClass = 'bg-slate-100 text-slate-700 border-slate-200';
                                if (str_contains($act, 'Falha') || str_contains($act, 'Exclusão') || str_contains($act, 'Rejeição')) {
                                    $badgeClass = 'bg-rose-50 text-rose-700 border-rose-200';
                                } elseif (str_contains($act, 'Assinatura') || str_contains($act, 'Aprovação') || str_contains($act, 'Licença')) {
                                    $badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                } elseif (str_contains($act, 'Edição') || str_contains($act, '2FA') || str_contains($act, 'Atualização')) {
                                    $badgeClass = 'bg-amber-50 text-amber-800 border-amber-200';
                                } elseif (str_contains($act, 'Login') || str_contains($act, 'Configurações')) {
                                    $badgeClass = 'bg-indigo-50 text-indigo-700 border-indigo-200';
                                }
                            ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4 whitespace-nowrap font-mono text-[11px] text-slate-500">
                                    <?= formatDateTime($l['created_at']) ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold border <?= $badgeClass ?>">
                                        <?= htmlspecialchars($act) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <?php if (!empty($l['user_name'])): ?>
                                        <span class="font-semibold text-slate-800 block"><?= htmlspecialchars($l['user_name']) ?></span>
                                        <span class="text-[10px] text-slate-400 uppercase"><?= htmlspecialchars($l['user_role'] ?? '') ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Sistema / Anônimo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-slate-700 leading-relaxed max-w-md">
                                    <?= htmlspecialchars($l['description']) ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <?php if (!empty($l['requisition_id'])): ?>
                                        <a href="<?= url('requisicoes/ver?id=' . $l['requisition_id']) ?>" class="text-indigo-600 hover:underline font-mono font-bold">
                                            <?= htmlspecialchars($l['requisition_code'] ?? ('#' . $l['requisition_id'])) ?>
                                        </a>
                                    <?php elseif (!empty($l['contract_id'])): ?>
                                        <a href="<?= url('contratos/ver?id=' . $l['contract_id']) ?>" class="text-purple-600 hover:underline font-mono font-bold">
                                            <?= htmlspecialchars($l['contract_number'] ?? ('CT#' . $l['contract_id'])) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap font-mono text-[11px] text-slate-500">
                                    <?= htmlspecialchars($l['ip_address'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
