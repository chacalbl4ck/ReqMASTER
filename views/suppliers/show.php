<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho & Botões -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="<?= url('fornecedores') ?>" class="text-xs font-semibold text-slate-400 hover:text-slate-700 transition">
                    <i class="fas fa-arrow-left"></i> Fornecedores
                </a>
                <span class="text-slate-300">&bull;</span>
                <span class="text-xs font-bold text-indigo-600 uppercase tracking-wider"><?= $supplier['type'] ?></span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($supplier['name']) ?></h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $supplier['status'] === 'ativo' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500' ?>">
                    <?= ucfirst($supplier['status']) ?>
                </span>
            </div>
            <?php if (!empty($supplier['trade_name'])): ?>
                <p class="text-xs text-slate-500 mt-0.5">Nome Fantasia: <strong><?= htmlspecialchars($supplier['trade_name']) ?></strong></p>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= url('fornecedores/editar?id=' . $supplier['id']) ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-sm transition">
                <i class="fas fa-pencil-alt mr-1"></i> Editar Cadastro
            </a>
            <form method="POST" action="<?= url('fornecedores/toggle-status') ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $supplier['id'] ?>">
                <input type="hidden" name="redirect_to" value="show">
                <button type="submit" class="px-3.5 py-2 rounded-xl text-xs font-bold transition border <?= $supplier['status'] === 'ativo' ? 'bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-200' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-200' ?>">
                    <i class="fas <?= $supplier['status'] === 'ativo' ? 'fa-ban' : 'fa-check-circle' ?> mr-1"></i>
                    <?= $supplier['status'] === 'ativo' ? 'Inativar Fornecedor' : 'Reativar Fornecedor' ?>
                </button>
            </form>
            <a href="<?= url('contratos/novo?supplier_id=' . $supplier['id']) ?>" class="px-3.5 py-2 rounded-xl bg-purple-50 text-purple-700 hover:bg-purple-100 text-xs font-bold transition">
                <i class="fas fa-file-contract mr-1"></i> Cadastrar Contrato
            </a>
            <a href="<?= url('requisicoes/nova?supplier_id=' . $supplier['id']) ?>" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-sm shadow-indigo-600/30 transition">
                <i class="fas fa-plus mr-1"></i> Nova Requisição
            </a>
        </div>
    </div>

    <!-- Grade: Informações Cadastrais e Financeiras -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Card de Informações -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Dados Cadastrais</h3>

            <div class="space-y-2.5 text-xs">
                <div>
                    <span class="text-slate-400 block">Documento (CNPJ/CPF):</span>
                    <span class="font-mono font-bold text-slate-800 text-sm"><?= \App\Helpers\Security::formatDocument($supplier['document']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block">Categoria / Segmento:</span>
                    <span class="font-semibold text-slate-800"><?= htmlspecialchars($supplier['category']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block">Pessoa de Contato:</span>
                    <span class="text-slate-800"><?= htmlspecialchars($supplier['contact_person'] ?? '-') ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block">E-mail:</span>
                    <span class="text-slate-800"><?= htmlspecialchars($supplier['email'] ?? '-') ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block">Telefone:</span>
                    <span class="text-slate-800"><?= htmlspecialchars($supplier['phone'] ?? '-') ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block">Endereço:</span>
                    <span class="text-slate-800">
                        <?= htmlspecialchars($supplier['address'] ?? '') ?>
                        <?= !empty($supplier['city']) ? ' - ' . htmlspecialchars($supplier['city']) . '/' . htmlspecialchars($supplier['state'] ?? '') : '' ?>
                    </span>
                </div>
            </div>

            <!-- Dados Bancários / Pix -->
            <div class="pt-4 border-t border-slate-100">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Dados Bancários / Pix</h4>
                <?php if (!empty($supplier['bank_info'])): ?>
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 font-mono text-xs text-slate-700 leading-relaxed">
                        <?= nl2br(htmlspecialchars($supplier['bank_info'])) ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-slate-400 italic">Nenhum dado bancário informado.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Coluna 2: Contratos e Requisições do Fornecedor -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Contratos do Fornecedor -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Contratos Vinculados</h3>
                        <p class="text-xs text-slate-400">Contratos formais ativos para este prestador/fornecedor</p>
                    </div>
                    <a href="<?= url('contratos/novo?supplier_id=' . $supplier['id']) ?>" class="text-xs font-semibold text-purple-700 hover:text-purple-900">
                        + Novo Contrato
                    </a>
                </div>

                <div class="p-5">
                    <?php if (empty($contracts)): ?>
                        <div class="text-center py-6 text-slate-400 text-xs">
                            <i class="fas fa-file-contract text-2xl text-slate-300 mb-1 block"></i>
                            Nenhum contrato formal cadastrado para este fornecedor.
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($contracts as $c): ?>
                                <?php 
                                    $pct = $c['total_value'] > 0 ? round(($c['used_value'] / $c['total_value']) * 100, 1) : 0;
                                ?>
                                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition">
                                    <div class="flex justify-between items-start gap-2 mb-2">
                                        <div>
                                            <a href="<?= url('contratos/ver?id=' . $c['id']) ?>" class="font-bold text-sm text-slate-900 hover:text-indigo-600">
                                                <?= htmlspecialchars($c['contract_number']) ?> - <?= htmlspecialchars($c['title']) ?>
                                            </a>
                                            <div class="text-[11px] text-slate-400">
                                                Vigência: <?= formatDate($c['start_date']) ?> até <?= formatDate($c['end_date']) ?>
                                            </div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700">
                                            <?= ucfirst($c['status']) ?>
                                        </span>
                                    </div>

                                    <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden my-2">
                                        <div class="h-2 rounded-full <?= $pct > 80 ? 'bg-rose-500' : 'bg-purple-600' ?>" style="width: <?= min(100, $pct) ?>%"></div>
                                    </div>

                                    <div class="flex justify-between items-center text-xs text-slate-600">
                                        <span>Teto: <strong><?= formatMoney($c['total_value']) ?></strong></span>
                                        <span>Consumido: <strong class="text-purple-700"><?= formatMoney($c['used_value']) ?></strong> (<?= $pct ?>%)</span>
                                        <span>Saldo Livre: <strong class="text-emerald-700"><?= formatMoney($c['balance_value']) ?></strong></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Requisições Vinculadas -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Histórico de Requisições</h3>
                        <p class="text-xs text-slate-400">Solicitações direcionadas a este fornecedor</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase">
                                <th class="py-2.5 px-4">Código</th>
                                <th class="py-2.5 px-4">Título</th>
                                <th class="py-2.5 px-4">Tipo</th>
                                <th class="py-2.5 px-4 text-right">Valor</th>
                                <th class="py-2.5 px-4 text-center">Status</th>
                                <th class="py-2.5 px-4 text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($requisitions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-6 text-slate-400">
                                        Nenhuma requisição registrada para este fornecedor.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requisitions as $r): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-2.5 px-4 font-bold text-slate-900"><?= htmlspecialchars($r['code']) ?></td>
                                        <td class="py-2.5 px-4 font-medium text-slate-800"><?= htmlspecialchars($r['title']) ?></td>
                                        <td class="py-2.5 px-4"><?= $r['type'] === 'servico' ? 'Serviço' : 'Material' ?></td>
                                        <td class="py-2.5 px-4 text-right font-bold"><?= formatMoney($r['total_amount']) ?></td>
                                        <td class="py-2.5 px-4 text-center"><?= statusBadge($r['status']) ?></td>
                                        <td class="py-2.5 px-4 text-center">
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
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
