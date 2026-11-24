<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Centros de Custo</h1>
            <p class="text-xs sm:text-sm text-slate-500">Cadastre e organize os centros de custo disponíveis para seleção nas requisições.</p>
        </div>
        <button type="button" onclick="openCostCenterModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs sm:text-sm font-semibold shadow-sm transition flex items-center gap-2">
            <i class="fas fa-plus"></i> Novo Centro de Custo
        </button>
    </div>

    <!-- Lista de Centros de Custo -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-5">Código</th>
                        <th class="py-3.5 px-5">Nome / Departamento</th>
                        <th class="py-3.5 px-5">Descrição</th>
                        <th class="py-3.5 px-5 text-center">Status</th>
                        <th class="py-3.5 px-5 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($costCenters)): ?>
                        <tr>
                            <td colspan="5" class="py-10 text-center text-slate-400">
                                <i class="fas fa-sitemap text-3xl mb-2 block text-slate-300"></i>
                                Nenhum centro de custo cadastrado ainda. Clique em <strong>Novo Centro de Custo</strong> para adicionar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($costCenters as $cc): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-5 font-mono font-bold text-indigo-700"><?= htmlspecialchars($cc['code']) ?></td>
                                <td class="py-3.5 px-5 font-semibold text-slate-800"><?= htmlspecialchars($cc['name']) ?></td>
                                <td class="py-3.5 px-5 text-slate-500 text-xs"><?= htmlspecialchars($cc['description'] ?: '-') ?></td>
                                <td class="py-3.5 px-5 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $cc['active'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $cc['active'] ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                                        <?= $cc['active'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button" onclick='editCostCenter(<?= json_encode($cc) ?>)' class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition" title="Editar">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>

                                        <form method="POST" action="<?= url('centros-de-custo/status') ?>" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $cc['id'] ?>">
                                            <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition" title="<?= $cc['active'] ? 'Desativar' : 'Ativar' ?>">
                                                <i class="fas <?= $cc['active'] ? 'fa-ban' : 'fa-check-circle' ?>"></i>
                                            </button>
                                        </form>

                                        <?php if ($isAdmin): ?>
                                            <form method="POST" action="<?= url('centros-de-custo/excluir') ?>" class="inline" onsubmit="return confirm('Confirma excluir o centro de custo <?= htmlspecialchars($cc['code']) ?>?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $cc['id'] ?>">
                                                <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="Excluir">
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

<!-- Modal Cadastro / Edição -->
<div id="ccModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 border border-slate-100 space-y-4">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 id="ccModalTitle" class="font-bold text-lg text-slate-900">Novo Centro de Custo</h3>
            <button type="button" onclick="closeCostCenterModal()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" action="<?= url('centros-de-custo/salvar') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="ccId" value="0">

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Código <span class="text-rose-500">*</span></label>
                <input type="text" name="code" id="ccCode" required maxlength="30" placeholder="Ex: CC-001 ou 10.01" class="w-full text-sm rounded-xl font-mono uppercase">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome do Centro de Custo <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="ccName" required maxlength="120" placeholder="Ex: Tecnologia da Informação" class="w-full text-sm rounded-xl">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Descrição (Opcional)</label>
                <textarea name="description" id="ccDesc" rows="2" class="w-full text-sm rounded-xl" placeholder="Finalidade ou área responsável..."></textarea>
            </div>

            <div id="ccActiveRow" class="hidden">
                <label class="flex items-center text-xs font-semibold text-slate-700 cursor-pointer">
                    <input type="checkbox" name="active" id="ccActive" value="1" checked class="rounded text-indigo-600 mr-2">
                    Centro de Custo Ativo
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeCostCenterModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Cancelar</button>
                <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCostCenterModal() {
        document.getElementById('ccModalTitle').textContent = 'Novo Centro de Custo';
        document.getElementById('ccId').value = '0';
        document.getElementById('ccCode').value = '';
        document.getElementById('ccName').value = '';
        document.getElementById('ccDesc').value = '';
        document.getElementById('ccActive').checked = true;
        document.getElementById('ccActiveRow').classList.add('hidden');
        document.getElementById('ccModal').classList.remove('hidden');
    }

    function editCostCenter(cc) {
        document.getElementById('ccModalTitle').textContent = 'Editar Centro de Custo';
        document.getElementById('ccId').value = cc.id;
        document.getElementById('ccCode').value = cc.code;
        document.getElementById('ccName').value = cc.name;
        document.getElementById('ccDesc').value = cc.description || '';
        document.getElementById('ccActive').checked = cc.active == 1;
        document.getElementById('ccActiveRow').classList.remove('hidden');
        document.getElementById('ccModal').classList.remove('hidden');
    }

    function closeCostCenterModal() {
        document.getElementById('ccModal').classList.add('hidden');
    }
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
