<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= url('contratos') ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition flex items-center gap-1 mb-1">
                <i class="fas fa-arrow-left"></i> Voltar para Contratos
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Cadastrar Novo Contrato</h1>
            <p class="text-xs sm:text-sm text-slate-500">Cadastre um contrato com teto financeiro e vigência para vincular requisições de serviços ou compras.</p>
        </div>
    </div>

    <form method="POST" action="<?= url('contratos/salvar') ?>" class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
        <?= csrf_field() ?>

        <div class="space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Identificação do Contrato</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Número / Código do Contrato <span class="text-rose-500">*</span></label>
                    <input type="text" name="contract_number" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 uppercase" placeholder="Ex: CT-2026/015">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Fornecedor / Prestador Contratado <span class="text-rose-500">*</span></label>
                    <select name="supplier_id" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- Selecione o Fornecedor --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($selectedSupplierId === (int)$s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['category']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Título / Objeto do Contrato <span class="text-rose-500">*</span></label>
                <input type="text" name="title" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ex: Prestação de Serviços Contínuos de Suporte em TI e Telefonia">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tipo de Contrato</label>
                    <select name="type" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="servico">Prestação de Serviços</option>
                        <option value="material">Fornecimento de Materiais</option>
                        <option value="misto">Misto (Serviços e Materiais)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Valor Total / Teto Orçamentário (R$) <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-xs">R$</span>
                        <input type="number" step="0.01" min="0" name="total_value" required class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="0.00">
                    </div>
                </div>
            </div>
        </div>

        <!-- Vigência -->
        <div class="space-y-4 pt-4 border-t border-slate-100">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Prazos de Vigência & Termos</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Data de Início da Vigência <span class="text-rose-500">*</span></label>
                    <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Data de Término da Vigência <span class="text-rose-500">*</span></label>
                    <input type="date" name="end_date" required value="<?= date('Y-m-d', strtotime('+1 year')) ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Cláusula de Renovação / Aditivos</label>
                <input type="text" name="renewal_clause" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ex: Renovação automática a cada 12 meses, mediante reajuste pelo IPCA">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Observações / Termos Gerais</label>
                <textarea name="notes" rows="3" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="SLA acordado, condições de faturamento, penalidades, retenções..."></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Status do Contrato</label>
                <select name="status" class="w-48 text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="ativo" selected>Ativo (Permite requisições)</option>
                    <option value="encerrado">Encerrado</option>
                    <option value="suspenso">Suspenso</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="<?= url('contratos') ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition">
                Cancelar
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md shadow-indigo-600/30 transition">
                <i class="fas fa-save mr-2"></i> Salvar Contrato
            </button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
