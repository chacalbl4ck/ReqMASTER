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
        <div class="flex items-center gap-2">
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
        <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Buscar por nome do produto, número de série ou observações..." class="flex-1 text-sm rounded-xl py-2 px-3">
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
                        <th class="py-3.5 px-4">Produto / Equipamento</th>
                        <th class="py-3.5 px-4">Nº de Série</th>
                        <th class="py-3.5 px-4 text-center">Preventiva (Dias)</th>
                        <th class="py-3.5 px-4 text-center">Última Manutenção</th>
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
                                            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center border border-slate-200 shrink-0">
                                                <i class="fas fa-box"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="<?= url('produtos/ver?id=' . $p['id']) ?>" class="font-bold text-slate-900 hover:text-indigo-600 block">
                                                <?= htmlspecialchars($p['name']) ?>
                                            </a>
                                            <?php if (!empty($p['notes'])): ?>
                                                <span class="text-[11px] text-slate-500 block line-clamp-1"><?= htmlspecialchars($p['notes']) ?></span>
                                            <?php endif; ?>
                                            <?php if ((int)$p['photo_count'] > 0): ?>
                                                <span class="text-[10px] text-indigo-600 font-semibold"><i class="fas fa-camera mr-1"></i><?= (int)$p['photo_count'] ?> foto(s)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-xs text-slate-700">
                                    <?= !empty($p['serial_number']) ? htmlspecialchars($p['serial_number']) : '<span class="text-slate-300">-</span>' ?>
                                </td>
                                <td class="py-3.5 px-4 text-center text-xs">
                                    <?php if (!empty($p['maintenance_interval_days'])): ?>
                                        <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 font-bold border border-indigo-200">
                                            A cada <?= (int)$p['maintenance_interval_days'] ?> dias
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400">Não definido</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center text-xs">
                                    <?php if (!empty($p['last_maintenance_at'])): ?>
                                        <span class="font-semibold text-slate-800 block"><?= formatDate($p['last_maintenance_at']) ?></span>
                                        <?php if (!empty($p['is_maintenance_overdue'])): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 bg-rose-100 px-2 py-0.5 rounded-full mt-0.5">
                                                <i class="fas fa-exclamation-triangle"></i> Atrasada (<?= (int)$p['days_since_maintenance'] ?>d)
                                            </span>
                                        <?php elseif ($p['days_since_maintenance'] !== null): ?>
                                            <span class="text-[10px] text-emerald-700">Em dia (<?= (int)$p['days_since_maintenance'] ?>d)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Nunca registrado</span>
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
                                        <button type="button" onclick='editProduct(<?= json_encode($p) ?>)' class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition" title="Editar">
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
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 border border-slate-100 space-y-4 max-h-[92vh] overflow-y-auto">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 id="prodModalTitle" class="font-bold text-lg text-slate-900">Cadastrar Produto / Equipamento</h3>
            <button type="button" onclick="closeProductModal()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" action="<?= url('produtos/salvar') ?>" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="prodId" value="0">

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome do Produto / Equipamento <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="prodName" required class="w-full text-sm rounded-xl" placeholder="Ex: Ar-Condicionado Split 12000 BTUs ou Notebook">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Número de Série (Opcional)</label>
                    <input type="text" name="serial_number" id="prodSerial" class="w-full text-sm rounded-xl font-mono" placeholder="Ex: SN-2026-9910">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Manutenção Preventiva (X Dias)</label>
                    <input type="number" name="maintenance_interval_days" id="prodInterval" min="1" class="w-full text-sm rounded-xl" placeholder="Ex: 90, 180, 365">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Última Manutenção / Reparo (Opcional / Automático)</label>
                <input type="date" name="last_maintenance_at" id="prodLastMaint" class="w-full text-sm rounded-xl">
                <p class="text-[11px] text-slate-400 mt-1">Atualizado automaticamente sempre que uma OS de manutenção ou reparo vinculada for concluída.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Fotos do Produto (Opcional - Múltiplas)</label>
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
    function openProductModal() {
        document.getElementById('prodModalTitle').textContent = 'Cadastrar Produto / Equipamento';
        document.getElementById('prodId').value = '0';
        document.getElementById('prodName').value = '';
        document.getElementById('prodSerial').value = '';
        document.getElementById('prodInterval').value = '';
        document.getElementById('prodLastMaint').value = '';
        document.getElementById('prodNotes').value = '';
        document.getElementById('prodActive').checked = true;
        document.getElementById('prodActiveRow').classList.add('hidden');
        document.getElementById('productModal').classList.remove('hidden');
    }

    function editProduct(p) {
        document.getElementById('prodModalTitle').textContent = 'Editar Produto: ' + p.name;
        document.getElementById('prodId').value = p.id;
        document.getElementById('prodName').value = p.name;
        document.getElementById('prodSerial').value = p.serial_number || '';
        document.getElementById('prodInterval').value = p.maintenance_interval_days || '';
        document.getElementById('prodLastMaint').value = p.last_maintenance_at || '';
        document.getElementById('prodNotes').value = p.notes || '';
        document.getElementById('prodActive').checked = p.active == 1;
        document.getElementById('prodActiveRow').classList.remove('hidden');
        document.getElementById('productModal').classList.remove('hidden');
    }

    function closeProductModal() {
        document.getElementById('productModal').classList.add('hidden');
    }
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
