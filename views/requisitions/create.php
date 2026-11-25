<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';

$companyName = $companySettings['company_name'] ?? 'Nossa Empresa';
$companyCnpj = $companySettings['company_cnpj'] ?? '';
$companyLogo = $companySettings['company_logo'] ?? '';
$logoPhysical = !empty($companyLogo) ? (__DIR__ . '/../../public/' . ltrim($companyLogo, '/')) : '';
?>

<div class="max-w-5xl mx-auto space-y-6">
    <!-- Cabeçalho -->
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= url('requisicoes') ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition flex items-center gap-1 mb-1">
                <i class="fas fa-arrow-left"></i> Voltar para a lista
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Nova Requisição</h1>
            <p class="text-xs sm:text-sm text-slate-500">Cadastre uma solicitação para compra de materiais ou prestação de serviços com fotos e assinatura digital.</p>
        </div>
    </div>

    <!-- Banner dos Dados da Empresa Solicitante e Usuário Logado -->
    <div class="bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100 rounded-2xl p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div class="flex items-center gap-3">
            <?php if (!empty($companyLogo) && file_exists($logoPhysical)): ?>
                <div class="h-11 w-16 bg-white rounded-xl border border-indigo-100 flex items-center justify-center p-1 shadow-sm shrink-0">
                    <img src="<?= asset($companyLogo) ?>" alt="Logo" class="max-h-full max-w-full object-contain">
                </div>
            <?php else: ?>
                <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-md shadow-indigo-600/20 shrink-0">
                    <i class="fas fa-building"></i>
                </div>
            <?php endif; ?>
            <div>
                <span class="text-[11px] font-semibold text-indigo-700 uppercase tracking-wider block">Empresa Solicitante (Institucional):</span>
                <span class="font-extrabold text-slate-900 text-sm"><?= htmlspecialchars($companyName) ?></span>
                <?php if (!empty($companyCnpj)): ?>
                    <span class="text-xs text-slate-500 font-mono ml-1.5">(CNPJ: <?= \App\Helpers\Security::formatDocument($companyCnpj) ?>)</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-left sm:text-right text-xs text-slate-600 border-t sm:border-t-0 pt-2 sm:pt-0 border-indigo-100 w-full sm:w-auto">
            <span class="text-slate-400 block text-[10px] uppercase">Requisitante Responsável:</span>
            <strong><?= htmlspecialchars($currentUser['name']) ?></strong> &bull; <?= htmlspecialchars($currentUser['email']) ?>
        </div>
    </div>

    <form method="POST" action="<?= url('requisicoes/salvar') ?>" enctype="multipart/form-data" id="reqForm" class="space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="requester_signature" id="requester_signature_input" value="">

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

            <!-- Opção exclusiva de Prestação de Serviços: Reparo/Manutenção de Equipamento ou Móvel -->
            <div id="serviceRepairSection" class="<?= $reqType === 'servico' ? '' : 'hidden' ?> rounded-2xl border border-amber-200 bg-amber-50/60 p-4 space-y-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="is_equipment_repair" id="is_equipment_repair" value="1"
                           <?= !empty($preselectedRepair) ? 'checked' : '' ?>
                           class="mt-0.5 rounded border-amber-400 text-indigo-600 focus:ring-indigo-500">
                    <div>
                        <span class="block text-sm font-bold text-slate-900">
                            <i class="fas fa-wrench text-amber-600 mr-1"></i> É uma solicitação de reparo de equipamento ou móvel?
                        </span>
                        <span class="block text-xs text-slate-600 mt-0.5">
                            Ao marcar esta opção, é liberada abaixo a seleção para vincular um produto/equipamento previamente cadastrado nesta requisição, permitindo registrar o histórico de reparo ou manutenção preventiva ao concluir a ordem de serviço.
                        </span>
                    </div>
                </label>

                <div id="repairProductPicker" class="<?= !empty($preselectedRepair) ? '' : 'hidden' ?> grid grid-cols-1 md:grid-cols-3 gap-4 pt-3 border-t border-amber-200/80">
                    <div class="md:col-span-2">
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                                Produto / Equipamento Vinculado ao Serviço
                            </label>
                            <a href="<?= url('produtos') ?>" target="_blank" class="text-[11px] font-bold text-indigo-600 hover:underline">
                                <i class="fas fa-external-link-alt mr-0.5"></i> Gerenciar Produtos
                            </a>
                        </div>
                        <select name="linked_product_id" id="linked_product_id" class="w-full text-sm rounded-xl border-amber-300 bg-white focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            <option value="">-- Selecione um produto cadastrado (opcional) --</option>
                            <?php foreach ($products ?? [] as $prod): ?>
                                <option value="<?= (int)$prod['id'] ?>" <?= ((int)($preselectedProductId ?? 0) === (int)$prod['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prod['name']) ?><?= !empty($prod['license_plate']) ? ' • Placa: ' . htmlspecialchars($prod['license_plate']) : '' ?><?= !empty($prod['serial_number']) ? ' • N/S: ' . htmlspecialchars($prod['serial_number']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Tipo de Manutenção
                        </label>
                        <select name="maintenance_type" id="maintenance_type" class="w-full text-sm rounded-xl border-amber-300 bg-white focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            <option value="reparo" <?= ($preselectedMaintenanceType ?? 'reparo') === 'reparo' ? 'selected' : '' ?>>Reparo / Manutenção Corretiva</option>
                            <option value="preventiva" <?= ($preselectedMaintenanceType ?? '') === 'preventiva' ? 'selected' : '' ?>>Manutenção Preventiva</option>
                        </select>
                    </div>
                </div>
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
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                            Centro de Custo <span class="text-rose-500">*</span>
                        </label>
                        <button type="button" onclick="openQuickCostCenterModal()" class="text-[11px] font-bold text-indigo-600 hover:underline">
                            + Novo Centro de Custo
                        </button>
                    </div>
                    <select name="department" id="costCenterSelect" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                        <option value="">-- Selecione o Centro de Custo --</option>
                        <?php foreach ($costCenters ?? [] as $cc): ?>
                            <?php $ccLabel = $cc['code'] . ' - ' . $cc['name']; ?>
                            <option value="<?= htmlspecialchars($ccLabel) ?>"><?= htmlspecialchars($ccLabel) ?></option>
                        <?php endforeach; ?>
                        <?php if (!empty($currentUser['department']) && empty($costCenters)): ?>
                            <option value="<?= htmlspecialchars($currentUser['department']) ?>" selected><?= htmlspecialchars($currentUser['department']) ?></option>
                        <?php endif; ?>
                    </select>
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
                    <p class="text-xs text-slate-400">
                        Adicione os itens. <em>Dica:</em> O preenchimento do valor unitário é <strong>opcional</strong>. Se você não preencher valores, os campos de preço e total não aparecerão na requisição.
                    </p>
                </div>
                <button type="button" id="btn_add_item" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold transition">
                    <i class="fas fa-plus"></i> Adicionar Linha
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                            <th class="py-2.5 px-3">Descrição / Especificações</th>
                            <th class="py-2.5 px-3 w-28">Unidade</th>
                            <th class="py-2.5 px-3 w-28 text-right">Quantidade</th>
                            <th class="py-2.5 px-3 w-36 text-right">Valor Unit. (Opcional)</th>
                            <th class="py-2.5 px-3 w-32 text-right">Subtotal</th>
                            <th class="py-2.5 px-2 w-12 text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="items_table_body">
                        <!-- Linha 1 padrão -->
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                            <td class="py-3 px-3">
                                <select class="item-catalog-select hidden w-full text-xs rounded-lg border-indigo-200 bg-indigo-50/40 text-slate-700 mb-1.5 focus:border-indigo-500 focus:ring-indigo-500"></select>
                                <input type="text" name="item_description[]" required class="item-desc-input w-full text-sm rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Descrição do material ou escopo do serviço">
                                <input type="text" name="item_specs[]" class="item-specs-input w-full text-xs text-slate-500 rounded border-slate-200 mt-1 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Especificações técnicas, marca de referência, etc (opcional)">
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
                                    <input type="text" name="item_price[]" placeholder="0,00" class="calc-price w-full text-sm text-right pl-7 rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </td>
                            <td class="py-3 px-3 w-32 text-right font-medium text-slate-800 row-subtotal">
                                -
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
                    <span id="grand_total_span" class="text-2xl font-black text-slate-900 block mt-0.5">Sem valor</span>
                    <span class="text-[11px] text-slate-400">Se não preenchido, será emitido como cotação/avulso</span>
                </div>
            </div>
        </div>

        <!-- Bloco 4: Justificativa -->
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

        <!-- Bloco 5: Anexo de Fotos & Documentos -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">5</span>
                Anexar Fotos & Documentos (Opcional)
            </h2>
            <p class="text-xs text-slate-400">Envie fotos dos itens a serem comprados, fotos do local da reforma, propostas, orçamentos ou laudos técnicos.</p>

            <div class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center hover:border-indigo-400 transition bg-slate-50/50">
                <i class="fas fa-cloud-upload-alt text-3xl text-indigo-500 mb-2"></i>
                <div class="text-sm font-semibold text-slate-700 mb-1">Selecione ou arraste fotos e arquivos aqui</div>
                <p class="text-xs text-slate-400 mb-3">Imagens (PNG, JPG, JPEG, WEBP), PDF ou documentos do Office.</p>
                <input type="file" name="attachments[]" id="fileAttachments" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" class="block mx-auto text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer">
                
                <!-- Pré-visualização de fotos -->
                <div id="photosPreviewGrid" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3 mt-4 hidden"></div>
            </div>
        </div>

        <!-- Bloco 6: Assinatura Digital do Solicitante -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">6</span>
                    Assinatura Digital do Solicitante
                </h2>
            </div>
            <p class="text-xs text-slate-400">Escolha a modalidade de assinatura desejada para autenticar formalmente sua requisição.</p>

            <!-- Seletor de Tipo de Assinatura -->
            <div class="flex rounded-xl bg-slate-100 p-1 text-xs font-semibold gap-1 max-w-lg">
                <button type="button" onclick="setRequesterSigMode('canvas')" id="btnReqModeCanvas" class="flex-1 py-2 rounded-lg bg-white text-indigo-700 shadow-sm transition">
                    <i class="fas fa-pen-nib mr-1"></i> Desenho na Tela
                </button>
                <button type="button" onclick="setRequesterSigMode('cert')" id="btnReqModeCert" class="flex-1 py-2 rounded-lg text-slate-600 hover:text-slate-900 transition">
                    <i class="fas fa-certificate mr-1 text-emerald-600"></i> Certificado Digital (Windows / Linux A1 & A3)
                </button>
            </div>

            <!-- Modo 1: Canvas Manual -->
            <div id="requesterCanvasContainer" class="space-y-2">
                <div class="border-2 border-slate-300 rounded-2xl p-2 bg-slate-50 inline-block w-full max-w-lg">
                    <div class="flex justify-between items-center text-[11px] text-slate-400 mb-1">
                        <span>Desenhe sua assinatura no quadro:</span>
                        <button type="button" onclick="clearRequesterSignature()" class="text-indigo-600 hover:underline">Limpar</button>
                    </div>
                    <canvas id="signatureCanvas" width="500" height="130" class="w-full h-32 bg-white rounded-xl border border-slate-200 cursor-crosshair touch-none"></canvas>
                </div>
            </div>

            <!-- Modo 2: Integração Nativa com Certificados Instalados no Navegador (Windows / Linux) -->
            <div id="requesterCertContainer" class="hidden border border-emerald-200 rounded-2xl p-4 bg-emerald-50/50 space-y-3 max-w-lg">
                <div class="text-xs text-emerald-950 font-bold flex items-center gap-1.5">
                    <i class="fas fa-shield-alt text-emerald-600"></i> Integração Direta com Certificados do Sistema (A1 ou Token A3)
                </div>
                <p class="text-[11px] text-slate-600 leading-relaxed">
                    O navegador abrirá a lista oficial de certificados digitais instalados no seu <strong>Windows</strong> ou <strong>Linux</strong> (incluindo Tokens/Smartcards A3 com solicitação nativa de PIN). Não é necessário enviar arquivos <code>.pfx</code>.
                </p>
                <button type="button" onclick="openBrowserCertSigner('requester')" id="btnValidateReqCert" class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                    <i class="fas fa-id-card"></i> Selecionar Certificado Instalado no Sistema (A1 / A3)
                </button>
            </div>

            <!-- Feedback e Prévia do Selo Digital Gerado -->
            <div id="requesterSealFeedback" class="hidden p-3 rounded-xl border border-emerald-300 bg-emerald-50 text-xs text-emerald-900 space-y-2 max-w-lg">
                <div class="font-bold flex items-center gap-1.5">
                    <i class="fas fa-check-circle text-emerald-600"></i> <span id="requesterSealTitle">Certificado Digital Autenticado com Sucesso!</span>
                </div>
                <div id="requesterSealPreview" class="bg-white p-2 rounded-lg border border-emerald-200 flex justify-center"></div>
            </div>

            <div class="text-[11px] text-slate-400">
                Responsável: <strong><?= htmlspecialchars($currentUser['name']) ?></strong> (<?= htmlspecialchars($currentUser['email']) ?>)
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

    // --------------------------------------------------------
    // CANVAS DE ASSINATURA DIGITAL
    // --------------------------------------------------------
    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let hasSigned = false;

    ctx.strokeStyle = '#0f172a';
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    function getCanvasCoordinates(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;

        let clientX = e.clientX;
        let clientY = e.clientY;

        if (e.touches && e.touches.length > 0) {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        }

        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
        };
    }

    function startDraw(e) {
        isDrawing = true;
        const pos = getCanvasCoordinates(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        e.preventDefault();
    }

    function draw(e) {
        if (!isDrawing) return;
        const pos = getCanvasCoordinates(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        hasSigned = true;
        e.preventDefault();
    }

    function stopDraw() {
        if (isDrawing) {
            ctx.closePath();
            isDrawing = false;
        }
    }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    window.addEventListener('mouseup', stopDraw);

    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    window.addEventListener('touchend', stopDraw);

    let currentRequesterMode = 'canvas';

    function setRequesterSigMode(mode) {
        currentRequesterMode = mode;
        const btnCanvas = document.getElementById('btnReqModeCanvas');
        const btnCert = document.getElementById('btnReqModeCert');
        const cCanvas = document.getElementById('requesterCanvasContainer');
        const cCert = document.getElementById('requesterCertContainer');

        btnCanvas.className = 'flex-1 py-2 rounded-lg text-slate-600 hover:text-slate-900 transition';
        btnCert.className = 'flex-1 py-2 rounded-lg text-slate-600 hover:text-slate-900 transition';

        cCanvas.classList.add('hidden');
        cCert.classList.add('hidden');

        if (mode === 'canvas') {
            btnCanvas.className = 'flex-1 py-2 rounded-lg bg-white text-indigo-700 shadow-sm transition';
            cCanvas.classList.remove('hidden');
        } else {
            btnCert.className = 'flex-1 py-2 rounded-lg bg-white text-emerald-700 shadow-sm transition';
            cCert.classList.remove('hidden');
        }
    }

    let pendingCertNonce = '';
    let certPollTimer = null;

    function applyRequesterCertResult(data) {
        if (!data || !data.success) return;
        if (certPollTimer) {
            clearInterval(certPollTimer);
            certPollTimer = null;
        }
        document.getElementById('requester_signature_input').value = data.seal_image;
        hasSigned = true;
        document.getElementById('requesterSealTitle').textContent = `Certificado Autenticado: ${data.signer_name}`;
        const preview = document.getElementById('requesterSealPreview');
        preview.innerHTML = '';
        const img = document.createElement('img');
        img.src = data.seal_image;
        img.alt = 'Selo Digital';
        img.className = 'max-h-24';
        preview.appendChild(img);
        document.getElementById('requesterSealFeedback').classList.remove('hidden');
    }

    function openBrowserCertSigner(ctxRole) {
        pendingCertNonce = 'rm_' + Date.now() + '_' + Math.random().toString(36).substring(2, 10);
        const httpsBase = <?= json_encode(cert_auth_url($companySettings ?? [])) ?>;
        const targetUrl = `${httpsBase}?ctx=${encodeURIComponent(ctxRole)}&nonce=${encodeURIComponent(pendingCertNonce)}&doc_title=${encodeURIComponent('Nova Requisição - Solicitante')}`;
        const popup = window.open(targetUrl, 'rmCertPopup', 'width=520,height=560,menubar=no,toolbar=no,location=yes,status=no');
        if (!popup) {
            alert('Por favor, permita a abertura da janela pop-up para selecionar o certificado instalado no seu navegador.');
            return;
        }
        if (certPollTimer) clearInterval(certPollTimer);
        certPollTimer = setInterval(() => {
            if (!pendingCertNonce) return;
            fetch(`<?= url('requisicoes/assinar-certificado') ?>?nonce=${encodeURIComponent(pendingCertNonce)}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.success) {
                        applyRequesterCertResult(data);
                    } else if (popup.closed) {
                        clearInterval(certPollTimer);
                        certPollTimer = null;
                    }
                })
                .catch(() => {
                    if (popup.closed) {
                        clearInterval(certPollTimer);
                        certPollTimer = null;
                    }
                });
        }, 1200);
    }

    window.addEventListener('message', function(event) {
        if (!event.data || event.data.type !== 'RM_CERT_SIGNED') return;
        if (event.data.ctx !== 'requester') return;
        applyRequesterCertResult(event.data.result);
    });

    function clearRequesterSignature() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasSigned = false;
        document.getElementById('requester_signature_input').value = '';
        document.getElementById('requesterSealFeedback').classList.add('hidden');
    }

    document.getElementById('reqForm').addEventListener('submit', function() {
        if (currentRequesterMode === 'canvas' && hasSigned) {
            document.getElementById('requester_signature_input').value = canvas.toDataURL('image/png');
        }
    });

    // --------------------------------------------------------
    // PRÉ-VISUALIZAÇÃO DE FOTOS ANEXADAS
    // --------------------------------------------------------
    const fileInput = document.getElementById('fileAttachments');
    const previewGrid = document.getElementById('photosPreviewGrid');

    fileInput.addEventListener('change', function() {
        previewGrid.innerHTML = '';
        if (this.files && this.files.length > 0) {
            previewGrid.classList.remove('hidden');
            Array.from(this.files).forEach(file => {
                const col = document.createElement('div');
                col.className = 'relative rounded-xl overflow-hidden border border-slate-200 bg-white p-1 text-center';

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        col.innerHTML = `
                            <img src="${e.target.result}" class="w-full h-20 object-cover rounded-lg">
                            <span class="text-[10px] text-slate-500 block truncate mt-1">${file.name}</span>
                        `;
                    };
                    reader.readAsDataURL(file);
                } else {
                    col.innerHTML = `
                        <div class="h-20 flex items-center justify-center bg-slate-50 text-slate-400">
                            <i class="fas fa-file-pdf text-2xl text-rose-500"></i>
                        </div>
                        <span class="text-[10px] text-slate-500 block truncate mt-1">${file.name}</span>
                    `;
                }
                previewGrid.appendChild(col);
            });
        } else {
            previewGrid.classList.add('hidden');
        }
    });

    function openQuickCostCenterModal() {
        document.getElementById('quickCcModal').classList.remove('hidden');
    }

    function closeQuickCostCenterModal() {
        document.getElementById('quickCcModal').classList.add('hidden');
    }

    function saveQuickCostCenter() {
        const code = document.getElementById('qCcCode').value.trim();
        const name = document.getElementById('qCcName').value.trim();
        const desc = document.getElementById('qCcDesc').value.trim();
        const btn = document.getElementById('btnSaveQuickCc');

        if (!code || !name) {
            alert('Informe o Código e o Nome do Centro de Custo.');
            return;
        }

        const fd = new FormData();
        fd.append('csrf_token', '<?= csrf_token() ?>');
        fd.append('ajax', '1');
        fd.append('code', code);
        fd.append('name', name);
        fd.append('description', desc);

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

        fetch('<?= url('centros-de-custo/salvar') ?>', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = 'Cadastrar e Selecionar';
            if (data.success) {
                const sel = document.getElementById('costCenterSelect');
                const opt = document.createElement('option');
                opt.value = data.label;
                opt.textContent = data.label;
                opt.selected = true;
                sel.appendChild(opt);
                document.getElementById('qCcCode').value = '';
                document.getElementById('qCcName').value = '';
                document.getElementById('qCcDesc').value = '';
                closeQuickCostCenterModal();
            } else {
                alert(data.message || 'Erro ao cadastrar centro de custo.');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = 'Cadastrar e Selecionar';
            alert('Erro de comunicação ao cadastrar centro de custo.');
        });
    }

    // --------------------------------------------------------
    // CATÁLOGO DE PRODUTOS / SERVIÇOS E VÍNCULO DE REPARO
    // --------------------------------------------------------
    window.REQ_CATALOG = {
        products: <?= json_encode(array_map(fn($p) => [
            'id' => (int)$p['id'],
            'name' => $p['name'],
            'serial' => $p['serial_number'] ?? '',
            'notes' => $p['notes'] ?? ''
        ], $products ?? []), JSON_UNESCAPED_UNICODE) ?>,
        services: <?= json_encode(array_map(fn($s) => [
            'id' => (int)$s['id'],
            'name' => $s['name'],
            'notes' => $s['notes'] ?? ''
        ], $services ?? []), JSON_UNESCAPED_UNICODE) ?>
    };

    function getCurrentReqType() {
        const checked = document.querySelector('input[name="type"]:checked');
        return checked ? checked.value : 'material';
    }

    window.populateCatalogSelect = function(selectEl) {
        if (!selectEl) return;
        const reqType = getCurrentReqType();
        const items = reqType === 'material' ? window.REQ_CATALOG.products : window.REQ_CATALOG.services;
        const placeholder = reqType === 'material'
            ? '-- Selecionar Produto Cadastrado (ou digite manualmente abaixo) --'
            : '-- Selecionar Serviço Cadastrado (ou digite manualmente abaixo) --';

        selectEl.innerHTML = '';
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = placeholder;
        selectEl.appendChild(defaultOpt);

        if (!items || items.length === 0) {
            selectEl.classList.add('hidden');
            return;
        }

        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.dataset.name = item.name;
            opt.dataset.serial = item.serial || '';
            opt.dataset.notes = item.notes || '';
            opt.textContent = item.serial ? `${item.name} (N/S: ${item.serial})` : item.name;
            selectEl.appendChild(opt);
        });
        selectEl.classList.remove('hidden');
    };

    function refreshAllCatalogSelects() {
        document.querySelectorAll('#items_table_body .item-catalog-select').forEach(sel => {
            window.populateCatalogSelect(sel);
        });
    }

    function syncServiceRepairVisibility() {
        const reqType = getCurrentReqType();
        const repairSection = document.getElementById('serviceRepairSection');
        const repairChk = document.getElementById('is_equipment_repair');
        const picker = document.getElementById('repairProductPicker');

        if (reqType === 'servico') {
            repairSection?.classList.remove('hidden');
            if (repairChk && repairChk.checked) {
                picker?.classList.remove('hidden');
            } else {
                picker?.classList.add('hidden');
            }
        } else {
            repairSection?.classList.add('hidden');
            picker?.classList.add('hidden');
        }
    }

    document.querySelectorAll('input[name="type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            refreshAllCatalogSelects();
            syncServiceRepairVisibility();
        });
    });

    document.getElementById('is_equipment_repair')?.addEventListener('change', syncServiceRepairVisibility);

    document.getElementById('items_table_body')?.addEventListener('change', function(e) {
        if (!e.target.classList.contains('item-catalog-select')) return;
        const opt = e.target.options[e.target.selectedIndex];
        if (!opt || !opt.value) return;

        const row = e.target.closest('tr');
        const descInput = row.querySelector('.item-desc-input, input[name="item_description[]"]');
        const specsInput = row.querySelector('.item-specs-input, input[name="item_specs[]"]');
        if (descInput) {
            descInput.value = opt.dataset.name || opt.textContent;
        }
        if (specsInput && !specsInput.value.trim()) {
            const extra = [];
            if (opt.dataset.serial) extra.push('N/S: ' + opt.dataset.serial);
            if (opt.dataset.notes) extra.push(opt.dataset.notes);
            if (extra.length > 0) specsInput.value = extra.join(' | ');
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        refreshAllCatalogSelects();
        syncServiceRepairVisibility();
    });
</script>

<!-- Modal Rápido de Novo Centro de Custo -->
<div id="quickCcModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 border border-slate-100 space-y-4">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="font-bold text-base text-slate-900">Cadastrar Novo Centro de Custo</h3>
            <button type="button" onclick="closeQuickCostCenterModal()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Código <span class="text-rose-500">*</span></label>
                <input type="text" id="qCcCode" maxlength="30" placeholder="Ex: CC-010" class="w-full text-sm rounded-xl font-mono uppercase">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome <span class="text-rose-500">*</span></label>
                <input type="text" id="qCcName" maxlength="120" placeholder="Ex: Manutenção Industrial" class="w-full text-sm rounded-xl">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Descrição</label>
                <input type="text" id="qCcDesc" maxlength="255" placeholder="Opcional" class="w-full text-sm rounded-xl">
            </div>
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button type="button" onclick="closeQuickCostCenterModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Cancelar</button>
            <button type="button" onclick="saveQuickCostCenter()" id="btnSaveQuickCc" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md">Cadastrar e Selecionar</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
