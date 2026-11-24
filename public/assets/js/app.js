/**
 * App JavaScript - ReqMaster
 * Manipulação de Requisições, Contratos e Itens Dinâmicos
 */

document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------
    // 1. CARREGAMENTO DINÂMICO DE CONTRATOS DO FORNECEDOR ESCOLHIDO
    // -------------------------------------------------------------
    const supplierSelect = document.getElementById('supplier_select');
    const contractSelect = document.getElementById('contract_select');
    const contractInfoBox = document.getElementById('contract_info_box');
    const contractBalanceSpan = document.getElementById('contract_balance_span');

    if (supplierSelect && contractSelect) {
        supplierSelect.addEventListener('change', function () {
            const supplierId = this.value;
            contractSelect.innerHTML = '<option value="">Carregando contratos disponíveis...</option>';
            contractSelect.disabled = true;
            if (contractInfoBox) contractInfoBox.classList.add('hidden');

            if (!supplierId) {
                contractSelect.innerHTML = '<option value="">-- Primeiro selecione um fornecedor --</option>';
                contractSelect.disabled = true;
                return;
            }

            // Busca os contratos via API
            fetch(`${window.BASE_URL || ''}/contratos/api-por-fornecedor?supplier_id=${supplierId}`)
                .then(res => res.json())
                .then(contracts => {
                    contractSelect.innerHTML = '<option value="">-- Sem vínculo com contrato (compra pontual) --</option>';
                    if (contracts && contracts.length > 0) {
                        contracts.forEach(c => {
                            const balanceFormatted = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(c.balance_value);
                            const opt = document.createElement('option');
                            opt.value = c.id;
                            opt.dataset.balance = c.balance_value;
                            opt.dataset.total = c.total_value;
                            opt.textContent = `${c.contract_number} - ${c.title} (Saldo Disponível: ${balanceFormatted})`;
                            contractSelect.appendChild(opt);
                        });
                        contractSelect.disabled = false;
                    } else {
                        const opt = document.createElement('option');
                        opt.value = "";
                        opt.textContent = "-- Nenhum contrato ativo cadastrado para este fornecedor --";
                        contractSelect.appendChild(opt);
                        contractSelect.disabled = false;
                    }
                })
                .catch(err => {
                    console.error('Erro ao buscar contratos:', err);
                    contractSelect.innerHTML = '<option value="">Erro ao carregar contratos</option>';
                });
        });

        // Quando o contrato é selecionado no dropdown
        contractSelect.addEventListener('change', function () {
            const selectedOpt = this.options[this.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.balance) {
                const bal = parseFloat(selectedOpt.dataset.balance);
                if (contractInfoBox && contractBalanceSpan) {
                    contractBalanceSpan.textContent = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(bal);
                    contractInfoBox.classList.remove('hidden');
                }
            } else {
                if (contractInfoBox) contractInfoBox.classList.add('hidden');
            }
            checkContractBalanceLimit();
        });
    }

    // -------------------------------------------------------------
    // 2. TABELA DINÂMICA DE ITENS / SERVIÇOS
    // -------------------------------------------------------------
    const itemsTableBody = document.getElementById('items_table_body');
    const btnAddItem = document.getElementById('btn_add_item');
    const grandTotalSpan = document.getElementById('grand_total_span');

    if (itemsTableBody && btnAddItem) {
        btnAddItem.addEventListener('click', function () {
            addNewItemRow();
        });

        // Event delegation para cálculo em tempo real e remoção
        itemsTableBody.addEventListener('input', function (e) {
            if (e.target.classList.contains('calc-qty') || e.target.classList.contains('calc-price')) {
                calculateRow(e.target.closest('tr'));
                calculateGrandTotal();
            }
        });

        itemsTableBody.addEventListener('click', function (e) {
            const btnRemove = e.target.closest('.btn-remove-item');
            if (btnRemove) {
                const row = btnRemove.closest('tr');
                if (itemsTableBody.querySelectorAll('tr').length > 1) {
                    row.remove();
                    calculateGrandTotal();
                } else {
                    alert('A requisição precisa conter pelo menos um item.');
                }
            }
        });
    }

    function addNewItemRow() {
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-100 hover:bg-slate-50 transition';
        tr.innerHTML = `
            <td class="py-3 px-3">
                <input type="text" name="item_description[]" required class="w-full text-sm rounded-lg border-slate-300 focus:border-primary-500 focus:ring-primary-500" placeholder="Descrição do material ou escopo do serviço">
                <input type="text" name="item_specs[]" class="w-full text-xs text-slate-500 rounded border-slate-200 mt-1 focus:border-primary-500 focus:ring-primary-500" placeholder="Especificações técnicas, marca de referência, etc (opcional)">
            </td>
            <td class="py-3 px-3 w-28">
                <select name="item_unit[]" class="w-full text-sm rounded-lg border-slate-300 focus:border-primary-500 focus:ring-primary-500">
                    <option value="un">UN</option>
                    <option value="cx">CX</option>
                    <option value="pct">PCT</option>
                    <option value="kg">KG</option>
                    <option value="m">M</option>
                    <option value="l">L</option>
                    <option value="hr">HORA</option>
                    <option value="dia">DIÁRIA</option>
                    <option value="mes">MÊS</option>
                    <option value="serv">SERVIÇO</option>
                    <option value="gl">GLOBAL</option>
                </select>
            </td>
            <td class="py-3 px-3 w-28">
                <input type="number" step="0.01" min="0.01" value="1.00" name="item_quantity[]" required class="calc-qty w-full text-sm text-right rounded-lg border-slate-300 focus:border-primary-500 focus:ring-primary-500">
            </td>
            <td class="py-3 px-3 w-36">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-xs text-slate-400">R$</span>
                    <input type="text" name="item_price[]" placeholder="0,00" class="calc-price w-full text-sm text-right pl-7 rounded-lg border-slate-300 focus:border-primary-500 focus:ring-primary-500">
                </div>
            </td>
            <td class="py-3 px-3 w-32 text-right font-medium text-slate-800 row-subtotal">
                -
            </td>
            <td class="py-3 px-2 w-12 text-center">
                <button type="button" class="btn-remove-item text-slate-400 hover:text-rose-600 transition p-1">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        `;
        itemsTableBody.appendChild(tr);
    }

    function parseMoneyInput(val) {
        if (!val) return 0;
        let cleaned = val.toString().replace(/R\$/g, '').replace(/\s/g, '').replace(/\./g, '').replace(/,/g, '.').trim();
        let num = parseFloat(cleaned);
        return isNaN(num) ? 0 : num;
    }

    function calculateRow(row) {
        if (!row) return;
        const qty = parseFloat(row.querySelector('.calc-qty')?.value || 0);
        const priceVal = row.querySelector('.calc-price')?.value;
        const price = parseMoneyInput(priceVal);
        const subtotalCell = row.querySelector('.row-subtotal');
        if (subtotalCell) {
            if (price > 0) {
                const subtotal = qty * price;
                subtotalCell.textContent = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(subtotal);
            } else {
                subtotalCell.textContent = '-';
            }
        }
    }

    function calculateGrandTotal() {
        if (!itemsTableBody || !grandTotalSpan) return;
        let total = 0;
        let hasAnyPrice = false;
        const rows = itemsTableBody.querySelectorAll('tr');
        rows.forEach(r => {
            const qty = parseFloat(r.querySelector('.calc-qty')?.value || 0);
            const priceVal = r.querySelector('.calc-price')?.value;
            const price = parseMoneyInput(priceVal);
            if (price > 0) {
                hasAnyPrice = true;
            }
            total += (qty * price);
        });

        if (hasAnyPrice && total > 0) {
            grandTotalSpan.textContent = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(total);
        } else {
            grandTotalSpan.textContent = 'Sem valor';
        }
        checkContractBalanceLimit();
    }

    function checkContractBalanceLimit() {
        if (!contractSelect) return;
        const selectedOpt = contractSelect.options[contractSelect.selectedIndex];
        const warningDiv = document.getElementById('contract_balance_warning');
        if (!warningDiv) return;

        if (selectedOpt && selectedOpt.dataset.balance) {
            const balance = parseFloat(selectedOpt.dataset.balance);
            let currentTotal = 0;
            itemsTableBody.querySelectorAll('tr').forEach(r => {
                const qty = parseFloat(r.querySelector('.calc-qty')?.value || 0);
                const price = parseMoneyInput(r.querySelector('.calc-price')?.value);
                currentTotal += (qty * price);
            });

            if (currentTotal > balance) {
                warningDiv.classList.remove('hidden');
            } else {
                warningDiv.classList.add('hidden');
            }
        } else {
            warningDiv.classList.add('hidden');
        }
    }

    // -------------------------------------------------------------
    // 3. MÁSCARAS DE ENTRADA (CPF / CNPJ / MOEDA)
    // -------------------------------------------------------------
    document.querySelectorAll('.mask-doc').forEach(input => {
        input.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '');
            if (v.length <= 11) {
                // CPF
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            } else {
                // CNPJ
                v = v.substring(0, 14);
                v = v.replace(/^(\d{2})(\d)/, '$1.$2');
                v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
                v = v.replace(/(\d{4})(\d)/, '$1-$2');
            }
            this.value = v;
        });
    });

    // -------------------------------------------------------------
    // 4. ALTERNÂNCIA DE TIPO: MATERIAL VS SERVIÇO
    // -------------------------------------------------------------
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const typeLabelSpan = document.getElementById('type_label_span');
    if (typeRadios.length > 0 && typeLabelSpan) {
        typeRadios.forEach(radio => {
            radio.addEventListener('change', function () {
                if (this.value === 'servico') {
                    typeLabelSpan.textContent = 'Prestação de Serviços';
                } else {
                    typeLabelSpan.textContent = 'Compra de Materiais';
                }
            });
        });
    }
});
