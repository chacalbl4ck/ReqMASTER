<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';

$counts = $metrics['counts'];
?>

<div class="space-y-6">
    <!-- Cabeçalho do Painel -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Painel de Controle</h1>
            <p class="text-xs sm:text-sm text-slate-500">Visão geral das requisições de compras, serviços e saldo de contratos.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= url('requisicoes/nova') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-sm transition">
                <i class="fas fa-plus"></i> Nova Requisição
            </a>
            <a href="<?= url('fornecedores/novo') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-xl text-sm font-semibold shadow-sm transition">
                <i class="fas fa-building"></i> Cadastrar Fornecedor
            </a>
        </div>
    </div>

    <?php if (!empty($overdueProducts)): ?>
        <!-- Aviso de Manutenção Preventiva Atrasada -->
        <div class="bg-rose-50 border border-rose-200 rounded-2xl p-5 shadow-sm space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-triangle-exclamation text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-sm sm:text-base font-bold text-rose-950">
                            Alerta de Manutenção Preventiva Atrasada (<?= count($overdueProducts) ?> <?= count($overdueProducts) === 1 ? 'equipamento' : 'equipamentos' ?>)
                        </h2>
                        <p class="text-xs text-rose-800">
                            Há equipamentos ou móveis cujo prazo desde a última manutenção ultrapassou o intervalo preventivo cadastrado.
                        </p>
                    </div>
                </div>
                <a href="<?= url('produtos') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-rose-200 text-rose-700 hover:bg-rose-100 text-xs font-bold transition shrink-0">
                    <i class="fas fa-boxes"></i> Ver Catálogo de Produtos
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 pt-1">
                <?php foreach ($overdueProducts as $op): ?>
                    <?php
                        $isDaysLate = !empty($op['is_days_overdue']);
                        $isKmLate = !empty($op['is_km_overdue']);
                        $daysSince = (int)($op['days_elapsed'] ?? 0);
                        $interval = (int)($op['maintenance_interval_days'] ?? 0);
                        $daysLate = (int)($op['days_overdue'] ?? max(1, $daysSince - $interval));
                        $kmElapsed = (int)($op['km_elapsed'] ?? 0);
                        $intervalKm = (int)($op['maintenance_interval_km'] ?? 0);
                        $kmOverdue = (int)($op['km_overdue'] ?? 0);
                    ?>
                    <div class="bg-white rounded-xl border border-rose-200/80 p-3.5 flex flex-col justify-between gap-2.5 shadow-sm">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <a href="<?= url('produtos/ver?id=' . (int)$op['id']) ?>" class="font-bold text-sm text-slate-900 hover:text-indigo-700 transition">
                                    <?php if (!empty($op['is_vehicle'])): ?>
                                        <i class="fas fa-car text-indigo-600 mr-1"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($op['name']) ?>
                                </a>
                                <div class="flex flex-col items-end gap-1 shrink-0">
                                    <?php if ($isDaysLate): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">
                                            +<?= $daysLate ?>d atraso
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($isKmLate): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900">
                                            <?= number_format($kmElapsed, 0, ',', '.') ?> / <?= number_format($intervalKm, 0, ',', '.') ?> km
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($op['license_plate'])): ?>
                                <div class="text-[11px] font-mono text-slate-500 mt-0.5">Placa: <?= htmlspecialchars($op['license_plate']) ?></div>
                            <?php elseif (!empty($op['serial_number'])): ?>
                                <div class="text-[11px] font-mono text-slate-500 mt-0.5">N/S: <?= htmlspecialchars($op['serial_number']) ?></div>
                            <?php endif; ?>
                            <?php if ($isDaysLate): ?>
                                <div class="text-xs text-slate-600 mt-1.5">
                                    Última: <strong><?= formatDate($op['last_maintenance_at']) ?></strong>
                                    &bull; Prazo: <strong><?= $interval ?> dias</strong>
                                    (decorridos <?= $daysSince ?>d)
                                </div>
                            <?php endif; ?>
                            <?php if ($isKmLate): ?>
                                <div class="text-xs text-slate-600 mt-1">
                                    Odômetro: <strong><?= number_format((int)$op['current_km'], 0, ',', '.') ?> km</strong>
                                    &bull; Excedido em <strong><?= number_format(max(0, $kmOverdue), 0, ',', '.') ?> km</strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($op['overdue_subitems'])): ?>
                                <div class="mt-2 p-2 rounded-lg bg-rose-50 border border-rose-200 text-[11px] text-rose-900">
                                    <strong class="block"><i class="fas fa-oil-can mr-1"></i>Subprodutos no prazo de troca:</strong>
                                    <?php foreach ($op['overdue_subitems'] as $osub): ?>
                                        <span class="inline-block mr-2">&bull; <strong><?= htmlspecialchars($osub['name']) ?></strong> (<?= htmlspecialchars($osub['overdue_label']) ?>)</span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
                            <div class="flex items-center gap-2">
                                <a href="<?= url('produtos/ver?id=' . (int)$op['id']) ?>" class="text-[11px] font-semibold text-slate-600 hover:text-slate-900">
                                    Ver Ficha
                                </a>
                                <?php if (!empty($op['is_vehicle'])): ?>
                                    <a href="<?= url('veiculos/quilometragem?product_id=' . (int)$op['id']) ?>" class="text-[11px] font-semibold text-sky-700 hover:underline">
                                        Registrar Km
                                    </a>
                                <?php endif; ?>
                            </div>
                            <a href="<?= url('requisicoes/nova?type=servico&repair=1&maintenance_type=preventiva&product_id=' . (int)$op['id']) ?>"
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold transition">
                                <i class="fas fa-wrench"></i> Abrir OS Preventiva
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cards de Métricas Principais -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Pendentes -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Aguardando Aprovação</p>
                <h3 class="text-3xl font-extrabold text-slate-900 mt-1"><?= (int)($counts['total_pending'] ?? 0) ?></h3>
                <p class="text-xs text-amber-600 font-medium mt-1">
                    <i class="fas fa-clock mr-1"></i> <?= formatMoney($counts['total_amount_pending'] ?? 0) ?>
                </p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="fas fa-hourglass-half"></i>
            </div>
        </div>

        <!-- Aprovadas / Concluídas -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Aprovadas</p>
                <h3 class="text-3xl font-extrabold text-slate-900 mt-1"><?= (int)($counts['total_approved'] ?? 0) + (int)($counts['total_completed'] ?? 0) ?></h3>
                <p class="text-xs text-emerald-600 font-medium mt-1">
                    <i class="fas fa-check-circle mr-1"></i> <?= formatMoney($counts['total_amount_approved'] ?? 0) ?>
                </p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fas fa-file-signature"></i>
            </div>
        </div>

        <!-- Materiais vs Serviços -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tipos de Requisição</p>
                <div class="flex items-center gap-3 mt-1.5">
                    <div>
                        <span class="text-xs text-slate-500 block">Materiais</span>
                        <span class="text-lg font-bold text-slate-800"><?= (int)($counts['total_materials'] ?? 0) ?></span>
                    </div>
                    <div class="h-6 w-px bg-slate-200"></div>
                    <div>
                        <span class="text-xs text-slate-500 block">Serviços</span>
                        <span class="text-lg font-bold text-indigo-600"><?= (int)($counts['total_services'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                <i class="fas fa-boxes-stacked"></i>
            </div>
        </div>

        <!-- Contratos Ativos & Saldo -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Contratos Vigentes</p>
                <h3 class="text-3xl font-extrabold text-slate-900 mt-1"><?= $metrics['total_contracts'] ?></h3>
                <p class="text-xs text-slate-500 mt-1">
                    Saldo: <strong class="text-indigo-600 font-semibold"><?= formatMoney($metrics['contracts_remaining']) ?></strong>
                </p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
                <i class="fas fa-file-contract"></i>
            </div>
        </div>
    </div>

    <!-- Seção de Gestão de Contratos: Consumo do Teto Orçamentário -->
    <?php if ($metrics['contracts_total_val'] > 0): ?>
    <?php 
        $pctTotalUsed = round(($metrics['contracts_used_val'] / $metrics['contracts_total_val']) * 100, 1);
    ?>
    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-3">
            <div>
                <h2 class="text-base font-bold text-slate-800">Teto Orçamentário Global dos Contratos</h2>
                <p class="text-xs text-slate-500">Acompanhamento do valor consumido por requisições aprovadas</p>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-400">Consumido: <strong><?= formatMoney($metrics['contracts_used_val']) ?></strong> de <?= formatMoney($metrics['contracts_total_val']) ?></span>
                <span class="ml-2 font-bold text-sm <?= $pctTotalUsed > 80 ? 'text-rose-600' : 'text-indigo-600' ?>"><?= $pctTotalUsed ?>%</span>
            </div>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
            <div class="h-3 rounded-full <?= $pctTotalUsed > 85 ? 'bg-rose-500' : ($pctTotalUsed > 60 ? 'bg-amber-500' : 'bg-indigo-600') ?> transition-all duration-500" style="width: <?= min(100, $pctTotalUsed) ?>%"></div>
        </div>
        <div class="flex justify-between items-center text-xs text-slate-400 mt-2">
            <span>0%</span>
            <span class="font-medium text-emerald-600">Saldo Livre Disponível: <?= formatMoney($metrics['contracts_remaining']) ?></span>
            <span>100%</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Seção Dividida: Gráficos e Alertas de Contratos -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Gráfico de Status -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm lg:col-span-1 flex flex-col">
            <h3 class="text-base font-bold text-slate-800 mb-1">Status das Requisições</h3>
            <p class="text-xs text-slate-400 mb-4">Distribuição do fluxo de solicitações</p>
            <div class="relative flex-1 flex items-center justify-center min-h-[220px]">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- Alertas de Contratos a Vencer -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm lg:col-span-2">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Alertas de Contratos Próximos do Fim</h3>
                    <p class="text-xs text-slate-400">Contratos com vencimento previsto para os próximos 60 dias</p>
                </div>
                <a href="<?= url('contratos') ?>" class="text-xs text-indigo-600 hover:text-indigo-700 font-semibold">Ver todos</a>
            </div>

            <?php if (empty($expiringContracts)): ?>
                <div class="p-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200">
                    <i class="fas fa-calendar-check text-slate-300 text-3xl mb-2"></i>
                    <p class="text-sm font-medium text-slate-600">Nenhum contrato vencendo nos próximos 60 dias.</p>
                    <p class="text-xs text-slate-400">Todos os contratos vigentes estão com prazos regulares.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($expiringContracts as $ec): ?>
                        <div class="p-3.5 rounded-xl border border-amber-200 bg-amber-50/60 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-sm">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-800">
                                        <?= htmlspecialchars($ec['contract_number']) ?> - <?= htmlspecialchars($ec['title']) ?>
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        Fornecedor: <strong><?= htmlspecialchars($ec['supplier_name']) ?></strong> &bull; Vence em: <strong><?= formatDate($ec['end_date']) ?></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right whitespace-nowrap">
                                <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-amber-200/80 text-amber-800">
                                    <?= $ec['days_left'] ?> dia(s)
                                </span>
                                <a href="<?= url('contratos/ver?id=' . $ec['id']) ?>" class="block text-xs text-indigo-600 hover:underline mt-1 font-medium">Ver Contrato</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabela de Últimas Requisições -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex justify-between items-center">
            <div>
                <h3 class="text-base font-bold text-slate-800">Requisições Recentes</h3>
                <p class="text-xs text-slate-400">Últimas solicitações registradas no sistema</p>
            </div>
            <a href="<?= url('requisicoes') ?>" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                Ver todas <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Código / Tipo</th>
                        <th class="py-3 px-4">Título / Descrição</th>
                        <th class="py-3 px-4">Para Quem Solicita (Fornecedor)</th>
                        <th class="py-3 px-4">Contrato</th>
                        <th class="py-3 px-4">Solicitante</th>
                        <th class="py-3 px-4 text-right">Valor Total</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($recentRequisitions)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-slate-400">
                                Nenhuma requisição encontrada.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentRequisitions as $req): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="font-bold text-slate-900"><?= htmlspecialchars($req['code']) ?></span>
                                    <span class="block text-[11px] <?= $req['type'] === 'servico' ? 'text-indigo-600 font-semibold' : 'text-slate-500' ?>">
                                        <i class="fas <?= $req['type'] === 'servico' ? 'fa-tools' : 'fa-box' ?> mr-1"></i>
                                        <?= $req['type'] === 'servico' ? 'Serviço' : 'Material' ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-medium text-slate-800 max-w-xs truncate">
                                    <a href="<?= url('requisicoes/ver?id=' . $req['id']) ?>" class="hover:text-indigo-600">
                                        <?= htmlspecialchars($req['title']) ?>
                                    </a>
                                    <span class="block text-[10px] text-slate-400">Prioridade: <?= priorityBadge($req['priority']) ?></span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-medium text-slate-800"><?= htmlspecialchars($req['supplier_name']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= \App\Helpers\Security::formatDocument($req['supplier_document']) ?></div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <?php if (!empty($req['contract_number'])): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-purple-50 text-purple-700 border border-purple-200">
                                            <i class="fas fa-file-contract mr-1 text-[10px]"></i> <?= htmlspecialchars($req['contract_number']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-[11px]">Sem Contrato</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="text-slate-700"><?= htmlspecialchars($req['requester_name']) ?></span>
                                    <span class="block text-[10px] text-slate-400"><?= htmlspecialchars($req['department']) ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-bold text-slate-900 whitespace-nowrap">
                                    <?= formatMoney($req['total_amount']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?= statusBadge($req['status']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <a href="<?= url('requisicoes/ver?id=' . $req['id']) ?>" class="p-1.5 text-slate-400 hover:text-indigo-600 transition" title="Visualizar">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    <a href="<?= url('requisicoes/imprimir?id=' . $req['id']) ?>" target="_blank" class="p-1.5 text-slate-400 hover:text-slate-700 transition" title="Imprimir Folha">
                                        <i class="fas fa-print text-sm"></i>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Pendentes', 'Aprovadas', 'Em Execução', 'Concluídas', 'Rejeitadas'],
                datasets: [{
                    data: [
                        <?= (int)($counts['total_pending'] ?? 0) ?>,
                        <?= (int)($counts['total_approved'] ?? 0) ?>,
                        <?= (int)($counts['total_in_progress'] ?? 0) ?>,
                        <?= (int)($counts['total_completed'] ?? 0) ?>,
                        <?= (int)($counts['total_rejected'] ?? 0) ?>
                    ],
                    backgroundColor: [
                        '#fbbf24', // Amber/Yellow
                        '#10b981', // Emerald
                        '#6366f1', // Indigo
                        '#059669', // Deep Green
                        '#f43f5e'  // Rose
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 12,
                            font: { size: 11 }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    });
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
