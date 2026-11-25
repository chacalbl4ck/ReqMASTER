<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Relatório de Manutenções & Reparos</h1>
            <p class="text-xs sm:text-sm text-slate-500">Histórico completo de reparos e manutenções preventivas vinculadas aos produtos e equipamentos.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= url('relatorios') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
                <i class="fas fa-chart-bar"></i> Relatório Geral
            </a>
            <a href="<?= url('relatorios/odometros') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-50 hover:bg-sky-100 text-sky-800 border border-sky-200 rounded-xl text-xs font-semibold transition">
                <i class="fas fa-gauge-high"></i> Odômetros
            </a>
            <a href="<?= url('relatorios/veiculos-consumo') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-800 border border-indigo-200 rounded-xl text-xs font-semibold transition">
                <i class="fas fa-gas-pump"></i> Uso & Km/L
            </a>
            <a href="<?= url('relatorios/manutencoes-csv?' . http_build_query($filters)) ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                <i class="fas fa-file-excel"></i> Exportar CSV
            </a>
        </div>
    </div>

    <!-- Resumo -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase block">Total de Ordens de Serviço</span>
            <span class="text-2xl font-black text-slate-900 mt-1 block"><?= (int)$totalOrders ?></span>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase block">Manutenções Concluídas</span>
            <span class="text-2xl font-black text-emerald-600 mt-1 block"><?= (int)$completedOrders ?></span>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase block">Investimento em Manutenção</span>
            <span class="text-2xl font-black text-indigo-700 mt-1 block"><?= formatMoney($totalMaintenanceCost) ?></span>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase block">Equipamentos Atrasados</span>
            <span class="text-2xl font-black <?= !empty($overdueProducts) ? 'text-rose-600' : 'text-slate-900' ?> mt-1 block">
                <?= count($overdueProducts ?? []) ?>
            </span>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
        <form method="GET" action="<?= url('relatorios/manutencoes') ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Produto / Equipamento / Veículo</label>
                <select name="product_id" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos os Produtos</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (string)($filters['product_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?><?= !empty($p['license_plate']) ? ' [' . htmlspecialchars($p['license_plate']) . ']' : (!empty($p['serial_number']) ? ' (N/S: ' . htmlspecialchars($p['serial_number']) . ')' : '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Subproduto do Veículo</label>
                <select name="subitem_name" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos os Subprodutos</option>
                    <?php foreach ($subitemNames ?? [] as $sn): ?>
                        <option value="<?= htmlspecialchars($sn) ?>" <?= ($filters['subitem_name'] ?? '') === $sn ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sn) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Manutenção Atrasada?</label>
                <select name="overdue_status" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todas (Atrasadas e Em Dia)</option>
                    <option value="overdue" <?= ($filters['overdue_status'] ?? '') === 'overdue' ? 'selected' : '' ?>>Somente Atrasada</option>
                    <option value="ok" <?= ($filters['overdue_status'] ?? '') === 'ok' ? 'selected' : '' ?>>Somente Em Dia</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Tipo</label>
                <select name="maintenance_type" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="reparo" <?= ($filters['maintenance_type'] ?? '') === 'reparo' ? 'selected' : '' ?>>Reparo / Corretiva</option>
                    <option value="preventiva" <?= ($filters['maintenance_type'] ?? '') === 'preventiva' ? 'selected' : '' ?>>Manutenção Preventiva</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Situação da OS</label>
                <select name="status" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todas</option>
                    <option value="pendente" <?= ($filters['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="aprovada" <?= ($filters['status'] ?? '') === 'aprovada' ? 'selected' : '' ?>>Aprovada</option>
                    <option value="em_execucao" <?= ($filters['status'] ?? '') === 'em_execucao' ? 'selected' : '' ?>>Em Execução</option>
                    <option value="concluida" <?= ($filters['status'] ?? '') === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                    <option value="rejeitada" <?= ($filters['status'] ?? '') === 'rejeitada' ? 'selected' : '' ?>>Rejeitada</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">De</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Até</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition h-[38px]">
                    <i class="fas fa-filter mr-1"></i> Filtrar
                </button>
                <a href="<?= url('relatorios/manutencoes') ?>" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Situação Atual dos Produtos & Subprodutos Filtrados -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center">
            <div>
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Situação Preventiva de Produtos & Subprodutos</h2>
                <p class="text-xs text-slate-400">Visão consolidada de atraso por prazo em dias, Km geral ou Km específico de cada subproduto.</p>
            </div>
            <span class="text-xs font-semibold text-slate-500"><?= count($productStatuses ?? []) ?> item(ns)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase">
                        <th class="py-2.5 px-4">Produto / Veículo</th>
                        <th class="py-2.5 px-4">Subprodutos & Regras</th>
                        <th class="py-2.5 px-4 text-center">Preventiva</th>
                        <th class="py-2.5 px-4 text-center">Situação Atual</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($productStatuses)): ?>
                        <tr><td colspan="4" class="py-6 text-center text-slate-400">Nenhum produto/subproduto encontrado com os filtros aplicados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($productStatuses as $ps): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-4">
                                    <a href="<?= url('produtos/ver?id=' . (int)$ps['id']) ?>" class="font-bold text-slate-900 hover:text-indigo-700">
                                        <?= htmlspecialchars($ps['name']) ?>
                                    </a>
                                    <?php if (!empty($ps['license_plate'])): ?>
                                        <span class="ml-1 font-mono text-[11px] text-slate-500">[<?= htmlspecialchars($ps['license_plate']) ?>]</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-4">
                                    <?php if (empty($ps['evaluated_subitems'])): ?>
                                        <span class="text-slate-400">-</span>
                                    <?php else: ?>
                                        <div class="flex flex-wrap gap-1.5">
                                            <?php foreach ($ps['evaluated_subitems'] as $es): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-semibold <?= !empty($es['is_overdue']) ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-slate-100 text-slate-700' ?>">
                                                    <?= htmlspecialchars($es['name']) ?>
                                                    <?php if (!empty($es['effective_interval_km'])): ?>
                                                        (<?= number_format((int)$es['km_elapsed'], 0, ',', '.') ?>/<?= number_format((int)$es['effective_interval_km'], 0, ',', '.') ?> km)
                                                    <?php endif; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-4 text-center text-slate-600">
                                    <?php if (!empty($ps['maintenance_interval_days'])): ?>
                                        <div><?= (int)$ps['maintenance_interval_days'] ?> dias</div>
                                    <?php endif; ?>
                                    <?php if (!empty($ps['maintenance_interval_km'])): ?>
                                        <div><?= number_format((int)$ps['maintenance_interval_km'], 0, ',', '.') ?> km</div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <?php if (!empty($ps['effective_overdue'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800">
                                            <i class="fas fa-exclamation-triangle"></i> Atrasada
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fas fa-check-circle"></i> Em Dia
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabela de Ordens de Manutenção / Reparo -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4">OS / Requisição</th>
                        <th class="py-3 px-4">Produto / Equipamento</th>
                        <th class="py-3 px-4">Tipo</th>
                        <th class="py-3 px-4">Prestador</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Abertura</th>
                        <th class="py-3 px-4">Conclusão</th>
                        <th class="py-3 px-4 text-right">Valor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                Nenhum registro de manutenção ou reparo encontrado para os filtros informados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 px-4">
                                    <a href="<?= url('requisicoes/ver?id=' . (int)$r['requisition_id']) ?>" class="font-bold text-indigo-700 hover:underline block">
                                        <?= htmlspecialchars($r['code']) ?>
                                    </a>
                                    <span class="text-xs text-slate-500"><?= htmlspecialchars($r['title']) ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="<?= url('produtos/ver?id=' . (int)$r['product_id']) ?>" class="font-semibold text-slate-900 hover:text-indigo-700 block">
                                        <?= htmlspecialchars($r['product_name']) ?>
                                    </a>
                                    <?php if (!empty($r['serial_number'])): ?>
                                        <span class="text-[11px] font-mono text-slate-500">N/S: <?= htmlspecialchars($r['serial_number']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if (($r['maintenance_type'] ?? 'reparo') === 'preventiva'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                            Preventiva
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            Reparo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-xs text-slate-700">
                                    <?= htmlspecialchars($r['supplier_name'] ?? '-') ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?= statusBadge($r['status']) ?>
                                </td>
                                <td class="py-3 px-4 text-xs text-slate-600">
                                    <?= formatDate($r['created_at']) ?>
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    <?php if (!empty($r['completed_at'])): ?>
                                        <span class="font-semibold text-emerald-700"><?= formatDateTime($r['completed_at']) ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-400">Em aberto</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right font-semibold text-slate-800">
                                    <?= !empty($r['has_values']) ? formatMoney($r['total_amount']) : '<span class="text-xs text-slate-400 font-normal">Sem valor</span>' ?>
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
