<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= url('fornecedores/ver?id=' . $supplier['id']) ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition flex items-center gap-1 mb-1">
                <i class="fas fa-arrow-left"></i> Voltar ao Perfil
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Editar Fornecedor / Prestador</h1>
            <p class="text-xs sm:text-sm text-slate-500">Atualize os dados cadastrais, contato ou informações bancárias.</p>
        </div>
    </div>

    <form method="POST" action="<?= url('fornecedores/atualizar') ?>" class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $supplier['id'] ?>">

        <!-- Tipo e Documentos -->
        <div class="space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Identificação Básica</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tipo de Pessoa <span class="text-rose-500">*</span></label>
                    <select name="type" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="pj" <?= $supplier['type'] === 'pj' ? 'selected' : '' ?>>Pessoa Jurídica (CNPJ)</option>
                        <option value="pf" <?= $supplier['type'] === 'pf' ? 'selected' : '' ?>>Pessoa Física (CPF)</option>
                    </select>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-xs font-semibold text-slate-700 uppercase">CNPJ / CPF <span class="text-rose-500">*</span></label>
                        <button type="button" onclick="consultarCnpjAuto()" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 transition flex items-center gap-1">
                            <i class="fas fa-search text-[10px]" id="cnpjSearchIcon"></i> Buscar CNPJ
                        </button>
                    </div>
                    <input type="text" name="document" id="docInput" required value="<?= htmlspecialchars(\App\Helpers\Security::formatDocument($supplier['document'])) ?>" class="mask-doc w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Categoria / Ramo <span class="text-rose-500">*</span></label>
                    <input type="text" name="category" id="categoryInput" required value="<?= htmlspecialchars($supplier['category']) ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Razão Social / Nome Completo <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="nameInput" required value="<?= htmlspecialchars($supplier['name']) ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome Fantasia</label>
                    <input type="text" name="trade_name" id="tradeNameInput" value="<?= htmlspecialchars($supplier['trade_name'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- Contato -->
        <div class="space-y-4 pt-4 border-t border-slate-100">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Contato & Atendimento</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Pessoa de Contato</label>
                    <input type="text" name="contact_person" id="contactPersonInput" value="<?= htmlspecialchars($supplier['contact_person'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">E-mail</label>
                    <input type="email" name="email" id="emailInput" value="<?= htmlspecialchars($supplier['email'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Telefone / WhatsApp</label>
                    <input type="text" name="phone" id="phoneInput" value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- Endereço -->
        <div class="space-y-4 pt-4 border-t border-slate-100">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Endereço & Localização</h3>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Logradouro & Número</label>
                    <input type="text" name="address" id="addressInput" value="<?= htmlspecialchars($supplier['address'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Cidade</label>
                    <input type="text" name="city" id="cityInput" value="<?= htmlspecialchars($supplier['city'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">UF (Estado)</label>
                    <input type="text" name="state" id="stateInput" maxlength="2" value="<?= htmlspecialchars($supplier['state'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 uppercase">
                </div>
            </div>
        </div>

        <!-- Financeiro & Observações -->
        <div class="space-y-4 pt-4 border-t border-slate-100">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Dados Bancários & Observações</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Dados Bancários / Chave Pix</label>
                    <textarea name="bank_info" rows="3" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"><?= htmlspecialchars($supplier['bank_info'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Observações Cadastrais</label>
                    <textarea name="notes" rows="3" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"><?= htmlspecialchars($supplier['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Status do Fornecedor</label>
                <select name="status" class="w-48 text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="ativo" <?= $supplier['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inativo" <?= $supplier['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="<?= url('fornecedores/ver?id=' . $supplier['id']) ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition">
                Cancelar
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md shadow-indigo-600/30 transition">
                <i class="fas fa-save mr-2"></i> Atualizar Dados
            </button>
        </div>
    </form>
</div>

<script>
    function consultarCnpjAuto() {
        const docInput = document.getElementById('docInput');
        const icon = document.getElementById('cnpjSearchIcon');
        const raw = docInput.value.replace(/\D/g, '');

        if (raw.length !== 14) {
            alert('Por favor, informe um CNPJ completo com 14 dígitos para consultar.');
            docInput.focus();
            return;
        }

        icon.className = 'fas fa-spinner fa-spin text-[10px]';

        fetch(`<?= url('fornecedores/consultar-cnpj') ?>?cnpj=${raw}`)
            .then(res => res.json())
            .then(data => {
                icon.className = 'fas fa-search text-[10px]';
                if (data.success) {
                    if (data.name) document.getElementById('nameInput').value = data.name;
                    if (data.trade_name) document.getElementById('tradeNameInput').value = data.trade_name;
                    if (data.category) document.getElementById('categoryInput').value = data.category;
                    if (data.phone) document.getElementById('phoneInput').value = data.phone;
                    if (data.email) document.getElementById('emailInput').value = data.email;
                    if (data.address) document.getElementById('addressInput').value = data.address;
                    if (data.city) document.getElementById('cityInput').value = data.city;
                    if (data.state) document.getElementById('stateInput').value = data.state;
                    
                    const tip = document.createElement('div');
                    tip.className = 'p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl mt-3';
                    tip.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Dados cadastrais do CNPJ atualizados!';
                    docInput.closest('.space-y-4').prepend(tip);
                    setTimeout(() => tip.remove(), 5000);
                } else {
                    alert(data.message || 'CNPJ não encontrado na base pública.');
                }
            })
            .catch(err => {
                icon.className = 'fas fa-search text-[10px]';
                alert('Erro ao conectar ao serviço de consulta de CNPJ.');
            });
    }
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
