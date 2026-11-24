<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= url('contratos/ver?id=' . $contract['id']) ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition flex items-center gap-1 mb-1">
                <i class="fas fa-arrow-left"></i> Voltar ao Contrato
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Editar Contrato</h1>
            <p class="text-xs sm:text-sm text-slate-500">Atualize dados cadastrais, teto orçamentário ou prazos de vigência.</p>
        </div>
    </div>

    <form method="POST" action="<?= url('contratos/atualizar') ?>" class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $contract['id'] ?>">

        <div class="space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Identificação do Contrato</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Número / Código <span class="text-rose-500">*</span></label>
                    <input type="text" name="contract_number" required value="<?= htmlspecialchars($contract['contract_number']) ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 uppercase">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Fornecedor / Prestador <span class="text-rose-500">*</span></label>
                    <select name="supplier_id" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ((int)$contract['supplier_id'] === (int)$s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Título / Objeto do Contrato <span class="text-rose-500">*</span></label>
                <input type="text" name="title" required value="<?= htmlspecialchars($contract['title']) ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tipo de Contrato</label>
                    <select name="type" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="servico" <?= $contract['type'] === 'servico' ? 'selected' : '' ?>>Prestação de Serviços</option>
                        <option value="material" <?= $contract['type'] === 'material' ? 'selected' : '' ?>>Fornecimento de Materiais</option>
                        <option value="misto" <?= $contract['type'] === 'misto' ? 'selected' : '' ?>>Misto</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Valor Total / Teto Orçamentário (R$) <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-xs">R$</span>
                        <input type="number" step="0.01" min="0" name="total_value" required value="<?= $contract['total_value'] ?>" class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4 pt-4 border-t border-slate-100">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Vigência & Termos</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Início da Vigência <span class="text-rose-500">*</span></label>
                    <input type="date" name="start_date" required value="<?= $contract['start_date'] ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Término da Vigência <span class="text-rose-500">*</span></label>
                    <input type="date" name="end_date" required value="<?= $contract['end_date'] ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Cláusula de Renovação</label>
                <input type="text" name="renewal_clause" value="<?= htmlspecialchars($contract['renewal_clause'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Observações</label>
                <textarea name="notes" rows="3" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"><?= htmlspecialchars($contract['notes'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Status do Contrato</label>
                <select name="status" class="w-48 text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="ativo" <?= $contract['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="encerrado" <?= $contract['status'] === 'encerrado' ? 'selected' : '' ?>>Encerrado</option>
                    <option value="suspenso" <?= $contract['status'] === 'suspenso' ? 'selected' : '' ?>>Suspenso</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="<?= url('contratos/ver?id=' . $contract['id']) ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition">
                Cancelar
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md shadow-indigo-600/30 transition">
                <i class="fas fa-save mr-2"></i> Atualizar Contrato
            </button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
