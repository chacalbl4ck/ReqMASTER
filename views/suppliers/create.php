<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= url('fornecedores') ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition flex items-center gap-1 mb-1">
                <i class="fas fa-arrow-left"></i> Voltar para Fornecedores
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Cadastrar Para Quem Solicitar (Fornecedor/Prestador)</h1>
            <p class="text-xs sm:text-sm text-slate-500">Cadastre a empresa ou profissional autônomo para quem serão emitidas as requisições.</p>
        </div>
    </div>

    <form method="POST" action="<?= url('fornecedores/salvar') ?>" class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
        <?= csrf_field() ?>

        <!-- Tipo e Documentos -->
        <div class="space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Identificação Básica</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Tipo de Pessoa <span class="text-rose-500">*</span></label>
                    <select name="type" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="pj">Pessoa Jurídica (CNPJ)</option>
                        <option value="pf">Pessoa Física (CPF)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">CNPJ / CPF <span class="text-rose-500">*</span></label>
                    <input type="text" name="document" required class="mask-doc w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="00.000.000/0000-00">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Categoria / Ramo <span class="text-rose-500">*</span></label>
                    <input type="text" name="category" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ex: Tecnologia, Obras, Limpeza...">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Razão Social / Nome Completo <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" required class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nome oficial registrado">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome Fantasia (Opcional)</label>
                    <input type="text" name="trade_name" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nome comercial ou de marca">
                </div>
            </div>
        </div>

        <!-- Contato -->
        <div class="space-y-4 pt-4 border-t border-slate-100">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Contato & Atendimento</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Pessoa de Contato</label>
                    <input type="text" name="contact_person" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ex: Roberto Silva (Comercial)">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">E-mail</label>
                    <input type="email" name="email" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="comercial@fornecedor.com">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Telefone / WhatsApp</label>
                    <input type="text" name="phone" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="(11) 99999-9999">
                </div>
            </div>
        </div>

        <!-- Endereço -->
        <div class="space-y-4 pt-4 border-t border-slate-100">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Endereço & Localização</h3>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Logradouro & Número</label>
                    <input type="text" name="address" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Av. Paulista, 1000 - Sala 40">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Cidade</label>
                    <input type="text" name="city" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="São Paulo">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">UF (Estado)</label>
                    <input type="text" name="state" maxlength="2" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 uppercase" placeholder="SP">
                </div>
            </div>
        </div>

        <!-- Financeiro & Observações -->
        <div class="space-y-4 pt-4 border-t border-slate-100">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Dados Bancários & Observações</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Dados Bancários / Chave Pix</label>
                    <textarea name="bank_info" rows="3" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Banco, Agência, Conta Corrente ou Chave Pix para liquidação financeira"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Observações Cadastrais / Histórico</label>
                    <textarea name="notes" rows="3" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Informações de homologação, restrições ou termos especiais"></textarea>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Status do Fornecedor</label>
                <select name="status" class="w-48 text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="ativo" selected>Ativo (Apto a receber requisições)</option>
                    <option value="inativo">Inativo (Bloqueado)</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="<?= url('fornecedores') ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition">
                Cancelar
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md shadow-indigo-600/30 transition">
                <i class="fas fa-save mr-2"></i> Salvar Cadastro
            </button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
