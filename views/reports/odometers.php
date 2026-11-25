<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Relatório de Registro de Odômetros</h1>
            <p class="text-xs sm:text-sm text-slate-500">Auditoria de quilometragens registradas por veículo, placa, período, distância percorrida e fotos do painel.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= url('relatorios') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
                <i class="fas fa-chart-bar"></i> Relatório Geral
            </a>
            <a href="<?= url('relatorios/manutencoes') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-xl text-xs font-semibold transition">
                <i class="fas fa-wrench"></i> Manutenções
            </a>
            <a href="<?= url('relatorios/veiculos-consumo') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-800 border border-indigo-200 rounded-xl text-xs font-semibold transition">
                <i class="fas fa-gas-pump"></i> Uso & Km/L
            </a>
            <a href="<?= url('relatorios/odometros-csv?' . http_build_query($filters)) ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                <i class="fas fa-file-excel"></i> Exportar CSV
            </a>
        </div>
    </div>

    <!-- Resumo -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase block">Total de Leituras</span>
            <span class="text-2xl font-black text-slate-900 mt-1 block"><?= count($records) ?></span>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase block">Distância Somada entre Leituras</span>
            <span class="text-2xl font-black text-indigo-700 mt-1 block font-mono"><?= number_format((int)$totalDeltaKm, 0, ',', '.') ?> km</span>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 uppercase block">Total de Litros Lançados</span>
            <span class="text-2xl font-black text-emerald-700 mt-1 block font-mono"><?= number_format((float)$totalLiters, 2, ',', '.') ?> L</span>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm">
        <form method="GET" action="<?= url('relatorios/odometros') ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
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
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Placa</label>
                <input type="text" name="license_plate" value="<?= htmlspecialchars($filters['license_plate'] ?? '') ?>" placeholder="Ex: ABC-1D23" class="w-full text-sm rounded-xl uppercase font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">De</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" class="w-full text-sm rounded-xl">
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Até</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" class="w-full text-sm rounded-xl">
                </div>
                <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold self-end h-[38px]">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Tabela de Leituras -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase">
                        <th class="py-3 px-4">Data / Hora</th>
                        <th class="py-3 px-4">Veículo & Placa</th>
                        <th class="py-3 px-4 text-right">Odômetro</th>
                        <th class="py-3 px-4 text-right">Km Percorrido (Delta)</th>
                        <th class="py-3 px-4 text-right">Litros</th>
                        <th class="py-3 px-4 text-center">Foto Odômetro</th>
                        <th class="py-3 px-4">Registrado por</th>
                        <th class="py-3 px-4">Observações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($records)): ?>
                        <tr><td colspan="8" class="py-10 text-center text-slate-400">Nenhum registro de odômetro encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="py-3 px-4 whitespace-nowrap text-slate-600"><?= formatDateTime($r['recorded_at']) ?></td>
                                <td class="py-3 px-4">
                                    <a href="<?= url('produtos/ver?id=' . (int)$r['product_id']) ?>" class="font-bold text-slate-900 hover:text-indigo-700 block">
                                        <?= htmlspecialchars($r['product_name']) ?>
                                    </a>
                                    <?php if (!empty($r['license_plate'])): ?>
                                        <span class="font-mono text-[11px] text-slate-500">Placa: <?= htmlspecialchars($r['license_plate']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-indigo-700"><?= number_format((int)$r['mileage_km'], 0, ',', '.') ?> km</td>
                                <td class="py-3 px-4 text-right font-mono text-slate-700">
                                    <?= $r['km_delta'] !== null ? '+' . number_format((int)$r['km_delta'], 0, ',', '.') . ' km' : '<span class="text-slate-300">-</span>' ?>
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-emerald-700">
                                    <?= !empty($r['fuel_liters']) ? number_format((float)$r['fuel_liters'], 2, ',', '.') . ' L' : '<span class="text-slate-300">-</span>' ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if (!empty($r['odometer_photo']) && file_exists(__DIR__ . '/../../public/' . $r['odometer_photo'])): ?>
                                        <a href="<?= asset($r['odometer_photo']) ?>" target="_blank">
                                            <img src="<?= asset($r['odometer_photo']) ?>" alt="Odômetro" class="w-12 h-9 object-cover rounded border border-slate-200 mx-auto">
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-slate-700"><?= htmlspecialchars($r['user_name'] ?? 'Sistema') ?></td>
                                <td class="py-3 px-4 text-slate-500"><?= htmlspecialchars($r['notes'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
