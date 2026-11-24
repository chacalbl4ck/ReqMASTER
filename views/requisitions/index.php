<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Requisições</h1>
            <p class="text-xs sm:text-sm text-slate-500">Gestão de solicitações para compra de materiais e contratação de serviços.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= url('requisicoes/nova') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-sm transition">
                <i class="fas fa-plus"></i> Nova Requisição
            </a>
        </div>
    </div>

    <!-- Barra de Filtros Avançados -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <form method="GET" action="<?= url('requisicoes') ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <input type="hidden" name="route" value="requisicoes">

            <!-- Busca por texto -->
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Buscar</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fas fa-search text-xs"></i>
                    </span>
                    <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Código, título, fornecedor..." class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <!-- Tipo -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Tipo</label>
                <select name="type" class="w-full py-1.5 px-2.5 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos os Tipos</option>
                    <option value="material" <?= ($filters['type'] ?? '') === 'material' ? 'selected' : '' ?>>Compra de Material</option>
                    <option value="servico" <?= ($filters['type'] ?? '') === 'servico' ? 'selected' : '' ?>>Prestação de Serviço</option>
                </select>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Status</label>
                <select name="status" class="w-full py-1.5 px-2.5 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos os Status</option>
                    <option value="pendente" <?= ($filters['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="em_analise" <?= ($filters['status'] ?? '') === 'em_analise' ? 'selected' : '' ?>>Em Análise</option>
                    <option value="aprovada" <?= ($filters['status'] ?? '') === 'aprovada' ? 'selected' : '' ?>>Aprovada</option>
                    <option value="em_execucao" <?= ($filters['status'] ?? '') === 'em_execucao' ? 'selected' : '' ?>>Em Execução</option>
                    <option value="concluida" <?= ($filters['status'] ?? '') === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                    <option value="rejeitada" <?= ($filters['status'] ?? '') === 'rejeitada' ? 'selected' : '' ?>>Rejeitada</option>
                </select>
            </div>

            <!-- Fornecedor / Para Quem -->
            <div>
                <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Para Quem (Fornecedor)</label>
                <select name="supplier_id" class="w-full py-1.5 px-2.5 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos os Fornecedores</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ((int)($filters['supplier_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Botões Filtrar / Limpar -->
            <div class="flex items-end gap-2">
                <button type="submit" class="w-full py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                    Filtrar
                </button>
                <a href="<?= url('requisicoes') ?>" class="py-1.5 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-xs font-medium transition text-center" title="Limpar Filtros">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabela de Listagem -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4">Código / Data</th>
                        <th class="py-3.5 px-4">Tipo</th>
                        <th class="py-3.5 px-4">Título da Requisição</th>
                        <th class="py-3.5 px-4">Para Quem Solicita (Fornecedor)</th>
                        <th class="py-3.5 px-4">Contrato Vinculado</th>
                        <th class="py-3.5 px-4">Solicitante</th>
                        <th class="py-3.5 px-4 text-right">Valor Total</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($requisitions)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-12 text-slate-400">
                                <i class="fas fa-inbox text-4xl mb-2 text-slate-300 block"></i>
                                Nenhuma requisição encontrada com os critérios informados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requisitions as $r): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="font-bold text-slate-900"><?= htmlspecialchars($r['code']) ?></span>
                                    <span class="block text-[10px] text-slate-400"><?= formatDate($r['created_at']) ?></span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold <?= $r['type'] === 'servico' ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'bg-emerald-50 text-emerald-700 border border-emerald-100' ?>">
                                        <i class="fas <?= $r['type'] === 'servico' ? 'fa-tools' : 'fa-box' ?> mr-1 text-[10px]"></i>
                                        <?= $r['type'] === 'servico' ? 'Serviço' : 'Material' ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 max-w-xs">
                                    <a href="<?= url('requisicoes/ver?id=' . $r['id']) ?>" class="font-medium text-slate-800 hover:text-indigo-600 block truncate">
                                        <?= htmlspecialchars($r['title']) ?>
                                    </a>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <?= priorityBadge($r['priority']) ?>
                                        <span class="text-[10px] text-slate-400">&bull; <?= htmlspecialchars($r['department']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-medium text-slate-800"><?= htmlspecialchars($r['supplier_name']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= \App\Helpers\Security::formatDocument($r['supplier_document']) ?></div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <?php if (!empty($r['contract_number'])): ?>
                                        <a href="<?= url('contratos/ver?id=' . $r['contract_id']) ?>" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 transition">
                                            <i class="fas fa-file-contract mr-1 text-[10px]"></i> <?= htmlspecialchars($r['contract_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-[11px]">Sem Contrato</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="text-slate-700 font-medium"><?= htmlspecialchars($r['requester_name']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($r['requester_dept'] ?? '') ?></div>
                                </td>
                                <td class="py-3.5 px-4 text-right font-bold text-slate-900 whitespace-nowrap">
                                    <?= formatMoney($r['total_amount']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?= statusBadge($r['status']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="<?= url('requisicoes/ver?id=' . $r['id']) ?>" class="p-1.5 text-slate-400 hover:text-indigo-600 transition" title="Detalhes">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= url('requisicoes/imprimir?id=' . $r['id']) ?>" target="_blank" class="p-1.5 text-slate-400 hover:text-slate-700 transition" title="Imprimir Folha Oficial">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php if (\App\Helpers\Security::hasRole(['admin'])): ?>
                                            <form method="POST" action="<?= url('requisicoes/excluir') ?>" class="inline" onsubmit="return confirm('Deseja excluir a requisição <?= $r['code'] ?>?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 transition" title="Excluir Requisição">
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

<?php require __DIR__ . '/../layout/footer.php'; ?>
