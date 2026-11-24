<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
$isApprover = \App\Helpers\Security::hasRole(['admin', 'aprovador']);
$hasValues = !empty($requisition['has_values']) && (float)$requisition['total_amount'] > 0;
$companyName = $companySettings['company_name'] ?? 'Nossa Empresa';
$companyLogo = $companySettings['company_logo'] ?? '';
$attachments = $requisition['attachments'] ?? [];
?>

<!-- Biblioteca HTML2PDF para exportação direta em PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

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
            <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                <span>Empresa: <strong class="text-slate-800"><?= htmlspecialchars($companyName) ?></strong></span>
                <span class="text-slate-300">&bull;</span>
                <span>Solicitante: <strong class="text-slate-800"><?= htmlspecialchars($requisition['requester_name']) ?></strong> (<?= htmlspecialchars($requisition['requester_email'] ?? '') ?> - <?= htmlspecialchars($requisition['department']) ?>)</span>
                <span class="text-slate-300">&bull;</span>
                <span>Criada em: <?= formatDateTime($requisition['created_at']) ?></span>
            </div>
        </div>

        <!-- Grupo de Ações / Fluxo de Aprovação -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Botão Exportar para PDF -->
            <button type="button" onclick="exportToPdf()" id="btnExportPdf" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-sm transition">
                <i class="fas fa-file-pdf text-rose-400"></i> Exportar para PDF
            </button>

            <!-- Botão de Enviar por E-mail -->
            <button type="button" onclick="openEmailModal()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-sm transition">
                <i class="fas fa-envelope text-indigo-600"></i> Enviar por E-mail
            </button>

            <!-- Botão de Impressão -->
            <a href="<?= url('requisicoes/imprimir?id=' . $requisition['id']) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-sm transition">
                <i class="fas fa-print"></i> Imprimir Ficha A4
            </a>

            <!-- Botão Editar Requisição (Apenas Administrador) -->
            <?php if (\App\Helpers\Security::hasRole(['admin'])): ?>
                <a href="<?= url('requisicoes/editar?id=' . $requisition['id']) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-bold transition border border-amber-200" title="Editar Dados e Itens da Requisição">
                    <i class="fas fa-edit"></i> Editar Requisição
                </a>
            <?php endif; ?>

            <!-- Botão Excluir Requisição (Apenas Administrador) -->
            <?php if (\App\Helpers\Security::hasRole(['admin'])): ?>
                <form method="POST" action="<?= url('requisicoes/excluir') ?>" class="inline" onsubmit="return confirm('ATENÇÃO: Deseja realmente excluir permanentemente a requisição <?= $requisition['code'] ?>? Esta ação não pode ser desfeita.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $requisition['id'] ?>">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition border border-rose-200" title="Excluir Requisição do Banco">
                        <i class="fas fa-trash-alt"></i> Excluir
                    </button>
                </form>
            <?php endif; ?>

            <!-- Ações do Aprovador / Gestor -->
            <?php if ($isApprover && in_array($requisition['status'], ['pendente', 'em_analise'])): ?>
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

                <?php $isAdminRequester = (($requisition['requester_role'] ?? '') === 'admin'); ?>
                <?php if ($isAdminRequester): ?>
                    <!-- Solicitante é Admin: aprova diretamente sem solicitar assinatura do aprovador -->
                    <form method="POST" action="<?= url('requisicoes/status') ?>" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $requisition['id'] ?>">
                        <input type="hidden" name="status" value="aprovada">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm shadow-emerald-600/30 transition">
                            <i class="fas fa-check-circle mr-1"></i> Aprovar Requisição
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Botão Aprovar com Modal de Assinatura -->
                    <button type="button" onclick="openApprovalModal()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm shadow-emerald-600/30 transition">
                        <i class="fas fa-check-circle mr-1"></i> Aprovar Requisição
                    </button>
                <?php endif; ?>

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

    <!-- Conteúdo da Requisição (Envolvido em container capturável para PDF) -->
    <div id="requisitionPrintableContent" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Coluna Esquerda: Itens, Justificativa, Fotos e Auditoria (2 cols) -->
            <div class="lg:col-span-2 space-y-6">
                <?php if ($requisition['type'] === 'servico' && !empty($requisition['is_equipment_repair'])): ?>
                    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                                <i class="fas fa-wrench"></i>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-amber-800">
                                    Ordem de Serviço &bull; <?= ($requisition['maintenance_type'] ?? 'reparo') === 'preventiva' ? 'Manutenção Preventiva' : 'Reparo / Manutenção de Equipamento ou Móvel' ?>
                                </div>
                                <?php if (!empty($requisition['linked_product_id'])): ?>
                                    <div class="text-sm font-bold text-slate-900 mt-0.5">
                                        Produto Vinculado:
                                        <a href="<?= url('produtos/ver?id=' . (int)$requisition['linked_product_id']) ?>" class="text-indigo-700 hover:underline">
                                            <?= htmlspecialchars($requisition['linked_product_name'] ?? ('Produto #' . $requisition['linked_product_id'])) ?>
                                        </a>
                                        <?php if (!empty($requisition['linked_product_serial'])): ?>
                                            <span class="ml-1.5 text-xs font-mono bg-white border border-amber-200 px-2 py-0.5 rounded text-slate-700">
                                                N/S: <?= htmlspecialchars($requisition['linked_product_serial']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-xs text-slate-600 mt-0.5">Reparo geral sem produto específico selecionado no catálogo.</div>
                                <?php endif; ?>
                                <?php if (!empty($requisition['completed_at'])): ?>
                                    <div class="text-xs text-emerald-700 font-semibold mt-1">
                                        <i class="fas fa-check-circle mr-1"></i> Manutenção concluída em <?= formatDateTime($requisition['completed_at']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($requisition['linked_product_id'])): ?>
                            <a href="<?= url('produtos/ver?id=' . (int)$requisition['linked_product_id']) ?>" class="px-3 py-1.5 rounded-xl bg-white border border-amber-300 text-xs font-bold text-amber-900 hover:bg-amber-100 transition shrink-0">
                                <i class="fas fa-box mr-1"></i> Ficha do Produto
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

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
                                    <?php if ($hasValues): ?>
                                        <th class="py-3 px-4 text-right">Valor Unit.</th>
                                        <th class="py-3 px-4 text-right">Total</th>
                                    <?php endif; ?>
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
                                        <?php if ($hasValues): ?>
                                            <td class="py-3 px-4 text-right text-slate-600"><?= formatMoney($item['unit_price']) ?></td>
                                            <td class="py-3 px-4 text-right font-bold text-slate-900"><?= formatMoney($item['total_price']) ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <?php if ($hasValues): ?>
                                <tfoot>
                                    <tr class="bg-slate-50 border-t border-slate-200 font-bold text-slate-900 text-sm">
                                        <td colspan="5" class="py-3.5 px-4 text-right uppercase tracking-wider text-xs text-slate-500">Valor Total da Requisição:</td>
                                        <td class="py-3.5 px-4 text-right text-indigo-700 text-base"><?= formatMoney($requisition['total_amount']) ?></td>
                                    </tr>
                                </tfoot>
                            <?php else: ?>
                                <tfoot>
                                    <tr class="bg-slate-50/60 border-t border-slate-200 text-xs text-slate-500 italic">
                                        <td colspan="4" class="py-3 px-4 text-center">
                                            <i class="fas fa-tag text-slate-400 mr-1"></i> Requisição emitida sem valores pré-definidos (Cotação / Solicitação aberta).
                                        </td>
                                    </tr>
                                </tfoot>
                            <?php endif; ?>
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

                <!-- Fotos e Arquivos Anexos -->
                <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fas fa-paperclip text-indigo-600"></i> Fotos & Documentos Anexos
                            </h3>
                            <p class="text-xs text-slate-400">Fotos de materiais, propostas, laudos ou notas vinculadas a esta requisição.</p>
                        </div>
                        <button type="button" onclick="document.getElementById('addAttachmentBox').classList.toggle('hidden')" class="px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition">
                            <i class="fas fa-plus mr-1"></i> Anexar Arquivo
                        </button>
                    </div>

                    <!-- Formulário Rápido para Anexar Novo Arquivo -->
                    <div id="addAttachmentBox" class="hidden p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                        <form method="POST" action="<?= url('requisicoes/anexar') ?>" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="requisition_id" value="<?= $requisition['id'] ?>">
                            <input type="file" name="new_attachment" required accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" class="flex-1 text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-sm">
                                Fazer Upload
                            </button>
                        </form>
                    </div>

                    <!-- Galeria de Fotos e Anexos -->
                    <?php if (empty($attachments)): ?>
                        <div class="p-6 text-center text-slate-400 text-xs bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                            <i class="fas fa-images text-2xl mb-1 text-slate-300 block"></i>
                            Nenhuma foto ou documento anexado a esta requisição.
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                            <?php foreach ($attachments as $att): ?>
                                <?php 
                                    $isImg = str_starts_with($att['mime_type'], 'image/');
                                    $attUrl = asset($att['filename']);
                                ?>
                                <div class="group relative rounded-2xl border border-slate-200 bg-white p-2.5 overflow-hidden hover:shadow-md transition flex flex-col justify-between">
                                    <?php if ($isImg): ?>
                                        <a href="<?= $attUrl ?>" target="_blank" class="block aspect-video w-full rounded-xl overflow-hidden bg-slate-100 mb-2">
                                            <img src="<?= $attUrl ?>" alt="<?= htmlspecialchars($att['original_name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= $attUrl ?>" target="_blank" class="block aspect-video w-full rounded-xl bg-slate-100 flex items-center justify-center text-rose-500 text-3xl mb-2">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    <?php endif; ?>

                                    <div class="text-[11px] text-slate-700 font-medium truncate" title="<?= htmlspecialchars($att['original_name']) ?>">
                                        <?= htmlspecialchars($att['original_name']) ?>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1 pt-1 border-t border-slate-100">
                                        <span><?= number_format($att['file_size'] / 1024, 1) ?> KB</span>
                                        <div class="flex items-center gap-1">
                                            <a href="<?= $attUrl ?>" download="<?= htmlspecialchars($att['original_name']) ?>" class="text-indigo-600 hover:text-indigo-800" title="Baixar">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <form method="POST" action="<?= url('requisicoes/anexo/excluir') ?>" class="inline" onsubmit="return confirm('Deseja excluir este anexo?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                                                <input type="hidden" name="requisition_id" value="<?= $requisition['id'] ?>">
                                                <button type="submit" class="text-rose-500 hover:text-rose-700" title="Excluir">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Painel de Assinaturas Digitais Autenticadas -->
                <?php $isAdminRequester = (($requisition['requester_role'] ?? '') === 'admin'); ?>
                <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                            <i class="fas fa-signature text-indigo-600"></i> Assinaturas Digitais da Requisição
                        </h3>
                        <?php if ($isAdminRequester): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                Solicitante Administrador (dispensa assinatura de aprovador)
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 <?= (!$isAdminRequester || !empty($requisition['approver_signature'])) ? 'sm:grid-cols-2' : '' ?> gap-4 items-stretch">
                        <!-- Assinatura do Solicitante -->
                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col justify-between text-xs h-full">
                            <div>
                                <span class="font-bold text-slate-700 block mb-1">1. Solicitante Responsável <?= $isAdminRequester ? '(Admin)' : '' ?></span>
                                <span class="text-slate-500 block min-h-[18px]"><?= htmlspecialchars($requisition['requester_name']) ?> (<?= htmlspecialchars($requisition['department']) ?>)</span>
                            </div>
                            <div class="mt-3 p-2 bg-white rounded-lg border border-slate-200 h-24 flex items-center justify-center">
                                <?php if (!empty($requisition['requester_signature'])): ?>
                                    <img src="<?= htmlspecialchars(signature_image_src($requisition['requester_signature'])) ?>" alt="Assinatura do Solicitante" class="max-h-full max-w-full object-contain">
                                <?php else: ?>
                                    <span class="text-[11px] text-slate-400 italic">Autenticado eletronicamente por sessão</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-[10px] text-slate-400 mt-2 block">Emitido em: <?= formatDateTime($requisition['created_at']) ?></span>
                        </div>

                        <?php if (!$isAdminRequester || !empty($requisition['approver_signature'])): ?>
                            <!-- Assinatura do Aprovador -->
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col justify-between text-xs h-full">
                                <div>
                                    <span class="font-bold text-slate-700 block mb-1">2. Gestor / Aprovador</span>
                                    <span class="text-slate-500 block min-h-[18px]"><?= !empty($requisition['approver_name']) ? htmlspecialchars($requisition['approver_name']) : 'Pendente de Avaliação' ?></span>
                                </div>
                                <div class="mt-3 p-2 bg-white rounded-lg border border-slate-200 h-24 flex items-center justify-center">
                                    <?php if (!empty($requisition['approver_signature'])): ?>
                                        <img src="<?= htmlspecialchars(signature_image_src($requisition['approver_signature'])) ?>" alt="Assinatura do Aprovador" class="max-h-full max-w-full object-contain">
                                    <?php elseif ($requisition['status'] === 'aprovada' || $requisition['status'] === 'em_execucao' || $requisition['status'] === 'concluida'): ?>
                                        <span class="text-[11px] text-emerald-600 font-semibold flex items-center gap-1">
                                            <i class="fas fa-check-circle"></i> Aprovado digitalmente no sistema
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[11px] text-slate-400 italic">Aguardando aprovação</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[10px] text-slate-400 mt-2 block">
                                    <?= !empty($requisition['approved_at']) ? 'Decisão em: ' . formatDateTime($requisition['approved_at']) : 'Sem assinatura de aprovação' ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Histórico e Auditoria / Logs -->
                <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                            <i class="fas fa-history text-indigo-600"></i> Trilha de Auditoria & Histórico de Ações
                        </h3>
                    </div>

                    <!-- Resumo Rápido de Assinaturas da Requisição -->
                    <div class="grid grid-cols-1 <?= (!$isAdminRequester || !empty($requisition['approver_signature'])) ? 'sm:grid-cols-2' : '' ?> gap-3 pb-2">
                        <!-- Assinatura do Solicitante -->
                        <div class="p-3.5 rounded-xl border <?= !empty($requisition['requester_signature']) ? 'bg-emerald-50/70 border-emerald-200 text-emerald-950' : 'bg-slate-50 border-slate-200 text-slate-600' ?> text-xs space-y-1">
                            <div class="font-bold flex items-center gap-1.5 <?= !empty($requisition['requester_signature']) ? 'text-emerald-800' : 'text-slate-700' ?>">
                                <i class="fas <?= !empty($requisition['requester_signature']) ? 'fa-check-circle text-emerald-600' : 'fa-clock text-slate-400' ?>"></i>
                                <span>Assinatura do Solicitante <?= $isAdminRequester ? '(Admin)' : '' ?></span>
                            </div>
                            <?php if (!empty($requisition['requester_signature'])): ?>
                                <?php $reqIsCert = is_certificate_signature($requisition['requester_signature']); ?>
                                <p class="text-[11px] text-emerald-800">Assinado por: <strong><?= htmlspecialchars($requisition['requester_name']) ?></strong></p>
                                <p class="text-[10px] text-emerald-700">Registrado em: <?= formatDateTime($requisition['created_at']) ?></p>
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold <?= $reqIsCert ? 'bg-emerald-200/70 text-emerald-900' : 'bg-slate-200/70 text-slate-700' ?>">
                                    <?= $reqIsCert ? 'Certificado ICP-Brasil' : 'Assinatura Digital (Canvas)' ?>
                                </span>
                            <?php else: ?>
                                <p class="text-[11px] text-slate-400">Pendente de assinatura.</p>
                            <?php endif; ?>
                        </div>

                        <?php if (!$isAdminRequester || !empty($requisition['approver_signature'])): ?>
                            <!-- Assinatura do Aprovador -->
                            <div class="p-3.5 rounded-xl border <?= !empty($requisition['approver_signature']) ? 'bg-emerald-50/70 border-emerald-200 text-emerald-950' : 'bg-slate-50 border-slate-200 text-slate-600' ?> text-xs space-y-1">
                                <div class="font-bold flex items-center gap-1.5 <?= !empty($requisition['approver_signature']) ? 'text-emerald-800' : 'text-slate-700' ?>">
                                    <i class="fas <?= !empty($requisition['approver_signature']) ? 'fa-check-circle text-emerald-600' : 'fa-clock text-slate-400' ?>"></i>
                                    <span>Assinatura do Aprovador</span>
                                </div>
                                <?php if (!empty($requisition['approver_signature'])): ?>
                                    <?php $appIsCert = is_certificate_signature($requisition['approver_signature']); ?>
                                    <p class="text-[11px] text-emerald-800">Aprovado e assinado por: <strong><?= htmlspecialchars($requisition['approver_name'] ?? 'Gestor') ?></strong></p>
                                    <p class="text-[10px] text-emerald-700">Registrado em: <?= formatDateTime($requisition['approved_at']) ?></p>
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold <?= $appIsCert ? 'bg-emerald-200/70 text-emerald-900' : 'bg-slate-200/70 text-slate-700' ?>">
                                        <?= $appIsCert ? 'Certificado ICP-Brasil' : 'Assinatura Digital (Canvas)' ?>
                                    </span>
                                <?php else: ?>
                                    <p class="text-[11px] text-slate-400">Aguardando aprovação formal.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Linha do Tempo dos Eventos -->
                    <div class="relative pl-6 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 pt-2">
                        <?php if (empty($logs)): ?>
                            <p class="text-xs text-slate-400">Nenhum evento registrado até o momento.</p>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <?php 
                                    $isSigEvent = str_contains($log['action'], 'Assinatura') || str_contains($log['description'], 'assinada');
                                    $isEditEvent = str_contains($log['action'], 'Edição') || str_contains($log['action'], 'editada');
                                    $dotColor = $isSigEvent ? 'bg-emerald-500 ring-emerald-100' : ($isEditEvent ? 'bg-amber-500 ring-amber-100' : 'bg-indigo-600 ring-white');
                                ?>
                                <div class="relative">
                                    <div class="absolute -left-6 top-1.5 w-3 h-3 rounded-full <?= $dotColor ?> ring-4"></div>
                                    <div class="text-xs">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <?php if ($isSigEvent): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1">
                                                    <i class="fas fa-certificate text-[9px]"></i> <?= htmlspecialchars($log['action']) ?>
                                                </span>
                                            <?php elseif ($isEditEvent): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1">
                                                    <i class="fas fa-edit text-[9px]"></i> <?= htmlspecialchars($log['action']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="font-bold text-slate-800"><?= htmlspecialchars($log['action']) ?></span>
                                            <?php endif; ?>
                                            <span class="text-slate-400">&bull;</span>
                                            <span class="text-slate-500 font-medium"><?= formatDateTime($log['created_at']) ?></span>
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

                <!-- Vínculo Contratual -->
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

                <!-- Dados Complementares -->
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

                <?php if (!empty($qrCodeUrl)): ?>
                    <!-- QR Code de Visualização Online (Sistema Hospedado na Web) -->
                    <div class="bg-white rounded-2xl p-6 border rm-req-card shadow-sm space-y-3 text-center">
                        <h3 class="text-xs font-bold uppercase tracking-wider rm-req-accent flex items-center justify-center gap-1.5">
                            <i class="fas fa-qrcode"></i> Acesso Online por QR Code
                        </h3>
                        <div id="showQrContainer" class="inline-block p-2.5 bg-white rounded-xl border border-slate-200 shadow-inner"></div>
                        <p class="text-[11px] text-slate-500 leading-relaxed">
                            Escaneie ou compartilhe o link seguro abaixo para consulta online desta requisição:
                        </p>
                        <div class="flex items-center gap-1.5">
                            <input type="text" readonly value="<?= htmlspecialchars($qrCodeUrl) ?>" id="publicTokenUrlInput" class="w-full text-[11px] font-mono rounded-lg py-1.5 px-2 bg-slate-50">
                            <button type="button" onclick="copyPublicQrLink()" class="px-2.5 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold shrink-0" title="Copiar Link">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL DE APROVAÇÃO COM ASSINATURA DIGITAL DO GESTOR      -->
<!-- ======================================================== -->
<div id="approvalModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 border border-slate-100 space-y-4">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="font-bold text-lg text-slate-900 flex items-center gap-2">
                <i class="fas fa-check-circle text-emerald-600"></i> Aprovar Requisição com Assinatura
            </h3>
            <button type="button" onclick="closeApprovalModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <p class="text-xs text-slate-500">
            Você está aprovando a requisição <strong><?= $requisition['code'] ?></strong>. Escolha a forma de autenticação formal:
        </p>

        <form method="POST" action="<?= url('requisicoes/status') ?>" id="approvalForm" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $requisition['id'] ?>">
            <input type="hidden" name="status" value="aprovada">
            <input type="hidden" name="approver_signature" id="approverSignatureInput" value="">

            <!-- Seletor de Tipo de Assinatura -->
            <div class="flex rounded-xl bg-slate-100 p-1 text-xs font-semibold gap-1">
                <button type="button" onclick="setApproverSigMode('canvas')" id="btnAppModeCanvas" class="flex-1 py-1.5 rounded-lg bg-white text-indigo-700 shadow-sm transition">
                    <i class="fas fa-pen-nib mr-1"></i> Desenhar na Tela
                </button>
                <button type="button" onclick="setApproverSigMode('cert')" id="btnAppModeCert" class="flex-1 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition">
                    <i class="fas fa-certificate mr-1 text-emerald-600"></i> Certificado Digital (Windows / Linux)
                </button>
            </div>

            <!-- Modo 1: Canvas Manual -->
            <div id="approverCanvasContainer" class="border-2 border-slate-300 rounded-2xl p-2 bg-slate-50">
                <div class="flex justify-between items-center text-[11px] text-slate-400 mb-1">
                    <span>Desenhe sua assinatura no quadro:</span>
                    <button type="button" onclick="clearApproverSig()" class="text-indigo-600 hover:underline">Limpar</button>
                </div>
                <canvas id="approverCanvas" width="450" height="120" class="w-full h-28 bg-white rounded-xl border border-slate-200 cursor-crosshair touch-none"></canvas>
            </div>

            <!-- Modo 2: Integração Nativa com Certificados Instalados no Windows / Linux -->
            <div id="approverCertContainer" class="hidden border border-emerald-200 rounded-2xl p-4 bg-emerald-50/50 space-y-3">
                <div class="text-xs text-emerald-950 font-bold flex items-center gap-1.5">
                    <i class="fas fa-shield-alt text-emerald-600"></i> Integração Direta com Certificados do Sistema (A1 ou Token A3)
                </div>
                <p class="text-[11px] text-slate-600 leading-relaxed">
                    O navegador exibirá o seletor oficial de certificados digitais instalados no seu <strong>Windows</strong> ou <strong>Linux</strong> (A1 ou Token/Smartcard A3 com PIN).
                </p>
                <button type="button" onclick="openApproverCertSigner()" id="btnValidateAppCert" class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                    <i class="fas fa-id-card"></i> Selecionar Certificado Instalado no Sistema (A1 / A3)
                </button>
            </div>

            <!-- Feedback e Prévia do Selo Digital Gerado -->
            <div id="approverSealFeedback" class="hidden p-3 rounded-xl border border-emerald-300 bg-emerald-50 text-xs text-emerald-900 space-y-2">
                <div class="font-bold flex items-center gap-1.5">
                    <i class="fas fa-check-circle text-emerald-600"></i> <span id="approverSealTitle">Certificado Digital Autenticado com Sucesso!</span>
                </div>
                <div id="approverSealPreview" class="bg-white p-2 rounded-lg border border-emerald-200 flex justify-center"></div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeApprovalModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Cancelar</button>
                <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md">Confirmar e Aprovar</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL DE ENVIO DA REQUISIÇÃO POR E-MAIL                 -->
<!-- ======================================================== -->
<div id="emailModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 border border-slate-100 space-y-4">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="font-bold text-lg text-slate-900 flex items-center gap-2">
                <i class="fas fa-paper-plane text-indigo-600"></i> Enviar Requisição por E-mail
            </h3>
            <button type="button" onclick="closeEmailModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="<?= url('requisicoes/enviar-email') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $requisition['id'] ?>">

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">E-mail do Destinatário <span class="text-rose-500">*</span></label>
                <input type="email" name="recipient_email" required value="<?= htmlspecialchars($requisition['supplier_email'] ?? '') ?>" placeholder="ex: compras@fornecedor.com.br" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome do Destinatário</label>
                <input type="text" name="recipient_name" value="<?= htmlspecialchars($requisition['supplier_name']) ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Assunto do E-mail</label>
                <input type="text" name="email_subject" value="Requisição <?= htmlspecialchars($requisition['code']) ?> - <?= htmlspecialchars($requisition['title']) ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Mensagem Adicional (Opcional)</label>
                <textarea name="email_message" rows="3" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Olá, segue a requisição formal para atendimento..."></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeEmailModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Cancelar</button>
                <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md">Enviar E-mail Agora</button>
            </div>
        </form>
    </div>
</div>

<script>
    // --------------------------------------------------------
    // EXPORTAÇÃO OFICIAL DA FICHA A4 PARA PDF
    // --------------------------------------------------------
    function exportToPdf() {
        const btn = document.getElementById('btnExportPdf');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Gerando Ficha A4 em PDF...';
        btn.disabled = true;

        let iframe = document.getElementById('pdfDownloadIframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'pdfDownloadIframe';
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            document.body.appendChild(iframe);
        }
        iframe.src = '<?= url("requisicoes/imprimir?id=" . $requisition["id"] . "&auto_pdf=1") ?>';

        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }, 4000);
    }

    // --------------------------------------------------------
    // MODAL DE ASSINATURA DO APROVADOR
    // --------------------------------------------------------
    const approverCanvas = document.getElementById('approverCanvas');
    let approverCtx, approverDrawing = false, approverSigned = false;

    if (approverCanvas) {
        approverCtx = approverCanvas.getContext('2d');
        approverCtx.strokeStyle = '#0f172a';
        approverCtx.lineWidth = 2.5;
        approverCtx.lineCap = 'round';

        function getApproverCoords(e) {
            const rect = approverCanvas.getBoundingClientRect();
            const scaleX = approverCanvas.width / rect.width;
            const scaleY = approverCanvas.height / rect.height;
            let cx = e.clientX, cy = e.clientY;
            if (e.touches && e.touches.length > 0) {
                cx = e.touches[0].clientX;
                cy = e.touches[0].clientY;
            }
            return { x: (cx - rect.left) * scaleX, y: (cy - rect.top) * scaleY };
        }

        approverCanvas.addEventListener('mousedown', e => {
            approverDrawing = true;
            const pos = getApproverCoords(e);
            approverCtx.beginPath();
            approverCtx.moveTo(pos.x, pos.y);
        });

        approverCanvas.addEventListener('mousemove', e => {
            if (!approverDrawing) return;
            const pos = getApproverCoords(e);
            approverCtx.lineTo(pos.x, pos.y);
            approverCtx.stroke();
            approverSigned = true;
        });

        window.addEventListener('mouseup', () => approverDrawing = false);

        approverCanvas.addEventListener('touchstart', e => {
            approverDrawing = true;
            const pos = getApproverCoords(e);
            approverCtx.beginPath();
            approverCtx.moveTo(pos.x, pos.y);
            e.preventDefault();
        }, { passive: false });

        approverCanvas.addEventListener('touchmove', e => {
            if (!approverDrawing) return;
            const pos = getApproverCoords(e);
            approverCtx.lineTo(pos.x, pos.y);
            approverCtx.stroke();
            approverSigned = true;
            e.preventDefault();
        }, { passive: false });

        window.addEventListener('touchend', () => approverDrawing = false);

        document.getElementById('approvalForm').addEventListener('submit', function(e) {
            if (currentApproverMode === 'canvas' && approverSigned) {
                document.getElementById('approverSignatureInput').value = approverCanvas.toDataURL('image/png');
            }
        });
    }

    let currentApproverMode = 'canvas';

    function setApproverSigMode(mode) {
        currentApproverMode = mode;
        const btnCanvas = document.getElementById('btnAppModeCanvas');
        const btnCert = document.getElementById('btnAppModeCert');
        const cCanvas = document.getElementById('approverCanvasContainer');
        const cCert = document.getElementById('approverCertContainer');

        btnCanvas.className = 'flex-1 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition';
        btnCert.className = 'flex-1 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition';

        cCanvas.classList.add('hidden');
        cCert.classList.add('hidden');

        if (mode === 'canvas') {
            btnCanvas.className = 'flex-1 py-1.5 rounded-lg bg-white text-indigo-700 shadow-sm transition';
            cCanvas.classList.remove('hidden');
        } else {
            btnCert.className = 'flex-1 py-1.5 rounded-lg bg-white text-emerald-700 shadow-sm transition';
            cCert.classList.remove('hidden');
        }
    }

    let appCertPollTimer = null;

    function applyApproverCertResult(data) {
        if (!data || !data.success) return;
        if (appCertPollTimer) {
            clearInterval(appCertPollTimer);
            appCertPollTimer = null;
        }
        document.getElementById('approverSignatureInput').value = data.seal_image;
        approverSigned = true;
        document.getElementById('approverSealTitle').textContent = `Certificado Autenticado: ${data.signer_name}`;
        const preview = document.getElementById('approverSealPreview');
        preview.innerHTML = '';
        const img = document.createElement('img');
        img.src = data.seal_image;
        img.alt = 'Selo Digital';
        img.className = 'max-h-24';
        preview.appendChild(img);
        document.getElementById('approverSealFeedback').classList.remove('hidden');
    }

    function openApproverCertSigner() {
        const nonce = 'rm_app_' + Date.now() + '_' + Math.random().toString(36).substring(2, 10);
        const httpsBase = <?= json_encode(cert_auth_url($companySettings ?? [])) ?>;
        const docTitle = <?= json_encode($requisition['code'] . ' - ' . $requisition['title']) ?>;
        const targetUrl = `${httpsBase}?ctx=approver&nonce=${encodeURIComponent(nonce)}&doc_title=${encodeURIComponent(docTitle)}`;
        const popup = window.open(targetUrl, 'rmCertPopup', 'width=520,height=560,menubar=no,toolbar=no,location=yes,status=no');
        if (!popup) {
            alert('Por favor, permita a abertura da janela pop-up para selecionar o certificado instalado no navegador.');
            return;
        }
        if (appCertPollTimer) clearInterval(appCertPollTimer);
        appCertPollTimer = setInterval(() => {
            fetch(`<?= url('requisicoes/assinar-certificado') ?>?nonce=${encodeURIComponent(nonce)}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.success) {
                        applyApproverCertResult(data);
                    } else if (popup.closed) {
                        clearInterval(appCertPollTimer);
                        appCertPollTimer = null;
                    }
                })
                .catch(() => {
                    if (popup.closed) {
                        clearInterval(appCertPollTimer);
                        appCertPollTimer = null;
                    }
                });
        }, 1200);
    }

    window.addEventListener('message', function(event) {
        if (!event.data || event.data.type !== 'RM_CERT_SIGNED') return;
        if (event.data.ctx !== 'approver') return;
        applyApproverCertResult(event.data.result);
    });

    function copyPublicQrLink() {
        const inp = document.getElementById('publicTokenUrlInput');
        if (inp) {
            inp.select();
            navigator.clipboard?.writeText(inp.value);
            alert('Link online copiado para a área de transferência!');
        }
    }

    function openApprovalModal() {
        document.getElementById('approvalModal').classList.remove('hidden');
    }

    function closeApprovalModal() {
        document.getElementById('approvalModal').classList.add('hidden');
    }

    function clearApproverSig() {
        if (approverCtx) {
            approverCtx.clearRect(0, 0, approverCanvas.width, approverCanvas.height);
            approverSigned = false;
            document.getElementById('approverSignatureInput').value = '';
            document.getElementById('approverSealFeedback').classList.add('hidden');
        }
    }

    // --------------------------------------------------------
    // MODAL DE E-MAIL
    // --------------------------------------------------------
    function openEmailModal() {
        document.getElementById('emailModal').classList.remove('hidden');
    }

    function closeEmailModal() {
        document.getElementById('emailModal').classList.add('hidden');
    }
</script>
<?php if (!empty($qrCodeUrl)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const el = document.getElementById('showQrContainer');
        if (el && typeof QRCode !== 'undefined') {
            new QRCode(el, {
                text: <?= json_encode($qrCodeUrl) ?>,
                width: 128,
                height: 128,
                colorDark: "#0f172a",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    });
</script>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
