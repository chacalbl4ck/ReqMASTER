<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Para Quem Solicitar (Fornecedores & Prestadores)</h1>
            <p class="text-xs sm:text-sm text-slate-500">Cadastro centralizado de empresas e prestadores de serviços com suporte a contratos.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= url('fornecedores/novo') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-sm transition">
                <i class="fas fa-plus"></i> Novo Fornecedor / Prestador
            </a>
        </div>
    </div>

    <!-- Filtros de Busca -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <form method="GET" action="<?= url('fornecedores') ?>" class="flex flex-col sm:flex-row gap-3">
            <input type="hidden" name="route" value="fornecedores">
            <div class="flex-1 relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <i class="fas fa-search text-xs"></i>
                </span>
                <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Buscar por Razão Social, Nome Fantasia, CNPJ/CPF ou Categoria..." class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="w-40">
                <select name="status" class="w-full py-2 px-3 text-xs rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos os Status</option>
                    <option value="ativo" <?= ($status ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inativo" <?= ($status ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>

            <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                Filtrar
            </button>
            <?php if (!empty($search) || !empty($status)): ?>
                <a href="<?= url('fornecedores') ?>" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-medium transition flex items-center">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabela de Fornecedores -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4">Razão Social / Nome Fantasia</th>
                        <th class="py-3.5 px-4">Tipo & Documento</th>
                        <th class="py-3.5 px-4">Categoria / Ramo</th>
                        <th class="py-3.5 px-4">Contato / Telefone</th>
                        <th class="py-3.5 px-4 text-center">Contratos</th>
                        <th class="py-3.5 px-4 text-center">Requisições</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($suppliers)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400">
                                <i class="fas fa-building text-3xl mb-2 text-slate-300 block"></i>
                                Nenhum fornecedor encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $s): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4">
                                    <a href="<?= url('fornecedores/ver?id=' . $s['id']) ?>" class="font-bold text-slate-900 hover:text-indigo-600">
                                        <?= htmlspecialchars($s['name']) ?>
                                    </a>
                                    <?php if (!empty($s['trade_name'])): ?>
                                        <span class="block text-[11px] text-slate-500"><?= htmlspecialchars($s['trade_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold <?= $s['type'] === 'pj' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700' ?> uppercase">
                                        <?= $s['type'] ?>
                                    </span>
                                    <span class="font-mono text-slate-700 ml-1"><?= \App\Helpers\Security::formatDocument($s['document']) ?></span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700">
                                        <?= htmlspecialchars($s['category']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="text-slate-800"><?= htmlspecialchars($s['contact_person'] ?? '-') ?></div>
                                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($s['phone'] ?? $s['email'] ?? '-') ?></div>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if ($s['total_contracts'] > 0): ?>
                                        <a href="<?= url('contratos?supplier_id=' . $s['id']) ?>" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-50 text-purple-700 hover:bg-purple-100 transition">
                                            <i class="fas fa-file-contract mr-1 text-[10px]"></i> <?= $s['total_contracts'] ?> ativo(s)
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[11px]">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if ($s['total_requisitions'] > 0): ?>
                                        <a href="<?= url('requisicoes?supplier_id=' . $s['id']) ?>" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition">
                                            <?= $s['total_requisitions'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[11px]">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $s['status'] === 'ativo' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                        <?= ucfirst($s['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="<?= url('fornecedores/ver?id=' . $s['id']) ?>" class="p-1.5 text-slate-400 hover:text-indigo-600 transition" title="Ver Detalhes">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= url('fornecedores/editar?id=' . $s['id']) ?>" class="p-1.5 text-slate-400 hover:text-slate-700 transition" title="Editar">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>
                                        <form method="POST" action="<?= url('fornecedores/toggle-status') ?>" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="p-1.5 <?= $s['status'] === 'ativo' ? 'text-slate-400 hover:text-amber-600' : 'text-emerald-500 hover:text-emerald-700' ?> transition" title="<?= $s['status'] === 'ativo' ? 'Inativar Fornecedor' : 'Reativar Fornecedor' ?>">
                                                <i class="fas <?= $s['status'] === 'ativo' ? 'fa-ban' : 'fa-check-circle' ?>"></i>
                                            </button>
                                        </form>
                                        <a href="<?= url('requisicoes/nova?supplier_id=' . $s['id']) ?>" class="p-1.5 text-indigo-600 hover:text-indigo-800 transition" title="Abrir Requisição para este Fornecedor">
                                            <i class="fas fa-plus-circle"></i>
                                        </a>
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
