<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Gestão de Contratos</h1>
            <p class="text-xs sm:text-sm text-slate-500">Controle de tetos orçamentários, saldos remanescentes e vigência de contratos.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= url('contratos/novo') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-sm transition">
                <i class="fas fa-plus"></i> Novo Contrato
            </a>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <form method="GET" action="<?= url('contratos') ?>" class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <input type="hidden" name="route" value="contratos">

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Buscar</label>
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Número ou título do contrato..." class="w-full px-3 py-1.5 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Fornecedor</label>
                <select name="supplier_id" class="w-full py-1.5 px-2.5 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos os Fornecedores</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ((int)($_GET['supplier_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Status</label>
                <select name="status" class="w-full py-1.5 px-2.5 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos os Status</option>
                    <option value="ativo" <?= ($_GET['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="encerrado" <?= ($_GET['status'] ?? '') === 'encerrado' ? 'selected' : '' ?>>Encerrado</option>
                    <option value="suspenso" <?= ($_GET['status'] ?? '') === 'suspenso' ? 'selected' : '' ?>>Suspenso</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                    Filtrar
                </button>
                <a href="<?= url('contratos') ?>" class="py-1.5 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-xs font-medium transition text-center" title="Limpar Filtros">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Grade de Contratos em Cards Modernos -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php if (empty($contracts)): ?>
            <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-slate-200 text-slate-400">
                <i class="fas fa-file-contract text-4xl mb-2 text-slate-300 block"></i>
                Nenhum contrato encontrado.
            </div>
        <?php else: ?>
            <?php foreach ($contracts as $c): ?>
                <?php 
                    $pct = (float)$c['usage_percentage'];
                    $barColor = $pct > 85 ? 'bg-rose-500' : ($pct > 65 ? 'bg-amber-500' : 'bg-purple-600');
                ?>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 hover:shadow-md transition flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start gap-2 mb-3">
                            <span class="px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                <?= htmlspecialchars($c['contract_number']) ?>
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold <?= $c['status'] === 'ativo' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                <?= ucfirst($c['status']) ?>
                            </span>
                        </div>

                        <h3 class="font-bold text-slate-900 text-base leading-snug mb-1">
                            <a href="<?= url('contratos/ver?id=' . $c['id']) ?>" class="hover:text-indigo-600">
                                <?= htmlspecialchars($c['title']) ?>
                            </a>
                        </h3>

                        <p class="text-xs text-slate-500 mb-4">
                            Fornecedor: <strong class="text-slate-700"><?= htmlspecialchars($c['supplier_name']) ?></strong>
                        </p>

                        <!-- Barra de Consumo do Teto -->
                        <div class="space-y-1.5 mb-4">
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-500">Consumo do Teto:</span>
                                <span class="font-bold text-slate-800"><?= $pct ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                                <div class="<?= $barColor ?> h-2.5 rounded-full transition-all duration-300" style="width: <?= min(100, $pct) ?>%"></div>
                            </div>
                            <div class="flex justify-between text-[11px] text-slate-500">
                                <span>Gasto: <strong><?= formatMoney($c['used_value']) ?></strong></span>
                                <span>Teto: <strong><?= formatMoney($c['total_value']) ?></strong></span>
                            </div>
                        </div>

                        <!-- Saldo Disponível em Destaque -->
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex justify-between items-center text-xs">
                            <span class="text-slate-500 font-medium">Saldo Restante:</span>
                            <span class="font-black text-emerald-700 text-sm"><?= formatMoney($c['balance_value']) ?></span>
                        </div>
                    </div>

                    <!-- Rodapé do Card -->
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                        <div>
                            <i class="far fa-calendar-alt mr-1"></i> Até <?= formatDate($c['end_date']) ?>
                        </div>
                        <div class="flex items-center gap-1">
                            <a href="<?= url('requisicoes/nova?supplier_id=' . $c['supplier_id'] . '&contract_id=' . $c['id']) ?>" class="p-1.5 text-indigo-600 hover:text-indigo-800 font-medium" title="Criar Requisição Vinculada">
                                <i class="fas fa-plus-circle text-base"></i>
                            </a>
                            <?php if (\App\Helpers\Security::hasRole(['admin'])): ?>
                                <form method="POST" action="<?= url('contratos/excluir') ?>" class="inline" onsubmit="return confirm('Confirma excluir o contrato <?= htmlspecialchars($c['contract_number']) ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 transition" title="Excluir Contrato">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="<?= url('contratos/ver?id=' . $c['id']) ?>" class="p-1.5 text-slate-400 hover:text-slate-700" title="Ver Detalhes">
                                <i class="fas fa-arrow-right text-base"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
