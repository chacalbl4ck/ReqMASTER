<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
$isApprover = \App\Helpers\Security::hasRole(['admin', 'aprovador']);
?>

<div class="space-y-6">
    <!-- Cabeçalho e Barra Superior de Ações -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="<?= url('requisicoes') ?>" class="text-xs font-semibold text-slate-400 hover:text-slate-700 transition">
                    <i class="fas fa-arrow-left"></i> Requisições
                </a>
                <span class="text-slate-300">&bull;</span>
                <span class="text-xs font-bold text-indigo-600 uppercase tracking-wider"><?= $requisition['code'] ?></span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($requisition['title']) ?></h1>
                <?= statusBadge($requisition['status']) ?>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Solicitado por <strong><?= htmlspecialchars($requisition['requester_name']) ?></strong> (<?= htmlspecialchars($requisition['department']) ?>) em <?= formatDateTime($requisition['created_at']) ?>
            </p>
        </div>

        <!-- Grupo de Ações / Fluxo de Aprovação -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Botão de Impressão -->
            <a href="<?= url('requisicoes/imprimir?id=' . $requisition['id']) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-sm transition">
                <i class="fas fa-print"></i> Imprimir Ficha A4
            </a>

            <!-- Ações do Aprovador / Gestor -->
            <?php if ($isApprover && in_array($requisition['status'], ['pendente', 'em_analise'])): ?>
                <!-- Colocar em Análise -->
                <?php if ($requisition['status'] === 'pendente'): ?>
                    <form method="POST" action="<?= url('requisicoes/status') ?>" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $requisition['id'] ?>">
                        <input type="hidden" name="status" value="em_analise">
                        <button type="submit" class="px-3.5 py-2 rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-bold transition">
                            <i class="fas fa-search mr-1"></i> Em Análise
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Aprovar -->
                <form method="POST" action="<?= url('requisicoes/status') ?>" class="inline" onsubmit="return confirm('Deseja realmente aprovar esta requisição?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $requisition['id'] ?>">
                    <input type="hidden" name="status" value="aprovada">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm shadow-emerald-600/30 transition">
                        <i class="fas fa-check-circle mr-1"></i> Aprovar Requisição
                    </button>
                </form>

                <!-- Rejeitar -->
                <button type="button" onclick="openRejectionModal(<?= $requisition['id'] ?>)" class="px-3.5 py-2 rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 text-xs font-bold transition">
                    <i class="fas fa-times-circle mr-1"></i> Rejeitar
                </button>
            <?php endif; ?>

            <!-- Fluxo de Execução pós-aprovação -->
            <?php if ($isApprover && $requisition['status'] === 'aprovada'): ?>
                <form method="POST" action="<?= url('requisicoes/status') ?>" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $requisition['id'] ?>">
                    <input type="hidden" name="status" value="em_execucao">
                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-sm transition">
                        <i class="fas fa-cogs mr-1"></i> Iniciar Execução / Pedido
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($isApprover && $requisition['status'] === 'em_execucao'): ?>
                <form method="POST" action="<?= url('requisicoes/status') ?>" class="inline" onsubmit="return confirm('Confirmar entrega e conclusão da requisição?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $requisition['id'] ?>">
                    <input type="hidden" name="status" value="concluida">
                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition">
                        <i class="fas fa-flag-checkered mr-1"></i> Concluir / Recebido
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alerta caso a requisição esteja rejeitada -->
    <?php if ($requisition['status'] === 'rejeitada'): ?>
        <div class="bg-rose-50 border border-rose-200 rounded-2xl p-5 flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5">
                <i class="fas fa-times-circle text-xl"></i>
            </div>
            <div>
                <h3 class="font-bold text-rose-900 text-sm">Requisição Rejeitada</h3>
                <p class="text-xs text-rose-700 mt-1"><strong>Motivo:</strong> <?= nl2br(htmlspecialchars($requisition['rejection_reason'] ?? 'Não informado')) ?></p>
                <?php if ($requisition['approved_by']): ?>
                    <p class="text-[11px] text-rose-600 mt-1">Rejeitada por: <?= htmlspecialchars($requisition['approver_name']) ?> em <?= formatDateTime($requisition['approved_at']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Grade com 2 Colunas -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Coluna Esquerda: Itens, Justificativa e Auditoria (2 cols) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Tabela de Itens Requisitados -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h2 class="text-base font-bold text-slate-800">Itens / Escopo da Solicitação</h2>
                        <p class="text-xs text-slate-400">Relação discriminada dos materiais ou serviços</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold <?= $requisition['type'] === 'servico' ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700' ?>">
                        <i class="fas <?= $requisition['type'] === 'servico' ? 'fa-tools' : 'fa-box' ?> mr-1.5"></i>
                        <?= $requisition['type'] === 'servico' ? 'Prestação de Serviço' : 'Compra de Material' ?>
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-4">#</th>
                                <th class="py-3 px-4">Descrição & Especificações</th>
                                <th class="py-3 px-4 text-center">Unid.</th>
                                <th class="py-3 px-4 text-right">Qtd</th>
                                <th class="py-3 px-4 text-right">Valor Unit.</th>
                                <th class="py-3 px-4 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <?php foreach ($requisition['items'] as $idx => $item): ?>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-3 px-4 text-slate-400 font-mono"><?= $idx + 1 ?></td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-slate-900"><?= htmlspecialchars($item['description']) ?></div>
                                        <?php if (!empty($item['technical_specs'])): ?>
                                            <div class="text-[11px] text-slate-500 mt-0.5">
                                                <i class="fas fa-info-circle text-[10px] text-slate-400 mr-1"></i>
                                                <?= htmlspecialchars($item['technical_specs']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-center text-slate-600 font-semibold uppercase"><?= htmlspecialchars($item['unit']) ?></td>
                                    <td class="py-3 px-4 text-right font-medium text-slate-800"><?= number_format((float)$item['quantity'], 2, ',', '.') ?></td>
                                    <td class="py-3 px-4 text-right text-slate-600"><?= formatMoney($item['unit_price']) ?></td>
                                    <td class="py-3 px-4 text-right font-bold text-slate-900"><?= formatMoney($item['total_price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 border-t border-slate-200 font-bold text-slate-900 text-sm">
                                <td colspan="5" class="py-3.5 px-4 text-right uppercase tracking-wider text-xs text-slate-500">Valor Total da Requisição:</td>
                                <td class="py-3.5 px-4 text-right text-indigo-700 text-base"><?= formatMoney($requisition['total_amount']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Justificativa da Compra/Serviço -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-2">Justificativa da Solicitação</h3>
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 text-sm text-slate-700 whitespace-pre-line leading-relaxed">
                    <?= htmlspecialchars($requisition['justification']) ?>
                </div>
            </div>

            <!-- Histórico e Auditoria / Logs -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <i class="fas fa-history text-indigo-600"></i> Trilha de Auditoria & Histórico de Ações
                </h3>

                <div class="relative pl-6 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                    <?php if (empty($logs)): ?>
                        <p class="text-xs text-slate-400">Nenhum evento registrado até o momento.</p>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <div class="relative">
                                <div class="absolute -left-6 top-1.5 w-3 h-3 rounded-full bg-indigo-600 ring-4 ring-white"></div>
                                <div class="text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-800"><?= htmlspecialchars($log['action']) ?></span>
                                        <span class="text-slate-400">&bull;</span>
                                        <span class="text-slate-500"><?= formatDateTime($log['created_at']) ?></span>
                                        <?php if (!empty($log['user_name'])): ?>
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-[10px] text-slate-600 font-medium">
                                                <?= htmlspecialchars($log['user_name']) ?> (<?= htmlspecialchars($log['user_role'] ?? '') ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-slate-600 mt-1"><?= htmlspecialchars($log['description']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Coluna Direita: Fornecedor, Contrato e Detalhes da Aprovação (1 col) -->
        <div class="space-y-6">
            <!-- Para Quem Foi Solicitado (Fornecedor / Prestador) -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Para Quem Solicita</h3>
                    <a href="<?= url('fornecedores/ver?id=' . $requisition['supplier_id']) ?>" class="text-xs text-indigo-600 hover:underline font-semibold">Ver perfil</a>
                </div>

                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 text-sm leading-tight"><?= htmlspecialchars($requisition['supplier_name']) ?></h4>
                        <?php if (!empty($requisition['supplier_trade_name'])): ?>
                            <p class="text-xs text-slate-500"><?= htmlspecialchars($requisition['supplier_trade_name']) ?></p>
                        <?php endif; ?>
                        <p class="text-[11px] text-slate-400 font-mono mt-0.5">
                            <?= \App\Helpers\Security::formatDocument($requisition['supplier_document']) ?>
                        </p>
                    </div>
                </div>

                <div class="space-y-2 text-xs border-t border-slate-100 pt-3">
                    <?php if (!empty($requisition['supplier_phone'])): ?>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Telefone:</span>
                            <span class="font-medium text-slate-800"><?= htmlspecialchars($requisition['supplier_phone']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($requisition['supplier_email'])): ?>
                        <div class="flex justify-between">
                            <span class="text-slate-500">E-mail:</span>
                            <span class="font-medium text-slate-800"><?= htmlspecialchars($requisition['supplier_email']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($requisition['supplier_bank_info'])): ?>
                        <div class="pt-2 border-t border-slate-100">
                            <span class="text-slate-500 block mb-1">Dados Bancários / Pix:</span>
                            <div class="p-2 rounded bg-slate-50 font-mono text-[11px] text-slate-700">
                                <?= nl2br(htmlspecialchars($requisition['supplier_bank_info'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vínculo Contratual (Possibilidade de Contrato) -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Vínculo Contratual</h3>
                    <?php if (!empty($requisition['contract_id'])): ?>
                        <a href="<?= url('contratos/ver?id=' . $requisition['contract_id']) ?>" class="text-xs text-indigo-600 hover:underline font-semibold">Ver contrato</a>
                    <?php endif; ?>
                </div>

                <?php if (empty($requisition['contract_id'])): ?>
                    <div class="p-4 rounded-xl bg-slate-50 border border-dashed border-slate-200 text-center">
                        <i class="fas fa-file-invoice text-slate-300 text-2xl mb-1.5 block"></i>
                        <p class="text-xs font-medium text-slate-600">Requisição Avulsa</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Esta solicitação não está vinculada a nenhum contrato formal pré-existente.</p>
                    </div>
                <?php else: ?>
                    <?php 
                        $pctUsed = round(((float)$requisition['contract_used_value'] / (float)$requisition['contract_total_value']) * 100, 1);
                    ?>
                    <div class="p-4 rounded-xl bg-purple-50/60 border border-purple-200 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-purple-100 text-purple-800 font-mono text-xs font-bold">
                                <?= htmlspecialchars($requisition['contract_number']) ?>
                            </span>
                        </div>
                        <h4 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($requisition['contract_title']) ?></h4>

                        <div class="space-y-1.5 text-xs pt-1">
                            <div class="flex justify-between text-slate-600">
                                <span>Teto Total do Contrato:</span>
                                <span class="font-semibold"><?= formatMoney($requisition['contract_total_value']) ?></span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>Total Consumido:</span>
                                <span class="font-semibold text-purple-700"><?= formatMoney($requisition['contract_used_value']) ?> (<?= $pctUsed ?>%)</span>
                            </div>
                            <div class="w-full bg-purple-200 rounded-full h-2 overflow-hidden mt-1">
                                <div class="bg-purple-600 h-2 rounded-full" style="width: <?= min(100, $pctUsed) ?>%"></div>
                            </div>
                            <div class="flex justify-between text-slate-700 pt-1 font-bold">
                                <span>Saldo Remanescente:</span>
                                <span class="text-emerald-700"><?= formatMoney($requisition['contract_balance']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Dados Complementares / Prazos -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-3 text-xs">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Informações da Solicitação</h3>
                <div class="flex justify-between py-1.5 border-b border-slate-100">
                    <span class="text-slate-500">Prioridade:</span>
                    <?= priorityBadge($requisition['priority']) ?>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-100">
                    <span class="text-slate-500">Prazo Desejado:</span>
                    <span class="font-medium text-slate-800"><?= formatDate($requisition['deadline']) ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-100">
                    <span class="text-slate-500">Data de Envio:</span>
                    <span class="font-medium text-slate-800"><?= formatDateTime($requisition['created_at']) ?></span>
                </div>
                <?php if ($requisition['approved_at']): ?>
                    <div class="flex justify-between py-1.5">
                        <span class="text-slate-500">Decisão em:</span>
                        <span class="font-medium text-slate-800"><?= formatDateTime($requisition['approved_at']) ?> por <?= htmlspecialchars($requisition['approver_name']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
