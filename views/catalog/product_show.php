<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';

$isVehicle = !empty($product['is_vehicle']);
$intervalDays = !empty($product['maintenance_interval_days']) ? (int)$product['maintenance_interval_days'] : null;
$lastMaint = $product['last_maintenance_at'] ?? null;
$daysElapsed = $lastMaint ? (int)((time() - strtotime($lastMaint)) / 86400) : null;
$isDaysOverdue = ($intervalDays && $daysElapsed !== null && $daysElapsed > $intervalDays);
$nextDueDate = ($intervalDays && $lastMaint) ? date('Y-m-d', strtotime($lastMaint . " +{$intervalDays} days")) : null;

$currentKm = $product['current_km'] !== null ? (int)$product['current_km'] : null;
$intervalKm = ($isVehicle && !empty($product['maintenance_interval_km'])) ? (int)$product['maintenance_interval_km'] : null;
$lastMaintKm = $product['last_maintenance_km'] !== null ? (int)$product['last_maintenance_km'] : 0;
$kmElapsed = ($isVehicle && $currentKm !== null) ? max(0, $currentKm - $lastMaintKm) : null;
$isKmOverdue = ($intervalKm && $kmElapsed !== null && $kmElapsed >= $intervalKm);
$nextDueKm = $intervalKm ? ($lastMaintKm + $intervalKm) : null;
$isOverdue = $isDaysOverdue || $isKmOverdue;
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <a href="<?= url('produtos') ?>" class="text-xs font-semibold text-slate-400 hover:text-slate-700">
                    <i class="fas fa-arrow-left"></i> Produtos & Veículos
                </a>
                <?php if ($isVehicle): ?>
                    <span class="text-slate-300">&bull;</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700"><i class="fas fa-car mr-1"></i>Veículo</span>
                <?php endif; ?>
                <?php if (!empty($product['license_plate'])): ?>
                    <span class="text-slate-300">&bull;</span>
                    <span class="text-xs font-mono font-bold text-slate-800">Placa: <?= htmlspecialchars($product['license_plate']) ?></span>
                <?php endif; ?>
                <?php if (!empty($product['chassis'])): ?>
                    <span class="text-slate-300">&bull;</span>
                    <span class="text-xs font-mono text-slate-600">Chassi: <?= htmlspecialchars($product['chassis']) ?></span>
                <?php endif; ?>
                <?php if (!empty($product['serial_number'])): ?>
                    <span class="text-slate-300">&bull;</span>
                    <span class="text-xs font-mono font-bold text-indigo-700">S/N: <?= htmlspecialchars($product['serial_number']) ?></span>
                <?php endif; ?>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($product['name']) ?></h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <?php if ($isVehicle): ?>
                <a href="<?= url('veiculos/quilometragem?product_id=' . $product['id']) ?>" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-sm transition">
                    <i class="fas fa-gauge-high mr-1"></i> Registrar Quilometragem
                </a>
            <?php endif; ?>
            <a href="<?= url('requisicoes/nova?type=servico&repair=1&product_id=' . $product['id']) ?>" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-sm transition">
                <i class="fas fa-tools mr-1"></i> Abrir OS de Reparo / Manutenção
            </a>
            <a href="<?= url('relatorios/manutencoes?product_id=' . $product['id']) ?>" class="px-4 py-2 rounded-xl bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-bold transition">
                <i class="fas fa-chart-line mr-1"></i> Relatório de Manutenções
            </a>
        </div>
    </div>

    <?php if ($isOverdue): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border-2 border-rose-300 text-rose-900 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 text-xs">
                <i class="fas fa-exclamation-triangle text-2xl text-rose-600 shrink-0"></i>
                <div class="space-y-0.5">
                    <strong class="text-sm block">Manutenção Preventiva Atrasada / Atingida!</strong>
                    <?php if ($isDaysOverdue): ?>
                        <span class="block">Por tempo: última manutenção em <strong><?= formatDate($lastMaint) ?></strong> (há <?= $daysElapsed ?> dias; limite de <strong><?= $intervalDays ?> dias</strong>).</span>
                    <?php endif; ?>
                    <?php if ($isKmOverdue): ?>
                        <span class="block">Por quilometragem: rodados <strong><?= number_format((int)$kmElapsed, 0, ',', '.') ?> km</strong> desde a última manutenção (limite de <strong><?= number_format((int)$intervalKm, 0, ',', '.') ?> km</strong>).</span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?= url('requisicoes/nova?type=servico&repair=1&maintenance_type=preventiva&product_id=' . $product['id']) ?>" class="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold whitespace-nowrap self-start sm:self-center">
                Agendar Agora
            </a>
        </div>
    <?php endif; ?>

    <!-- Cards de Manutenção e Quilometragem -->
    <div class="grid grid-cols-1 sm:grid-cols-<?= $isVehicle ? '4' : '3' ?> gap-4">
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold uppercase text-slate-400">Última Manutenção</span>
            <div class="text-2xl font-black text-slate-900 mt-1">
                <?= $lastMaint ? formatDate($lastMaint) : 'Sem registro' ?>
            </div>
            <span class="text-[11px] text-slate-500">
                <?= $daysElapsed !== null ? "Há {$daysElapsed} dia(s)" : 'Nenhuma ordem concluída ainda' ?>
                <?= ($isVehicle && $product['last_maintenance_km'] !== null) ? ' • aos ' . number_format((int)$product['last_maintenance_km'], 0, ',', '.') . ' km' : '' ?>
            </span>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold uppercase text-slate-400">Prazo Preventivo</span>
            <div class="text-xl font-black text-indigo-700 mt-1">
                <?php
                    $parts = [];
                    if ($intervalDays) $parts[] = "A cada {$intervalDays} dias";
                    if ($intervalKm) $parts[] = "A cada " . number_format($intervalKm, 0, ',', '.') . " km";
                    echo !empty($parts) ? implode(' / ', $parts) : 'Não configurado';
                ?>
            </div>
            <span class="text-[11px] text-slate-500">Ciclo preventivo programado</span>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold uppercase text-slate-400">Próxima Manutenção Prevista</span>
            <div class="text-xl font-black <?= $isOverdue ? 'text-rose-600' : 'text-emerald-700' ?> mt-1">
                <?php
                    $nextParts = [];
                    if ($nextDueDate) $nextParts[] = formatDate($nextDueDate);
                    if ($nextDueKm !== null) $nextParts[] = number_format($nextDueKm, 0, ',', '.') . ' km';
                    echo !empty($nextParts) ? implode(' ou ', $nextParts) : '-';
                ?>
            </div>
            <span class="text-[11px] <?= $isOverdue ? 'text-rose-600 font-bold' : 'text-slate-500' ?>">
                <?= $isOverdue ? 'Prazo vencido!' : (!empty($nextParts) ? 'Dentro do prazo' : 'Aguardando primeira conclusão/prazo') ?>
            </span>
        </div>

        <?php if ($isVehicle): ?>
            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
                <span class="text-xs font-semibold uppercase text-slate-400">Odômetro Atual</span>
                <div class="text-2xl font-black text-sky-700 mt-1 font-mono">
                    <?= $currentKm !== null ? number_format($currentKm, 0, ',', '.') . ' km' : 'Não registrado' ?>
                </div>
                <span class="text-[11px] text-slate-500">
                    <?= $kmElapsed !== null ? number_format($kmElapsed, 0, ',', '.') . ' km rodados desde última OS' : 'Lance a leitura no Controle de Km' ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($isVehicle): ?>
        <!-- Subprodutos Vinculados ao Veículo & Histórico de Odômetro -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-3">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fas fa-oil-can text-indigo-600"></i> Subprodutos / Insumos Vinculados
                </h3>
                <?php if (empty($product['subitems'])): ?>
                    <p class="text-xs text-slate-400 italic">Nenhum subproduto (óleo, filtro, combustível, etc.) vinculado a este veículo.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100 text-xs">
                        <?php foreach ($product['subitems'] as $sub): ?>
                            <li class="py-2 flex items-start justify-between gap-2">
                                <div>
                                    <span class="font-bold text-slate-800 block"><?= htmlspecialchars($sub['name']) ?></span>
                                    <?php if (!empty($sub['notes'])): ?>
                                        <span class="text-[11px] text-slate-500"><?= htmlspecialchars($sub['notes']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[10px] font-semibold shrink-0">Insumo</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Registros de Quilometragem & Odômetro</h3>
                    <a href="<?= url('veiculos/quilometragem?product_id=' . $product['id']) ?>" class="text-xs font-bold text-indigo-600 hover:underline">
                        + Novo Registro de Km
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase">
                                <th class="py-3 px-4">Data / Hora</th>
                                <th class="py-3 px-4 text-right">Quilometragem</th>
                                <th class="py-3 px-4 text-center">Foto Odômetro</th>
                                <th class="py-3 px-4">Responsável & Obs.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($product['mileage_logs'])): ?>
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-slate-400">Nenhuma leitura de odômetro registrada para este veículo.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($product['mileage_logs'] as $ml): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-2.5 px-4 text-slate-600 whitespace-nowrap"><?= formatDateTime($ml['recorded_at']) ?></td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold text-indigo-700"><?= number_format((int)$ml['mileage_km'], 0, ',', '.') ?> km</td>
                                        <td class="py-2.5 px-4 text-center">
                                            <?php if (!empty($ml['odometer_photo']) && file_exists(__DIR__ . '/../../public/' . $ml['odometer_photo'])): ?>
                                                <a href="<?= asset($ml['odometer_photo']) ?>" target="_blank" title="Ver Foto do Odômetro">
                                                    <img src="<?= asset($ml['odometer_photo']) ?>" alt="Odômetro" class="w-12 h-8 object-cover rounded border border-slate-200 mx-auto">
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-300">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2.5 px-4">
                                            <span class="font-semibold text-slate-800 block"><?= htmlspecialchars($ml['user_name'] ?? 'Sistema') ?></span>
                                            <?php if (!empty($ml['notes'])): ?>
                                                <span class="text-[11px] text-slate-500"><?= htmlspecialchars($ml['notes']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Fotos e Observações -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Fotos & Observações Gerais</h3>

            <div class="text-xs text-slate-700 bg-slate-50 p-3.5 rounded-xl border border-slate-100 whitespace-pre-line">
                <?= !empty($product['notes']) ? htmlspecialchars($product['notes']) : '<span class="italic text-slate-400">Nenhuma observação registrada.</span>' ?>
            </div>

            <?php if (!empty($product['photos'])): ?>
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach ($product['photos'] as $ph): ?>
                        <div class="relative rounded-xl overflow-hidden border border-slate-200 bg-slate-50">
                            <a href="<?= asset($ph['filename']) ?>" target="_blank">
                                <img src="<?= asset($ph['filename']) ?>" alt="Foto" class="w-full h-28 object-cover">
                            </a>
                            <?php if (\App\Helpers\Security::hasRole(['admin'])): ?>
                                <form method="POST" action="<?= url('produtos/foto/excluir') ?>" class="absolute top-1.5 right-1.5" onsubmit="return confirm('Remover esta foto?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                                    <button type="submit" class="w-6 h-6 rounded-full bg-rose-600 text-white text-[10px] shadow flex items-center justify-center" title="Excluir Foto">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-xs text-slate-400 italic">Nenhuma foto anexada.</p>
            <?php endif; ?>
        </div>

        <!-- Histórico de Manutenções e Reparos -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">
                    Histórico de Envios para Reparo / Manutenção
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase">
                            <th class="py-3 px-4">Requisição</th>
                            <th class="py-3 px-4">Tipo</th>
                            <th class="py-3 px-4">Fornecedor</th>
                            <th class="py-3 px-4">Abertura</th>
                            <th class="py-3 px-4">Conclusão</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($product['maintenance_history'])): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">Nenhuma ordem de serviço vinculada a este produto ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($product['maintenance_history'] as $mh): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4">
                                        <a href="<?= url('requisicoes/ver?id=' . $mh['id']) ?>" class="font-mono font-bold text-indigo-600 hover:underline"><?= htmlspecialchars($mh['code']) ?></a>
                                        <div class="text-[11px] text-slate-600"><?= htmlspecialchars($mh['title']) ?></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded font-semibold <?= $mh['maintenance_type'] === 'preventiva' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-800' ?>">
                                            <?= $mh['maintenance_type'] === 'preventiva' ? 'Preventiva' : 'Reparo' ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-700"><?= htmlspecialchars($mh['supplier_name'] ?? '-') ?></td>
                                    <td class="py-3 px-4 text-slate-500"><?= formatDate($mh['created_at']) ?></td>
                                    <td class="py-3 px-4 font-semibold text-emerald-700"><?= !empty($mh['completed_at']) ? formatDate($mh['completed_at']) : '-' ?></td>
                                    <td class="py-3 px-4 text-center"><?= statusBadge($mh['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
