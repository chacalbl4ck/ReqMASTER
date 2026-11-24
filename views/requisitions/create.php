<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="max-w-5xl mx-auto space-y-6">
    <!-- Cabeçalho -->
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= url('requisicoes') ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition flex items-center gap-1 mb-1">
                <i class="fas fa-arrow-left"></i> Voltar para a lista
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Nova Requisição</h1>
            <p class="text-xs sm:text-sm text-slate-500">Cadastre uma solicitação para compra de materiais ou prestação de serviços com ou sem contrato.</p>
        </div>
    </div>

    <form method="POST" action="<?= url('requisicoes/salvar') ?>" class="space-y-6">
        <?= csrf_field() ?>

        <!-- Bloco 1: Tipo e Identificação Principal -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-5">
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">1</span>
                Classificação da Requisição
            </h2>

            <!-- Seletor de Tipo: Compra de Material vs Prestação de Serviço -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="relative flex items-center p-4 border rounded-xl cursor-pointer hover:border-indigo-400 transition bg-slate-50/50 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/40 has-[:checked]:ring-1 has-[:checked]:ring-indigo-600">
                    <input type="radio" name="type" value="material" class="sr-only" <?= $reqType === 'material' ? 'checked' : '' ?>>
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-lg mr-3">
                        <i class="fas fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-slate-800">Compra de Materiais</span>
                        <span class="block text-xs text-slate-500">Insumos, equipamentos, peças, papelaria, consumíveis.</span>
                    </div>
                </label>

                <label class="relative flex items-center p-4 border rounded-xl cursor-pointer hover:border-indigo-400 transition bg-slate-50/50 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/40 has-[:checked]:ring-1 has-[:checked]:ring-indigo-600">
                    <input type="radio" name="type" value="servico" class="sr-only" <?= $reqType === 'servico' ? 'checked' : '' ?>>
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-lg mr-3">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-slate-800">Prestação de Serviços</span>
                        <span class="block text-xs text-slate-500">Manutenções, consultorias, reformas, obras, TI, licenças.</span>
                    </div>
                </label>
            </div>

            <!-- Dados Básicos -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                        Título Resumido da Requisição <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="title" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Ex: Aquisição de Nobreaks para a Sala de Servidores">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                        Departamento / Centro de Custo <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="department" required value="<?= htmlspecialchars($currentUser['department'] ?? 'Geral') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Prioridade</label>
                    <select name="priority" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                        <option value="baixa">Baixa (Rotina normal)</option>
                        <option value="media" selected>Média (Padrão)</option>
                        <option value="alta">Alta (Prioritária)</option>
                        <option value="urgente">Urgente (Crítico / Parada)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Prazo Desejado de Atendimento</label>
                    <input type="date" name="deadline" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                </div>
            </div>
        </div>

        <!-- Bloco 2: Para Quem Solicitar & Vínculo Contratual -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-5">
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">2</span>
                Destinatário & Vínculo Contratual
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Fornecedor / Para Quem -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                            Para Quem Solicita (Fornecedor/Prestador) <span class="text-rose-500">*</span>
                        </label>
                        <a href="<?= url('fornecedores/novo') ?>" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold">
                            + Novo Fornecedor
                        </a>
                    </div>
                    <select name="supplier_id" id="supplier_select" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                        <option value="">-- Selecione o Fornecedor ou Prestador --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($selectedSupplierId === (int)$s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['category']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Ao selecionar o fornecedor, os contratos ativos serão carregados automaticamente ao lado.</p>
                </div>

                <!-- Possibilidade de Contrato -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                        Vincular a um Contrato Existente? (Opcional)
                    </label>
                    <select name="contract_id" id="contract_select" <?= empty($contracts) ? 'disabled' : '' ?> class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                        <option value="">-- Sem vínculo com contrato (compra avulsa/pontual) --</option>
                        <?php foreach ($contracts as $c): ?>
                            <option value="<?= $c['id'] ?>" data-balance="<?= $c['balance_value'] ?>" <?= ($selectedContractId === (int)$c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['contract_number']) ?> - <?= htmlspecialchars($c['title']) ?> (Saldo: <?= formatMoney($c['balance_value']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Alerta de Saldo do Contrato -->
                    <div id="contract_info_box" class="mt-2 p-2.5 rounded-lg bg-indigo-50/70 border border-indigo-200 text-xs text-indigo-800 flex items-center justify-between <?= empty($selectedContractId) ? 'hidden' : '' ?>">
                        <span><i class="fas fa-file-contract mr-1"></i> Saldo Disponível no Contrato:</span>
                        <strong id="contract_balance_span" class="font-bold text-indigo-900">R$ 0,00</strong>
                    </div>

                    <div id="contract_balance_warning" class="mt-2 p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-800 hidden flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-rose-600"></i>
                        <span>Atenção: O total dos itens adicionados excede o saldo disponível do contrato!</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bloco 3: Itens / Serviços da Requisição Dinâmicos -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                <div>
                    <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">3</span>
                        Itens / Serviços Requisitados
                    </h2>
                    <p class="text-xs text-slate-400">Adicione os materiais ou serviços necessários. O subtotal e total geral são calculados automaticamente.</p>
                </div>
                <button type="button" id="btn_add_item" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold transition">
                    <i class="fas fa-plus"></i> Adicionar Item / Linha
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                            <th class="py-2.5 px-3">Descrição / Especificações</th>
                            <th class="py-2.5 px-3 w-28">Unidade</th>
                            <th class="py-2.5 px-3 w-28 text-right">Quantidade</th>
                            <th class="py-2.5 px-3 w-36 text-right">Valor Unitário Estimado</th>
                            <th class="py-2.5 px-3 w-32 text-right">Subtotal</th>
                            <th class="py-2.5 px-2 w-12 text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="items_table_body">
                        <!-- Linha 1 padrão -->
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                            <td class="py-3 px-3">
                                <input type="text" name="item_description[]" required class="w-full text-sm rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Descrição do material ou escopo do serviço">
                                <input type="text" name="item_specs[]" class="w-full text-xs text-slate-500 rounded border-slate-200 mt-1 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Especificações técnicas, marca de referência, etc (opcional)">
                            </td>
                            <td class="py-3 px-3 w-28">
                                <select name="item_unit[]" class="w-full text-sm rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="un">UN</option>
                                    <option value="cx">CX</option>
                                    <option value="pct">PCT</option>
                                    <option value="kg">KG</option>
                                    <option value="m">M</option>
                                    <option value="l">L</option>
                                    <option value="hr">HORA</option>
                                    <option value="dia">DIÁRIA</option>
                                    <option value="mes">MÊS</option>
                                    <option value="serv">SERVIÇO</option>
                                    <option value="gl">GLOBAL</option>
                                </select>
                            </td>
                            <td class="py-3 px-3 w-28">
                                <input type="number" step="0.01" min="0.01" value="1.00" name="item_quantity[]" required class="calc-qty w-full text-sm text-right rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="py-3 px-3 w-36">
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-xs text-slate-400">R$</span>
                                    <input type="number" step="0.01" min="0" value="0.00" name="item_price[]" required class="calc-price w-full text-sm text-right pl-7 rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </td>
                            <td class="py-3 px-3 w-32 text-right font-medium text-slate-800 row-subtotal">
                                R$ 0,00
                            </td>
                            <td class="py-3 px-2 w-12 text-center">
                                <button type="button" class="btn-remove-item text-slate-400 hover:text-rose-600 transition p-1">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Resumo Totalizador -->
            <div class="flex justify-end pt-3 border-t border-slate-100">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-right min-w-[260px]">
                    <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Valor Total Estimado</span>
                    <span id="grand_total_span" class="text-2xl font-black text-slate-900 block mt-0.5">R$ 0,00</span>
                    <span class="text-[11px] text-slate-400">Soma de todos os itens cadastrados</span>
                </div>
            </div>
        </div>

        <!-- Bloco 4: Justificativa & Necessidade -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">4</span>
                Justificativa da Solicitação
            </h2>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                    Por que esta compra ou serviço é necessária? <span class="text-rose-500">*</span>
                </label>
                <textarea name="justification" required rows="4" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Descreva os motivos, a finalidade, eventuais impactos na operação caso não seja atendido..."></textarea>
            </div>
        </div>

        <!-- Botões de Envio -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="<?= url('requisicoes') ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition">
                Cancelar
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md shadow-indigo-600/30 transition">
                <i class="fas fa-paper-plane mr-2"></i> Criar e Submeter Requisição
            </button>
        </div>
    </form>
</div>

<script>
    window.BASE_URL = '<?= BASE_URL ?>';
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
