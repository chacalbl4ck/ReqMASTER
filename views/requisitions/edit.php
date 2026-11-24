<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';

$companyName = $companySettings['company_name'] ?? 'Nossa Empresa';
$companyCnpj = $companySettings['company_cnpj'] ?? '';
?>

<div class="max-w-5xl mx-auto space-y-6">
    <!-- Cabeçalho -->
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= url('requisicoes/ver?id=' . $requisition['id']) ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition flex items-center gap-1 mb-1">
                <i class="fas fa-arrow-left"></i> Voltar aos detalhes
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Editar Requisição <?= htmlspecialchars($requisition['code']) ?></h1>
                <?= statusBadge($requisition['status']) ?>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Modo de Edição Administrativa &bull; Todas as alterações serão registradas na Trilha de Auditoria.</p>
        </div>
    </div>

    <!-- Alerta de Auditoria -->
    <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-start gap-3">
        <i class="fas fa-shield-alt text-amber-600 text-base mt-0.5 shrink-0"></i>
        <div>
            <div class="font-bold">Aviso de Auditoria:</div>
            <p class="mt-0.5">Você está editando esta requisição como <strong>Administrador</strong>. Os dados anteriores, novos valores e a relação de itens modificados serão gravados no histórico permanente do sistema.</p>
        </div>
    </div>

    <form method="POST" action="<?= url('requisicoes/atualizar') ?>" id="requisitionEditForm" class="space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $requisition['id'] ?>">

        <!-- Bloco 1: Dados Gerais -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-3 flex items-center gap-2">
                <i class="fas fa-info-circle text-indigo-600"></i> Informações Principais
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Tipo da Requisição -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tipo de Solicitação <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center justify-center p-2.5 border rounded-xl cursor-pointer text-xs font-semibold <?= $requisition['type'] === 'material' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600' ?>">
                            <input type="radio" name="type" value="material" <?= $requisition['type'] === 'material' ? 'checked' : '' ?> class="sr-only">
                            <i class="fas fa-box mr-1.5"></i> Material
                        </label>
                        <label class="flex items-center justify-center p-2.5 border rounded-xl cursor-pointer text-xs font-semibold <?= $requisition['type'] === 'servico' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600' ?>">
                            <input type="radio" name="type" value="servico" <?= $requisition['type'] === 'servico' ? 'checked' : '' ?> class="sr-only">
                            <i class="fas fa-tools mr-1.5"></i> Serviço
                        </label>
                    </div>
                </div>

                <!-- Título -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Título / Objeto Resumido <span class="text-rose-500">*</span></label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($requisition['title']) ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Centro de Custo / Departamento -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Centro de Custo <span class="text-rose-500">*</span></label>
                    <select name="department" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                        <?php
                            $currentDept = (string)($requisition['department'] ?? '');
                            $foundInCc = false;
                        ?>
                        <?php foreach ($costCenters ?? [] as $cc): ?>
                            <?php
                                $ccLabel = $cc['code'] . ' - ' . $cc['name'];
                                $sel = ($currentDept === $ccLabel || $currentDept === $cc['name']);
                                if ($sel) $foundInCc = true;
                            ?>
                            <option value="<?= htmlspecialchars($ccLabel) ?>" <?= $sel ? 'selected' : '' ?>><?= htmlspecialchars($ccLabel) ?></option>
                        <?php endforeach; ?>
                        <?php if (!$foundInCc && $currentDept !== ''): ?>
                            <option value="<?= htmlspecialchars($currentDept) ?>" selected><?= htmlspecialchars($currentDept) ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Prioridade -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Prioridade</label>
                    <select name="priority" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                        <option value="baixa" <?= $requisition['priority'] === 'baixa' ? 'selected' : '' ?>>Baixa</option>
                        <option value="media" <?= $requisition['priority'] === 'media' ? 'selected' : '' ?>>Média</option>
                        <option value="alta" <?= $requisition['priority'] === 'alta' ? 'selected' : '' ?>>Alta</option>
                        <option value="urgente" <?= $requisition['priority'] === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                    </select>
                </div>

                <!-- Prazo Limite -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Data Limite Desejada</label>
                    <input type="date" name="deadline" value="<?= htmlspecialchars($requisition['deadline'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                </div>
            </div>

            <!-- Justificativa -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Justificativa da Necessidade <span class="text-rose-500">*</span></label>
                <textarea name="justification" rows="3" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"><?= htmlspecialchars($requisition['justification']) ?></textarea>
            </div>
        </div>

        <!-- Bloco 2: Fornecedor e Contrato -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-3 flex items-center gap-2">
                <i class="fas fa-building text-indigo-600"></i> Fornecedor / Para Quem Solicitar & Contrato
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Fornecedor -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Fornecedor / Prestador <span class="text-rose-500">*</span></label>
                    <select name="supplier_id" id="supplier_select" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                        <option value="">Selecione o Fornecedor...</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= (int)$requisition['supplier_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?> (<?= \App\Helpers\Security::formatDocument($s['document']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Contrato -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Vínculo Contratual (Opcional)</label>
                    <select name="contract_id" id="contract_select" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                        <option value="">Sem Contrato (Compra / Serviço Avulso)</option>
                        <?php foreach ($contracts as $c): ?>
                            <option value="<?= $c['id'] ?>" data-supplier-id="<?= $c['supplier_id'] ?? '' ?>" data-balance="<?= $c['balance_value'] ?? 0 ?>" <?= (int)($requisition['contract_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['contract_number']) ?> - <?= htmlspecialchars($c['title']) ?> (Saldo: <?= formatMoney($c['balance_value']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Bloco 3: Itens da Requisição -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                        <i class="fas fa-list text-indigo-600"></i> Itens / Escopo da Solicitação
                    </h2>
                    <p class="text-xs text-slate-400">Preços unitários são opcionais. Caso não preenchidos, a requisição é tratada como cotação aberta.</p>
                </div>
                <button type="button" id="btnAddItemRow" class="px-3.5 py-1.5 rounded-xl bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-bold transition flex items-center gap-1.5">
                    <i class="fas fa-plus"></i> Adicionar Item
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-semibold text-slate-500 uppercase">
                            <th class="py-2.5 px-3 w-10">#</th>
                            <th class="py-2.5 px-3">Descrição do Item / Serviço <span class="text-rose-500">*</span></th>
                            <th class="py-2.5 px-3 w-28">Especificações</th>
                            <th class="py-2.5 px-3 w-20 text-center">Unid.</th>
                            <th class="py-2.5 px-3 w-24 text-right">Qtd</th>
                            <th class="py-2.5 px-3 w-32 text-right">Preço Un.</th>
                            <th class="py-2.5 px-3 w-28 text-right">Subtotal</th>
                            <th class="py-2.5 px-3 w-10 text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody" class="divide-y divide-slate-100">
                        <?php if (empty($items)): ?>
                            <tr>
                                <td class="py-2.5 px-3 text-slate-400 font-mono item-row-num">1</td>
                                <td class="py-2.5 px-3">
                                    <input type="text" name="item_description[]" required class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                                </td>
                                <td class="py-2.5 px-3">
                                    <input type="text" name="item_specs[]" class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Marca / Ref">
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <input type="text" name="item_unit[]" value="un" class="w-full text-xs rounded-lg border-slate-300 text-center focus:border-indigo-500 focus:ring-indigo-500">
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <input type="number" step="0.01" min="0.01" name="item_quantity[]" value="1.00" required class="calc-qty w-full text-xs rounded-lg border-slate-300 text-right focus:border-indigo-500 focus:ring-indigo-500">
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <input type="text" name="item_price[]" placeholder="R$ 0,00" class="calc-price w-full text-xs rounded-lg border-slate-300 text-right focus:border-indigo-500 focus:ring-indigo-500">
                                </td>
                                <td class="py-2.5 px-3 text-right font-semibold text-slate-800 row-subtotal">-</td>
                                <td class="py-2.5 px-3 text-center">
                                    <button type="button" class="btn-remove-row text-slate-300 hover:text-rose-600 transition"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $idx => $it): ?>
                                <tr>
                                    <td class="py-2.5 px-3 text-slate-400 font-mono item-row-num"><?= $idx + 1 ?></td>
                                    <td class="py-2.5 px-3">
                                        <input type="text" name="item_description[]" required value="<?= htmlspecialchars($it['description']) ?>" class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <input type="text" name="item_specs[]" value="<?= htmlspecialchars($it['technical_specs'] ?? '') ?>" class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Marca / Ref">
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <input type="text" name="item_unit[]" value="<?= htmlspecialchars($it['unit']) ?>" class="w-full text-xs rounded-lg border-slate-300 text-center focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <input type="number" step="0.01" min="0.01" name="item_quantity[]" value="<?= htmlspecialchars((string)$it['quantity']) ?>" required class="calc-qty w-full text-xs rounded-lg border-slate-300 text-right focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <input type="text" name="item_price[]" value="<?= (float)$it['unit_price'] > 0 ? number_format((float)$it['unit_price'], 2, ',', '.') : '' ?>" placeholder="R$ 0,00" class="calc-price w-full text-xs rounded-lg border-slate-300 text-right focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-semibold text-slate-800 row-subtotal">
                                        <?= (float)$it['total_price'] > 0 ? formatMoney($it['total_price']) : '-' ?>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <button type="button" class="btn-remove-row text-slate-300 hover:text-rose-600 transition"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total Calculado -->
            <div class="flex justify-end items-center gap-4 pt-3 border-t border-slate-100">
                <span class="text-xs font-semibold text-slate-500 uppercase">Valor Total Calculado:</span>
                <span id="grandTotalSpan" class="text-lg font-black text-indigo-700">
                    <?= !empty($requisition['has_values']) && (float)$requisition['total_amount'] > 0 ? formatMoney($requisition['total_amount']) : 'Sem valor' ?>
                </span>
            </div>
        </div>

        <!-- Ações do Formulário -->
        <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
            <a href="<?= url('requisicoes/ver?id=' . $requisition['id']) ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition">
                Cancelar
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-600/30 transition flex items-center gap-2">
                <i class="fas fa-save"></i> Salvar Alterações na Requisição
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.getElementById('itemsTableBody');
        const btnAdd = document.getElementById('btnAddItemRow');
        const grandTotalSpan = document.getElementById('grandTotalSpan');

        function parseMoney(val) {
            if (!val) return 0;
            let cleaned = val.toString().replace(/R\$/g, '').replace(/\s/g, '').replace(/\./g, '').replace(/,/g, '.').trim();
            let num = parseFloat(cleaned);
            return isNaN(num) ? 0 : num;
        }

        function recalcRow(row) {
            const qty = parseFloat(row.querySelector('.calc-qty')?.value || 0);
            const price = parseMoney(row.querySelector('.calc-price')?.value);
            const subtotalCell = row.querySelector('.row-subtotal');
            if (price > 0 && qty > 0) {
                const sub = qty * price;
                subtotalCell.textContent = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(sub);
            } else {
                subtotalCell.textContent = '-';
            }
        }

        function recalcAll() {
            let total = 0;
            let hasAny = false;
            tableBody.querySelectorAll('tr').forEach(r => {
                recalcRow(r);
                const qty = parseFloat(r.querySelector('.calc-qty')?.value || 0);
                const price = parseMoney(r.querySelector('.calc-price')?.value);
                if (price > 0) {
                    hasAny = true;
                    total += (qty * price);
                }
            });

            if (hasAny && total > 0) {
                grandTotalSpan.textContent = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(total);
            } else {
                grandTotalSpan.textContent = 'Sem valor';
            }
        }

        tableBody.addEventListener('input', function (e) {
            if (e.target.classList.contains('calc-qty') || e.target.classList.contains('calc-price')) {
                recalcAll();
            }
        });

        tableBody.addEventListener('click', function (e) {
            const btnRemove = e.target.closest('.btn-remove-row');
            if (btnRemove) {
                const rows = tableBody.querySelectorAll('tr');
                if (rows.length > 1) {
                    btnRemove.closest('tr').remove();
                    // Atualiza contadores
                    tableBody.querySelectorAll('tr').forEach((r, idx) => {
                        const numCell = r.querySelector('.item-row-num');
                        if (numCell) numCell.textContent = idx + 1;
                    });
                    recalcAll();
                } else {
                    alert('A requisição precisa ter pelo menos um item.');
                }
            }
        });

        btnAdd?.addEventListener('click', function () {
            const rowCount = tableBody.querySelectorAll('tr').length + 1;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="py-2.5 px-3 text-slate-400 font-mono item-row-num">${rowCount}</td>
                <td class="py-2.5 px-3">
                    <input type="text" name="item_description[]" required class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </td>
                <td class="py-2.5 px-3">
                    <input type="text" name="item_specs[]" class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Marca / Ref">
                </td>
                <td class="py-2.5 px-3 text-center">
                    <input type="text" name="item_unit[]" value="un" class="w-full text-xs rounded-lg border-slate-300 text-center focus:border-indigo-500 focus:ring-indigo-500">
                </td>
                <td class="py-2.5 px-3 text-right">
                    <input type="number" step="0.01" min="0.01" name="item_quantity[]" value="1.00" required class="calc-qty w-full text-xs rounded-lg border-slate-300 text-right focus:border-indigo-500 focus:ring-indigo-500">
                </td>
                <td class="py-2.5 px-3 text-right">
                    <input type="text" name="item_price[]" placeholder="R$ 0,00" class="calc-price w-full text-xs rounded-lg border-slate-300 text-right focus:border-indigo-500 focus:ring-indigo-500">
                </td>
                <td class="py-2.5 px-3 text-right font-semibold text-slate-800 row-subtotal">-</td>
                <td class="py-2.5 px-3 text-center">
                    <button type="button" class="btn-remove-row text-slate-300 hover:text-rose-600 transition"><i class="fas fa-trash"></i></button>
                </td>
            `;
            tableBody.appendChild(tr);
        });

        recalcAll();
    });
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
