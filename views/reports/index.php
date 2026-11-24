<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Relatórios Gerenciais</h1>
            <p class="text-xs sm:text-sm text-slate-500">Análise de gastos em materiais, serviços, fornecedores e contratos.</p>
        </div>
        <div>
            <!-- Botão de Exportação CSV para Excel -->
            <a href="<?= url('relatorios/exportar-csv?' . http_build_query($filters)) ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold shadow-sm transition">
                <i class="fas fa-file-excel"></i> Exportar para Excel (CSV)
            </a>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <form method="GET" action="<?= url('relatorios') ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="hidden" name="route" value="relatorios">

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Data Inicial</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" class="w-full px-3 py-1.5 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Data Final</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" class="w-full px-3 py-1.5 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Tipo</label>
                <select name="type" class="w-full py-1.5 px-2.5 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="material" <?= ($filters['type'] ?? '') === 'material' ? 'selected' : '' ?>>Materiais</option>
                    <option value="servico" <?= ($filters['type'] ?? '') === 'servico' ? 'selected' : '' ?>>Serviços</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Status</label>
                <select name="status" class="w-full py-1.5 px-2.5 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="aprovada" <?= ($filters['status'] ?? '') === 'aprovada' ? 'selected' : '' ?>>Aprovadas</option>
                    <option value="pendente" <?= ($filters['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                    <option value="concluida" <?= ($filters['status'] ?? '') === 'concluida' ? 'selected' : '' ?>>Concluídas</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                    Filtrar
                </button>
                <a href="<?= url('relatorios') ?>" class="py-1.5 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-xs font-medium transition text-center" title="Limpar">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Cards de Resumo do Período Filtrado -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold uppercase text-slate-400">Total de Requisições</span>
            <div class="text-3xl font-black text-slate-900 mt-1"><?= $totalItems ?></div>
            <span class="text-[11px] text-slate-400">No período selecionado</span>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold uppercase text-slate-400">Volume Financeiro Total</span>
            <div class="text-3xl font-black text-indigo-700 mt-1"><?= formatMoney($totalAmount) ?></div>
            <span class="text-[11px] text-slate-400">Soma dos valores das requisições filtradas</span>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold uppercase text-slate-400">Ticket Médio por Requisição</span>
            <div class="text-3xl font-black text-emerald-700 mt-1">
                <?= $totalItems > 0 ? formatMoney($totalAmount / $totalItems) : 'R$ 0,00' ?>
            </div>
            <span class="text-[11px] text-slate-400">Média por solicitação</span>
        </div>
    </div>

    <!-- Tabela Detalhada -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Código</th>
                        <th class="py-3 px-4">Data</th>
                        <th class="py-3 px-4">Tipo</th>
                        <th class="py-3 px-4">Título</th>
                        <th class="py-3 px-4">Para Quem (Fornecedor)</th>
                        <th class="py-3 px-4">Contrato</th>
                        <th class="py-3 px-4">Solicitante</th>
                        <th class="py-3 px-4 text-right">Valor Total</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($requisitions)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-8 text-slate-400">
                                Nenhuma requisição encontrada para os filtros selecionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requisitions as $r): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars($r['code']) ?></td>
                                <td class="py-3 px-4 text-slate-500"><?= formatDate($r['created_at']) ?></td>
                                <td class="py-3 px-4"><?= $r['type'] === 'servico' ? 'Serviço' : 'Material' ?></td>
                                <td class="py-3 px-4 font-medium text-slate-800"><?= htmlspecialchars($r['title']) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($r['supplier_name']) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($r['contract_number'] ?? '-') ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($r['requester_name']) ?></td>
                                <td class="py-3 px-4 text-right font-bold"><?= formatMoney($r['total_amount']) ?></td>
                                <td class="py-3 px-4 text-center"><?= statusBadge($r['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
