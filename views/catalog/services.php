<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fas fa-wrench text-indigo-600"></i> Cadastro de Serviços
            </h1>
            <p class="text-xs sm:text-sm text-slate-500">Cadastre os serviços padrão para agilizar a criação de requisições de prestação de serviços.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= url('produtos') ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-sm transition">
                <i class="fas fa-boxes-stacked mr-1"></i> Catálogo de Produtos
            </a>
            <button type="button" onclick="openServiceModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs sm:text-sm font-semibold shadow-sm transition flex items-center gap-2">
                <i class="fas fa-plus"></i> Cadastrar Serviço
            </button>
        </div>
    </div>

    <!-- Busca -->
    <form method="GET" action="<?= url('servicos') ?>" class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Buscar por nome do serviço ou observações..." class="flex-1 text-sm rounded-xl py-2 px-3">
        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition">
            <i class="fas fa-search mr-1"></i> Buscar
        </button>
        <?php if (!empty($search)): ?>
            <a href="<?= url('servicos') ?>" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold text-center">Limpar</a>
        <?php endif; ?>
    </form>

    <!-- Lista de Serviços -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-5">Nome do Serviço</th>
                        <th class="py-3.5 px-5">Observações Gerais</th>
                        <th class="py-3.5 px-5 text-center">Status</th>
                        <th class="py-3.5 px-5 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="4" class="py-10 text-center text-slate-400">
                                <i class="fas fa-tools text-3xl mb-2 block text-slate-300"></i>
                                Nenhum serviço cadastrado ainda.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $sv): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-5 font-bold text-slate-900"><?= htmlspecialchars($sv['name']) ?></td>
                                <td class="py-3.5 px-5 text-slate-500 text-xs"><?= htmlspecialchars($sv['notes'] ?: '-') ?></td>
                                <td class="py-3.5 px-5 text-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $sv['active'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                                        <?= $sv['active'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button" onclick='editService(<?= json_encode($sv) ?>)' class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition" title="Editar">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <form method="POST" action="<?= url('servicos/status') ?>" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $sv['id'] ?>">
                                            <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-amber-600 transition" title="<?= $sv['active'] ? 'Inativar' : 'Ativar' ?>">
                                                <i class="fas <?= $sv['active'] ? 'fa-ban' : 'fa-check-circle' ?>"></i>
                                            </button>
                                        </form>
                                        <?php if ($isAdmin): ?>
                                            <form method="POST" action="<?= url('servicos/excluir') ?>" class="inline" onsubmit="return confirm('Confirma excluir este serviço?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $sv['id'] ?>">
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

<!-- Modal Cadastro / Edição de Serviço -->
<div id="serviceModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 border border-slate-100 space-y-4">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 id="srvModalTitle" class="font-bold text-lg text-slate-900">Cadastrar Serviço</h3>
            <button type="button" onclick="closeServiceModal()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" action="<?= url('servicos/salvar') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="srvId" value="0">

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome do Serviço <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="srvName" required class="w-full text-sm rounded-xl" placeholder="Ex: Manutenção Preventiva de Ar-Condicionado">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Observações Gerais</label>
                <textarea name="notes" id="srvNotes" rows="3" class="w-full text-sm rounded-xl" placeholder="Escopo padrão, recomendações técnicas..."></textarea>
            </div>

            <div id="srvActiveRow" class="hidden">
                <label class="flex items-center text-xs font-semibold text-slate-700 cursor-pointer">
                    <input type="checkbox" name="active" id="srvActive" value="1" checked class="rounded text-indigo-600 mr-2">
                    Serviço Ativo
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeServiceModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl">Cancelar</button>
                <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md">Salvar Serviço</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openServiceModal() {
        document.getElementById('srvModalTitle').textContent = 'Cadastrar Serviço';
        document.getElementById('srvId').value = '0';
        document.getElementById('srvName').value = '';
        document.getElementById('srvNotes').value = '';
        document.getElementById('srvActive').checked = true;
        document.getElementById('srvActiveRow').classList.add('hidden');
        document.getElementById('serviceModal').classList.remove('hidden');
    }

    function editService(sv) {
        document.getElementById('srvModalTitle').textContent = 'Editar Serviço: ' + sv.name;
        document.getElementById('srvId').value = sv.id;
        document.getElementById('srvName').value = sv.name;
        document.getElementById('srvNotes').value = sv.notes || '';
        document.getElementById('srvActive').checked = sv.active == 1;
        document.getElementById('srvActiveRow').classList.remove('hidden');
        document.getElementById('serviceModal').classList.remove('hidden');
    }

    function closeServiceModal() {
        document.getElementById('serviceModal').classList.add('hidden');
    }
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
