<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fas fa-boxes-stacked text-indigo-600"></i> Cadastro de Produtos & Equipamentos
            </h1>
            <p class="text-xs sm:text-sm text-slate-500">Cadastre produtos, móveis e equipamentos com número de série, fotos, observações e prazo de manutenção preventiva.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= url('veiculos/quilometragem') ?>" class="px-3.5 py-2 rounded-xl border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold shadow-sm transition">
                <i class="fas fa-gauge-high mr-1"></i> Registrar Quilometragem
            </a>
            <a href="<?= url('servicos') ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-sm transition">
                <i class="fas fa-wrench mr-1"></i> Catálogo de Serviços
            </a>
            <button type="button" onclick="openProductModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs sm:text-sm font-semibold shadow-sm transition flex items-center gap-2">
                <i class="fas fa-plus"></i> Cadastrar Produto
            </button>
        </div>
    </div>

    <!-- Busca -->
    <form method="GET" action="<?= url('produtos') ?>" class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Buscar por nome do produto, número de série, placa, chassi ou observações..." class="flex-1 text-sm rounded-xl py-2 px-3">
        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition">
            <i class="fas fa-search mr-1"></i> Buscar
        </button>
        <?php if (!empty($search)): ?>
            <a href="<?= url('produtos') ?>" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold text-center">Limpar</a>
        <?php endif; ?>
    </form>

    <!-- Tabela de Produtos -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4">Produto / Equipamento / Veículo</th>
                        <th class="py-3.5 px-4">Identificação (Série / Placa)</th>
                        <th class="py-3.5 px-4 text-center">Preventiva (Dias / Km)</th>
                        <th class="py-3.5 px-4 text-center">Última Manutenção / Km</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-slate-400">
                                <i class="fas fa-box-open text-3xl mb-2 block text-slate-300"></i>
                                Nenhum produto cadastrado ainda.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr class="hover:bg-slate-50/70 transition <?= !empty($p['is_maintenance_overdue']) ? 'bg-rose-50/40' : '' ?>">
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($p['thumb_photo']) && file_exists(__DIR__ . '/../../public/' . $p['thumb_photo'])): ?>
                                            <img src="<?= asset($p['thumb_photo']) ?>" alt="Foto" class="w-11 h-11 rounded-xl object-cover border border-slate-200 shrink-0">
                                        <?php else: ?>
                                            <div class="w-11 h-11 rounded-xl <?= !empty($p['is_vehicle']) ? 'bg-indigo-50 text-indigo-600 border-indigo-200' : 'bg-slate-100 text-slate-400 border-slate-200' ?> flex items-center justify-center border shrink-0">
                                                <i class="fas <?= !empty($p['is_vehicle']) ? 'fa-car' : 'fa-box' ?>"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <a href="<?= url('produtos/ver?id=' . $p['id']) ?>" class="font-bold text-slate-900 hover:text-indigo-600">
                                                    <?= htmlspecialchars($p['name']) ?>
                                                </a>
                                                <?php if (!empty($p['is_vehicle'])): ?>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700">Veículo</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($p['notes'])): ?>
                                                <span class="text-[11px] text-slate-500 block line-clamp-1"><?= htmlspecialchars($p['notes']) ?></span>
                                            <?php endif; ?>
                                            <div class="flex flex-wrap items-center gap-2 mt-0.5">
                                                <?php if ((int)$p['photo_count'] > 0): ?>
                                                    <span class="text-[10px] text-indigo-600 font-semibold"><i class="fas fa-camera mr-1"></i><?= (int)$p['photo_count'] ?> foto(s)</span>
                                                <?php endif; ?>
                                                <?php if (!empty($p['is_vehicle']) && (int)($p['subitem_count'] ?? 0) > 0): ?>
                                                    <span class="text-[10px] text-emerald-700 font-semibold"><i class="fas fa-list-check mr-1"></i><?= (int)$p['subitem_count'] ?> subproduto(s)</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-xs text-slate-700 space-y-0.5">
                                    <?php if (!empty($p['is_vehicle'])): ?>
                                        <?php if (!empty($p['license_plate'])): ?>
                                            <div><span class="text-slate-400">Placa:</span> <strong><?= htmlspecialchars($p['license_plate']) ?></strong></div>
                                        <?php endif; ?>
                                        <?php if (!empty($p['chassis'])): ?>
                                            <div class="text-[11px]"><span class="text-slate-400">Chassi:</span> <?= htmlspecialchars($p['chassis']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($p['current_km'] !== null): ?>
                                            <div class="text-[11px] text-indigo-700 font-bold"><i class="fas fa-gauge-high mr-1"></i><?= number_format((int)$p['current_km'], 0, ',', '.') ?> km</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($p['serial_number'])): ?>
                                        <div>S/N: <?= htmlspecialchars($p['serial_number']) ?></div>
                                    <?php elseif (empty($p['is_vehicle']) || (empty($p['license_plate']) && empty($p['chassis']) && $p['current_km'] === null)): ?>
                                        <span class="text-slate-300">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center text-xs space-y-1">
                                    <?php if (!empty($p['maintenance_interval_days'])): ?>
                                        <span class="inline-block px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 font-bold border border-indigo-200">
                                            A cada <?= (int)$p['maintenance_interval_days'] ?> dias
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($p['is_vehicle']) && !empty($p['maintenance_interval_km'])): ?>
                                        <span class="inline-block px-2.5 py-0.5 rounded-full bg-sky-50 text-sky-700 font-bold border border-sky-200">
                                            A cada <?= number_format((int)$p['maintenance_interval_km'], 0, ',', '.') ?> km
                                        </span>
                                    <?php endif; ?>
                                    <?php if (empty($p['maintenance_interval_days']) && empty($p['maintenance_interval_km'])): ?>
                                        <span class="text-slate-400">Não definido</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center text-xs space-y-1">
                                    <?php if (!empty($p['last_maintenance_at'])): ?>
                                        <span class="font-semibold text-slate-800 block"><?= formatDate($p['last_maintenance_at']) ?></span>
                                        <?php if (!empty($p['is_days_overdue'])): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 bg-rose-100 px-2 py-0.5 rounded-full">
                                                <i class="fas fa-exclamation-triangle"></i> Prazo dias vencido (<?= (int)$p['days_since_maintenance'] ?>d)
                                            </span>
                                        <?php elseif ($p['days_since_maintenance'] !== null): ?>
                                            <span class="text-[10px] text-emerald-700 block">Dias em dia (<?= (int)$p['days_since_maintenance'] ?>d)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic block">Sem data registrada</span>
                                    <?php endif; ?>

                                    <?php if (!empty($p['is_vehicle']) && $p['km_since_maintenance'] !== null): ?>
                                        <?php if (!empty($p['is_km_overdue'])): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 bg-rose-100 px-2 py-0.5 rounded-full">
                                                <i class="fas fa-gauge-high"></i> Km atingido (<?= number_format((int)$p['km_since_maintenance'], 0, ',', '.') ?> km rodados)
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] text-sky-700 block">Rodados: <?= number_format((int)$p['km_since_maintenance'], 0, ',', '.') ?> km</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $p['active'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                                        <?= $p['active'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="<?= url('produtos/ver?id=' . $p['id']) ?>" class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition" title="Ver Ficha e Histórico">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" onclick="editProduct(<?= htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)" class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition" title="Editar">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <a href="<?= url('requisicoes/nova?type=servico&repair=1&product_id=' . $p['id']) ?>" class="p-1.5 rounded-lg text-amber-500 hover:text-amber-700 hover:bg-amber-50 transition" title="Solicitar Reparo / Manutenção">
                                            <i class="fas fa-tools"></i>
                                        </a>
                                        <form method="POST" action="<?= url('produtos/status') ?>" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-amber-600 transition" title="<?= $p['active'] ? 'Inativar' : 'Ativar' ?>">
                                                <i class="fas <?= $p['active'] ? 'fa-ban' : 'fa-check-circle' ?>"></i>
                                            </button>
                                        </form>
                                        <?php if ($isAdmin): ?>
                                            <form method="POST" action="<?= url('produtos/excluir') ?>" class="inline" onsubmit="return confirm('Confirma excluir este produto?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 transition" title="Excluir">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Cadastro / Edição de Produto -->
