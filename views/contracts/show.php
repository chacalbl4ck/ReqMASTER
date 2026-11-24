<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
$pct = (float)$contract['usage_percentage'];
$barColor = $pct > 85 ? 'bg-rose-500' : ($pct > 65 ? 'bg-amber-500' : 'bg-purple-600');
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="<?= url('contratos') ?>" class="text-xs font-semibold text-slate-400 hover:text-slate-700 transition">
                    <i class="fas fa-arrow-left"></i> Contratos
                </a>
                <span class="text-slate-300">&bull;</span>
                <span class="text-xs font-mono font-bold text-purple-700 uppercase tracking-wider"><?= htmlspecialchars($contract['contract_number']) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($contract['title']) ?></h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $contract['status'] === 'ativo' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500' ?>">
                    <?= ucfirst($contract['status']) ?>
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Contratado: <a href="<?= url('fornecedores/ver?id=' . $contract['supplier_id']) ?>" class="font-bold text-slate-800 hover:text-indigo-600"><?= htmlspecialchars($contract['supplier_name']) ?></a> &bull; Vigência: <?= formatDate($contract['start_date']) ?> até <?= formatDate($contract['end_date']) ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= url('contratos/editar?id=' . $contract['id']) ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-sm transition">
                <i class="fas fa-pencil-alt mr-1"></i> Editar Contrato
            </a>
            <?php if (\App\Helpers\Security::hasRole(['admin'])): ?>
                <form method="POST" action="<?= url('contratos/excluir') ?>" class="inline" onsubmit="return confirm('Confirma excluir o contrato <?= htmlspecialchars($contract['contract_number']) ?>? Requisições vinculadas terão o vínculo removido.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $contract['id'] ?>">
                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition">
                        <i class="fas fa-trash-alt mr-1"></i> Excluir Contrato
                    </button>
                </form>
            <?php endif; ?>
            <a href="<?= url('requisicoes/nova?supplier_id=' . $contract['supplier_id'] . '&contract_id=' . $contract['id']) ?>" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-sm shadow-indigo-600/30 transition">
                <i class="fas fa-plus mr-1"></i> Nova Requisição sob este Contrato
            </a>
        </div>
    </div>

    <!-- Cards de Balanço Financeiro do Contrato -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold uppercase text-slate-400">Teto Total Contratado</span>
            <div class="text-2xl font-black text-slate-900 mt-1"><?= formatMoney($contract['total_value']) ?></div>
            <span class="text-[11px] text-slate-400 block mt-1">Valor fixado em contrato/aditivos</span>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold uppercase text-slate-400">Total Consumido (Requisições)</span>
            <div class="text-2xl font-black text-purple-700 mt-1"><?= formatMoney($contract['used_value']) ?></div>
            <span class="text-[11px] text-purple-600 font-medium block mt-1"><?= $pct ?>% do valor total consumido</span>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
            <span class="text-xs font-semibold uppercase text-slate-400">Saldo Livre Remanescente</span>
            <div class="text-2xl font-black text-emerald-700 mt-1"><?= formatMoney($contract['balance_value']) ?></div>
            <span class="text-[11px] text-emerald-600 font-medium block mt-1">Disponível para novas requisições</span>
        </div>
    </div>

    <!-- Barra Visual de Consumo -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-2">
        <div class="flex justify-between items-center text-xs">
            <span class="font-bold text-slate-700">Progresso de Execução Financeira</span>
            <span class="font-extrabold text-slate-800"><?= $pct ?>% consumido</span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
            <div class="<?= $barColor ?> h-3 rounded-full transition-all duration-500" style="width: <?= min(100, $pct) ?>%"></div>
        </div>
        <div class="flex justify-between text-[11px] text-slate-400">
            <span>Início: <?= formatDate($contract['start_date']) ?></span>
            <span>Término da Vigência: <?= formatDate($contract['end_date']) ?></span>
        </div>
    </div>

    <!-- Detalhes do Contrato e Requisições Vinculadas -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Cláusulas e Termos -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Cláusulas & Termos</h3>
            
            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-slate-400 block">Tipo:</span>
                    <span class="font-medium text-slate-800 capitalize"><?= $contract['type'] ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block">Cláusula de Renovação:</span>
                    <span class="text-slate-800"><?= htmlspecialchars($contract['renewal_clause'] ?? 'Não especificada') ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block">Observações:</span>
                    <p class="text-slate-700 mt-1 whitespace-pre-line"><?= htmlspecialchars($contract['notes'] ?? 'Sem observações adicionais.') ?></p>
                </div>
            </div>
        </div>

        <!-- Requisições Vinculadas a este Contrato -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Requisições Emitidas sob este Contrato</h3>
                    <p class="text-xs text-slate-400">Cada requisição aprovada desconta do saldo deste contrato</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                            <th class="py-3 px-4">Código / Data</th>
                            <th class="py-3 px-4">Título</th>
                            <th class="py-3 px-4">Solicitante</th>
                            <th class="py-3 px-4 text-right">Valor</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($requisitions)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-slate-400">
                                    Nenhuma requisição vinculada a este contrato até o momento.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requisitions as $r): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="font-bold text-slate-900"><?= htmlspecialchars($r['code']) ?></span>
                                        <span class="block text-[10px] text-slate-400"><?= formatDate($r['created_at']) ?></span>
                                    </td>
                                    <td class="py-3 px-4 font-medium text-slate-800"><?= htmlspecialchars($r['title']) ?></td>
                                    <td class="py-3 px-4 text-slate-600"><?= htmlspecialchars($r['requester_name']) ?></td>
                                    <td class="py-3 px-4 text-right font-bold text-slate-900"><?= formatMoney($r['total_amount']) ?></td>
                                    <td class="py-3 px-4 text-center"><?= statusBadge($r['status']) ?></td>
                                    <td class="py-3 px-4 text-center">
                                        <a href="<?= url('requisicoes/ver?id=' . $r['id']) ?>" class="text-indigo-600 hover:text-indigo-800 font-medium">Ver</a>
                                    </td>
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
