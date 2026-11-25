<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Relatório de Uso, Manutenção & Consumo (Km/L)</h1>
            <p class="text-xs sm:text-sm text-slate-500">Analise quilometragem percorrida, necessidade de manutenção (geral e subprodutos), abastecimentos e rendimento médio em Km/L.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= url('relatorios') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
                <i class="fas fa-chart-bar"></i> Relatório Geral
            </a>
            <a href="<?= url('relatorios/manutencoes') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-xl text-xs font-semibold transition">
                <i class="fas fa-wrench"></i> Manutenções
            </a>
            <a href="<?= url('relatorios/odometros') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-50 hover:bg-sky-100 text-sky-800 border border-sky-200 rounded-xl text-xs font-semibold transition">
                <i class="fas fa-gauge-high"></i> Odômetros
            </a>
            <a href="<?= url('relatorios/veiculos-consumo-csv?' . http_build_query($filters)) ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                <i class="fas fa-file-excel"></i> Exportar CSV
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
        <form method="GET" action="<?= url('relatorios/veiculos-consumo') ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Veículo (Placa / Nome)</label>
                <select name="product_id" class="w-full text-sm rounded-xl border-slate-200">
                    <option value="">Todos os Veículos</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= (string)($filters['product_id'] ?? '') === (string)$v['id'] ? 'selected' : '' ?>>
                            <?= !empty($v['license_plate']) ? '[' . htmlspecialchars($v['license_plate']) . '] ' : '' ?><?= htmlspecialchars($v['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Período Inicial</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" class="w-full text-sm rounded-xl">
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Período Final</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" class="w-full text-sm rounded-xl">
                </div>
                <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold self-end h-[38px]">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Informativo de Cálculo -->
    <div class="p-4 rounded-2xl bg-sky-50 border border-sky-200 text-xs text-sky-950 flex items-start gap-3">
        <i class="fas fa-gas-pump text-sky-600 text-base mt-0.5 shrink-0"></i>
        <div>
            <strong>Como o sistema calcula Km/L e necessidade de combustível / manutenção:</strong>
            O consumo em <strong>Km/L</strong> divide a distância percorrida entre o odômetro inicial e final pelo total de litros informados tanto nos registros de quilometragem quanto em requisições de combustível (unidade <code>L</code> / gasolina / etanol / diesel). Para veículos com alta rodagem ou manutenção vencida, use os atalhos ao lado para abrir requisição de combustível ou manutenção.
        </div>
    </div>

    <!-- Tabela Consolidada -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase">
                        <th class="py-3 px-4">Veículo & Placa</th>
                        <th class="py-3 px-4 text-right">Odômetro (Início &rarr; Fim)</th>
                        <th class="py-3 px-4 text-right">Km Percorridos</th>
                        <th class="py-3 px-4 text-right">Total Litros</th>
                        <th class="py-3 px-4 text-right">Consumo Médio</th>
                        <th class="py-3 px-4">Necessidade de Manutenção & Subprodutos</th>
                        <th class="py-3 px-4 text-center">Ações Rápidas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($usageList)): ?>
                        <tr><td colspan="7" class="py-10 text-center text-slate-400">Nenhum veículo cadastrado para análise.</td></tr>
                    <?php else: ?>
                        <?php foreach ($usageList as $u): ?>
                            <tr class="hover:bg-slate-50 <?= !empty($u['needs_maintenance']) ? 'bg-rose-50/30' : '' ?>">
                                <td class="py-3 px-4">
                                    <a href="<?= url('produtos/ver?id=' . (int)$u['product_id']) ?>" class="font-bold text-slate-900 hover:text-indigo-700 block">
                                        <?= htmlspecialchars($u['name']) ?>
                                    </a>
                                    <?php if (!empty($u['license_plate'])): ?>
                                        <span class="font-mono text-[11px] text-slate-500">Placa: <?= htmlspecialchars($u['license_plate']) ?></span>
                                    <?php endif; ?>
                                    <span class="block text-[10px] text-slate-400"><?= (int)$u['readings_count'] ?> leitura(s) no período</span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-slate-700">
                                    <?= $u['initial_km'] !== null ? number_format((int)$u['initial_km'], 0, ',', '.') : '-' ?>
                                    &rarr;
                                    <strong><?= $u['final_km'] !== null ? number_format((int)$u['final_km'], 0, ',', '.') : '-' ?></strong> km
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-indigo-700">
                                    <?= number_format((int)$u['km_driven'], 0, ',', '.') ?> km
                                </td>
                                <td class="py-3 px-4 text-right font-mono">
                                    <strong class="text-slate-800"><?= number_format((float)$u['total_liters'], 2, ',', '.') ?> L</strong>
                                    <?php if ($u['liters_from_logs'] > 0 || $u['liters_from_reqs'] > 0): ?>
                                        <span class="block text-[10px] text-slate-400">
                                            Odôm: <?= number_format((float)$u['liters_from_logs'], 1, ',', '.') ?>L &bull; Req: <?= number_format((float)$u['liters_from_reqs'], 1, ',', '.') ?>L
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right font-mono">
                                    <?php if ($u['km_per_liter'] !== null): ?>
                                        <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 font-extrabold border border-emerald-200">
                                            <?= number_format((float)$u['km_per_liter'], 2, ',', '.') ?> km/L
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Sem dados suficientes</span>
                                    <?php endif; ?>
                                    <?php if ($u['km_since_last_fuel'] !== null && $u['km_since_last_fuel'] > 350): ?>
                                        <span class="block text-[10px] text-amber-700 font-semibold mt-1">
                                            <?= number_format((int)$u['km_since_last_fuel'], 0, ',', '.') ?> km desde último abastecimento
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 space-y-1">
                                    <?php if (!empty($u['needs_maintenance'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">
                                            <i class="fas fa-exclamation-triangle"></i> Manutenção Necessária
                                        </span>
                                        <?php if (!empty($u['overdue_subitems'])): ?>
                                            <div class="text-[11px] text-rose-800">
                                                Subprodutos:
                                                <?php foreach ($u['overdue_subitems'] as $os): ?>
                                                    <strong><?= htmlspecialchars($os['name']) ?></strong> (<?= htmlspecialchars($os['overdue_label']) ?>);
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fas fa-check-circle"></i> Em Dia
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap space-x-1">
                                    <a href="<?= url('requisicoes/nova?type=material') ?>" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[10px] font-bold" title="Solicitar Combustível / Insumos">
                                        <i class="fas fa-gas-pump"></i> Combustível
                                    </a>
                                    <a href="<?= url('requisicoes/nova?type=servico&repair=1&maintenance_type=preventiva&product_id=' . (int)$u['product_id']) ?>" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 text-[10px] font-bold" title="Solicitar Manutenção">
                                        <i class="fas fa-wrench"></i> OS
                                    </a>
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