<div id="productModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-6 border border-slate-100 space-y-4 max-h-[92vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 id="prodModalTitle" class="font-bold text-lg text-slate-900">Cadastrar Produto / Equipamento / Veículo</h3>
            <button type="button" onclick="closeProductModal()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" action="<?= url('produtos/salvar') ?>" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="prodId" value="0">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome do Produto / Equipamento / Veículo <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="prodName" required class="w-full text-sm rounded-xl" placeholder="Ex: Fiat Strada 1.4 ou Ar-Condicionado Split">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Número de Série (Opcional)</label>
                    <input type="text" name="serial_number" id="prodSerial" class="w-full text-sm rounded-xl font-mono" placeholder="Ex: SN-2026-9910">
                </div>
            </div>

            <!-- Flag: É um Veículo? -->
            <div class="p-3.5 rounded-2xl bg-indigo-50/70 border border-indigo-200">
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="is_vehicle" id="prodIsVehicle" value="1" onchange="toggleVehicleFields()" class="rounded text-indigo-600 focus:ring-indigo-500">
                    <div>
                        <span class="text-sm font-bold text-slate-900 block"><i class="fas fa-car text-indigo-600 mr-1"></i> Este produto é um Veículo?</span>
                        <span class="text-[11px] text-slate-600">Marque para cadastrar placa, chassi, quilometragem, manutenção por Km e subprodutos vinculados (óleo, filtros, gasolina, etc.).</span>
                    </div>
                </label>
            </div>

            <!-- Campos específicos de Veículo -->
            <div id="vehicleFieldsBox" class="hidden space-y-4 p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Placa (Opcional)</label>
                        <input type="text" name="license_plate" id="prodPlate" maxlength="20" class="w-full text-sm rounded-xl font-mono uppercase" placeholder="Ex: ABC-1D23">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Chassi (Opcional)</label>
                        <input type="text" name="chassis" id="prodChassis" maxlength="80" class="w-full text-sm rounded-xl font-mono uppercase" placeholder="Ex: 9BWZZZ377VT004251">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Quilometragem Atual (Km)</label>
                        <input type="number" name="current_km" id="prodCurrentKm" min="0" class="w-full text-sm rounded-xl font-mono" placeholder="Ex: 45200">
                    </div>
                </div>

                <!-- Subprodutos vinculados ao veículo -->
                <div class="space-y-2 pt-2 border-t border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block text-xs font-bold text-slate-800 uppercase">Subprodutos Vinculados ao Veículo</span>
                            <span class="text-[11px] text-slate-500">Óleo, filtros, gasolina, aditivos, pneus, pastilhas, etc.</span>
                        </div>
                        <button type="button" onclick="addVehicleSubitemRow()" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-sm transition">
                            <i class="fas fa-plus"></i> Adicionar Item
                        </button>
                    </div>
                    <div id="vehicleSubitemsContainer" class="space-y-2"></div>
                </div>
            </div>

            <!-- Manutenção Preventiva (Dias e/ou Quilômetros) -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                <span class="block text-xs font-bold text-slate-800 uppercase">Prazo de Manutenção Preventiva</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Por Tempo (X Dias)</label>
                        <input type="number" name="maintenance_interval_days" id="prodInterval" min="1" class="w-full text-sm rounded-xl" placeholder="Ex: 90, 180, 365">
                    </div>
                    <div id="prodKmIntervalBox" class="hidden">
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Por Quilômetros Percorridos (Km)</label>
                        <input type="number" name="maintenance_interval_km" id="prodIntervalKm" min="1" oninput="toggleKmNotice()" class="w-full text-sm rounded-xl font-mono" placeholder="Ex: 5000, 10000">
                    </div>
                </div>

                <!-- Aviso obrigatório quando preencher manutenção por Km -->
                <div id="kmTrackingNotice" class="hidden p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-start gap-2">
                    <i class="fas fa-circle-info text-amber-600 mt-0.5 shrink-0"></i>
                    <span><strong>Atenção:</strong> Ao definir manutenção por quilômetros percorridos, você deve acompanhar e registrar a quilometragem do veículo de tempos em tempos na tela de <strong>Quilometragem</strong> para verificar se já alcançou o limite da manutenção.</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Data da Última Manutenção (Opcional)</label>
                        <input type="date" name="last_maintenance_at" id="prodLastMaint" class="w-full text-sm rounded-xl">
                    </div>
                    <div id="prodLastMaintKmBox" class="hidden">
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Km da Última Manutenção (Opcional)</label>
                        <input type="number" name="last_maintenance_km" id="prodLastMaintKm" min="0" class="w-full text-sm rounded-xl font-mono" placeholder="Se vazio, usa o Km atual">
                    </div>
                </div>
                <p class="text-[11px] text-slate-400">Atualizado automaticamente sempre que uma OS de manutenção ou reparo vinculada for concluída.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Fotos do Produto / Veículo (Opcional - Múltiplas)</label>
                <input type="file" name="photos[]" multiple accept="image/*" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 cursor-pointer">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Observações Gerais</label>
                <textarea name="notes" id="prodNotes" rows="3" class="w-full text-sm rounded-xl" placeholder="Localização, modelo, especificações, recomendações..."></textarea>
            </div>

            <div id="prodActiveRow" class="hidden">
                <label class="flex items-center text-xs font-semibold text-slate-700 cursor-pointer">
                    <input type="checkbox" name="active" id="prodActive" value="1" checked class="rounded text-indigo-600 mr-2">
                    Produto Ativo
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeProductModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Cancelar</button>
                <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md">Salvar Produto</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleVehicleFields() {
        const isVeh = document.getElementById('prodIsVehicle').checked;
        document.getElementById('vehicleFieldsBox').classList.toggle('hidden', !isVeh);
        document.getElementById('prodKmIntervalBox').classList.toggle('hidden', !isVeh);
        document.getElementById('prodLastMaintKmBox').classList.toggle('hidden', !isVeh);
        toggleKmNotice();
    }

    function toggleKmNotice() {
        const isVeh = document.getElementById('prodIsVehicle').checked;
        const kmVal = parseInt(document.getElementById('prodIntervalKm').value || '0', 10);
        document.getElementById('kmTrackingNotice').classList.toggle('hidden', !(isVeh && kmVal > 0));
    }

    function addVehicleSubitemRow(name = '', notes = '') {
        const container = document.getElementById('vehicleSubitemsContainer');
        const row = document.createElement('div');
        row.className = 'grid grid-cols-1 sm:grid-cols-12 gap-2 items-center bg-white p-2 rounded-xl border border-slate-200';
        row.innerHTML = `
            <div class="sm:col-span-5">
                <input type="text" name="subitem_name[]" placeholder="Ex: Óleo 5W30, Filtro de ar, Gasolina" class="w-full text-xs rounded-lg">
            </div>
            <div class="sm:col-span-6">
                <input type="text" name="subitem_notes[]" placeholder="Especificação / Qtd / Observação (opcional)" class="w-full text-xs rounded-lg">
            </div>
            <div class="sm:col-span-1 text-right">
                <button type="button" onclick="this.closest('.grid').remove()" class="p-1.5 text-slate-400 hover:text-rose-600 transition" title="Remover">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;
        row.querySelector('input[name="subitem_name[]"]').value = name;
        row.querySelector('input[name="subitem_notes[]"]').value = notes;
        container.appendChild(row);
    }

    function openProductModal() {
        document.getElementById('prodModalTitle').textContent = 'Cadastrar Produto / Equipamento / Veículo';
        document.getElementById('prodId').value = '0';
        document.getElementById('prodName').value = '';
        document.getElementById('prodSerial').value = '';
        document.getElementById('prodIsVehicle').checked = false;
        document.getElementById('prodPlate').value = '';
        document.getElementById('prodChassis').value = '';
        document.getElementById('prodCurrentKm').value = '';
        document.getElementById('prodInterval').value = '';
        document.getElementById('prodIntervalKm').value = '';
        document.getElementById('prodLastMaint').value = '';
        document.getElementById('prodLastMaintKm').value = '';
        document.getElementById('prodNotes').value = '';
        document.getElementById('vehicleSubitemsContainer').innerHTML = '';
        document.getElementById('prodActive').checked = true;
        document.getElementById('prodActiveRow').classList.add('hidden');
        toggleVehicleFields();
        document.getElementById('productModal').classList.remove('hidden');
    }

    function editProduct(p) {
        document.getElementById('prodModalTitle').textContent = 'Editar: ' + p.name;
        document.getElementById('prodId').value = p.id;
        document.getElementById('prodName').value = p.name;
        document.getElementById('prodSerial').value = p.serial_number || '';
        document.getElementById('prodIsVehicle').checked = p.is_vehicle == 1;
        document.getElementById('prodPlate').value = p.license_plate || '';
        document.getElementById('prodChassis').value = p.chassis || '';
        document.getElementById('prodCurrentKm').value = p.current_km !== null && p.current_km !== undefined ? p.current_km : '';
        document.getElementById('prodInterval').value = p.maintenance_interval_days || '';
        document.getElementById('prodIntervalKm').value = p.maintenance_interval_km || '';
        document.getElementById('prodLastMaint').value = p.last_maintenance_at || '';
        document.getElementById('prodLastMaintKm').value = p.last_maintenance_km !== null && p.last_maintenance_km !== undefined ? p.last_maintenance_km : '';
        document.getElementById('prodNotes').value = p.notes || '';
        document.getElementById('prodActive').checked = p.active == 1;
        document.getElementById('prodActiveRow').classList.remove('hidden');

        const container = document.getElementById('vehicleSubitemsContainer');
        container.innerHTML = '';
        if (Array.isArray(p.subitems) && p.subitems.length > 0) {
            p.subitems.forEach(s => addVehicleSubitemRow(s.name || '', s.notes || ''));
        }

        toggleVehicleFields();
        document.getElementById('productModal').classList.remove('hidden');
    }

    function closeProductModal() {
        document.getElementById('productModal').classList.add('hidden');
    }
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
