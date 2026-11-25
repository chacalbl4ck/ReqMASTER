<?php
declare(strict_types=1);
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fas fa-gauge-high text-indigo-600"></i> Controle de Quilometragem de Veículos
            </h1>
            <p class="text-xs sm:text-sm text-slate-500">Registre a quilometragem atual com foto do odômetro para acompanhar as manutenções preventivas por Km percorrido.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= url('produtos') ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-sm transition">
                <i class="fas fa-boxes-stacked mr-1"></i> Produtos & Veículos
            </a>
        </div>
    </div>

    <!-- Informativo de Acompanhamento -->
    <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-200 text-xs text-indigo-950 flex items-start gap-3">
        <i class="fas fa-circle-info text-indigo-600 text-base mt-0.5 shrink-0"></i>
        <div>
            <strong class="block font-bold">Acompanhamento de Manutenção por Quilometragem</strong>
            <p class="mt-0.5 text-indigo-900">
                Para veículos configurados com manutenção por Km percorrido, registre o odômetro regularmente (recomendado anexar foto do painel). Quando a distância percorrida desde a última manutenção atingir o intervalo configurado, o sistema emitirá alerta automático no Painel Inicial.
            </p>
        </div>
    </div>

    <?php if (empty($vehicles)): ?>
        <div class="bg-white rounded-3xl p-10 border border-slate-200 text-center space-y-3">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-2xl">
                <i class="fas fa-car"></i>
            </div>
            <h2 class="text-base font-bold text-slate-800">Nenhum veículo cadastrado ainda</h2>
            <p class="text-xs text-slate-500 max-w-md mx-auto">
                Vá em <strong>Produtos</strong>, cadastre um novo item e marque a opção <strong>"Este produto é um Veículo?"</strong> para habilitar o controle de placa, chassi, subprodutos e quilometragem.
            </p>
            <a href="<?= url('produtos') ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-sm transition">
                <i class="fas fa-plus"></i> Ir para Cadastro de Produtos / Veículos
            </a>
        </div>
    <?php else: ?>
        <!-- Resumo dos Veículos -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($vehicles as $v): ?>
                <?php
                    $curKm = $v['current_km'] !== null ? (int)$v['current_km'] : null;
                    $lastKm = (int)($v['last_maintenance_km'] ?? 0);
                    $intKm = !empty($v['maintenance_interval_km']) ? (int)$v['maintenance_interval_km'] : null;
                    $driven = $v['km_since_maintenance'] !== null ? (int)$v['km_since_maintenance'] : null;
                    $overdue = !empty($v['is_km_overdue']);
                    $remaining = ($intKm !== null && $driven !== null) ? ($intKm - $driven) : null;
                ?>
                <div class="bg-white rounded-2xl p-4 border <?= $overdue ? 'border-rose-300 bg-rose-50/30' : 'border-slate-200/80' ?> shadow-sm flex flex-col justify-between gap-3">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <a href="<?= url('produtos/ver?id=' . (int)$v['id']) ?>" class="font-bold text-sm text-slate-900 hover:text-indigo-700">
                                    <?= htmlspecialchars($v['name']) ?>
                                </a>
                                <div class="text-[11px] font-mono text-slate-500 mt-0.5">
                                    <?= !empty($v['license_plate']) ? 'Placa: <strong>' . htmlspecialchars($v['license_plate']) . '</strong>' : 'Sem placa' ?>
                                    <?= !empty($v['chassis']) ? ' &bull; Chassi: ' . htmlspecialchars($v['chassis']) : '' ?>
                                </div>
                            </div>
                            <?php if ($overdue): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 shrink-0">
                                    Manutenção Atingida
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mt-3 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase block">Km Atual</span>
                                <strong class="text-slate-900 font-mono">
                                    <?= $curKm !== null ? number_format($curKm, 0, ',', '.') . ' km' : 'Não inf.' ?>
                                </strong>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase block">Meta Preventiva</span>
                                <strong class="<?= $overdue ? 'text-rose-700' : 'text-indigo-700' ?> font-mono">
                                    <?php if ($intKm !== null): ?>
                                        <?= number_format($intKm, 0, ',', '.') ?> km
                                    <?php else: ?>
                                        Não definida
                                    <?php endif; ?>
                                </strong>
                            </div>
                        </div>

                        <?php if ($intKm !== null && $driven !== null): ?>
                            <div class="text-[11px] mt-2 <?= $overdue ? 'text-rose-700 font-bold' : 'text-slate-600' ?>">
                                Rodados desde última manutenção (<?= number_format($lastKm, 0, ',', '.') ?> km): <strong><?= number_format($driven, 0, ',', '.') ?> km</strong>
                                <?php if ($overdue): ?>
                                    (excedido em <?= number_format(abs((int)$remaining), 0, ',', '.') ?> km)
                                <?php else: ?>
                                    (faltam <?= number_format((int)$remaining, 0, ',', '.') ?> km)
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[11px]">
                        <button type="button" onclick="selectVehicleForMileage(<?= (int)$v['id'] ?>, <?= $curKm !== null ? $curKm : 'null' ?>)" class="font-bold text-indigo-600 hover:underline">
                            <i class="fas fa-plus-circle mr-1"></i> Lançar Km neste Veículo
                        </button>
                        <?php if ($overdue): ?>
                            <a href="<?= url('requisicoes/nova?type=servico&repair=1&maintenance_type=preventiva&product_id=' . (int)$v['id']) ?>" class="px-2.5 py-1 rounded-lg bg-rose-600 text-white font-bold">
                                Abrir OS
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulário de Lançamento de Quilometragem -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm space-y-4 h-fit">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <i class="fas fa-camera text-indigo-600"></i> Novo Registro de Odômetro
                </h2>

                <form method="POST" action="<?= url('veiculos/quilometragem/salvar') ?>" enctype="multipart/form-data" class="space-y-4">
                    <?= csrf_field() ?>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Veículo (Placa / Nome) <span class="text-rose-500">*</span></label>
                        <select name="product_id" id="mileageVehicleSelect" required onchange="updateKmHint()" class="w-full text-sm rounded-xl">
                            <option value="">-- Selecione o Veículo pela Placa --</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= (int)$v['id'] ?>"
                                        data-current-km="<?= $v['current_km'] !== null ? (int)$v['current_km'] : '' ?>"
                                        <?= $selectedProductId === (int)$v['id'] ? 'selected' : '' ?>>
                                    <?= !empty($v['license_plate']) ? '[' . htmlspecialchars($v['license_plate']) . '] ' : '[Sem Placa] ' ?><?= htmlspecialchars($v['name']) ?><?= $v['current_km'] !== null ? ' • ' . number_format((int)$v['current_km'], 0, ',', '.') . ' km' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p id="currentKmHint" class="text-[11px] text-indigo-600 font-semibold mt-1 hidden"></p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Quilometragem Atual (Km) <span class="text-rose-500">*</span></label>
                            <input type="number" name="mileage_km" id="mileageKmInput" min="0" required class="w-full text-sm rounded-xl font-mono" placeholder="Ex: 52340">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Litros Abastecidos (Opcional)</label>
                            <input type="number" name="fuel_liters" step="0.01" min="0" class="w-full text-sm rounded-xl font-mono" placeholder="Ex: 42.50 (p/ Km/L)">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Foto do Odômetro (Opcional / Recomendado)</label>
                        <input type="file" name="odometer_photo" accept="image/*" capture="environment" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 cursor-pointer">
                        <p class="text-[11px] text-slate-400 mt-1">Anexe foto do painel/odômetro (PNG, JPG, WEBP).</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Data e Hora da Leitura</label>
                        <input type="datetime-local" name="recorded_at" value="<?= date('Y-m-d\TH:i') ?>" class="w-full text-sm rounded-xl">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Observações</label>
                        <textarea name="notes" rows="2" class="w-full text-sm rounded-xl" placeholder="Ex: Abastecimento, retorno de viagem, inspeção semanal..."></textarea>
                    </div>

                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs sm:text-sm font-bold shadow-md transition">
                        <i class="fas fa-save mr-1.5"></i> Registrar Quilometragem
                    </button>
                </form>
            </div>

            <!-- Histórico de Quilometragens -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Histórico de Leituras de Odômetro</h3>
                        <p class="text-xs text-slate-400">Acompanhe todas as quilometragens registradas e fotos anexadas.</p>
                    </div>
                    <?php if ($selectedProductId > 0): ?>
                        <a href="<?= url('veiculos/quilometragem') ?>" class="text-xs font-semibold text-indigo-600 hover:underline">
                            Mostrar todos os veículos
                        </a>
                    <?php endif; ?>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase">
                                <th class="py-3 px-4">Veículo</th>
                                <th class="py-3 px-4 text-right">Quilometragem</th>
                                <th class="py-3 px-4 text-center">Foto Odômetro</th>
                                <th class="py-3 px-4">Registrado por</th>
                                <th class="py-3 px-4">Data / Hora</th>
                                <?php if (!empty($isAdmin)): ?>
                                    <th class="py-3 px-3 text-center"></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($mileageLogs)): ?>
                                <tr>
                                    <td colspan="<?= !empty($isAdmin) ? 6 : 5 ?>" class="py-10 text-center text-slate-400">
                                        Nenhum registro de quilometragem lançado ainda.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($mileageLogs as $ml): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-3 px-4">
                                            <a href="<?= url('produtos/ver?id=' . (int)$ml['product_id']) ?>" class="font-bold text-slate-900 hover:text-indigo-700 block">
                                                <?= htmlspecialchars($ml['product_name']) ?>
                                            </a>
                                            <?php if (!empty($ml['license_plate'])): ?>
                                                <span class="font-mono text-[11px] text-slate-500"><?= htmlspecialchars($ml['license_plate']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($ml['notes'])): ?>
                                                <div class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars($ml['notes']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4 text-right font-mono font-bold text-indigo-700">
                                            <?= number_format((int)$ml['mileage_km'], 0, ',', '.') ?> km
                                            <?php if (!empty($ml['fuel_liters'])): ?>
                                                <span class="block text-[10px] text-emerald-700 font-semibold">+<?= number_format((float)$ml['fuel_liters'], 2, ',', '.') ?> L</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <?php if (!empty($ml['odometer_photo']) && file_exists(__DIR__ . '/../../public/' . $ml['odometer_photo'])): ?>
                                                <a href="<?= asset($ml['odometer_photo']) ?>" target="_blank" class="inline-block" title="Ampliar Foto do Odômetro">
                                                    <img src="<?= asset($ml['odometer_photo']) ?>" alt="Odômetro" class="w-14 h-10 object-cover rounded-lg border border-slate-200 mx-auto hover:scale-105 transition">
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-300">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4 text-slate-700">
                                            <?= htmlspecialchars($ml['user_name'] ?? 'Sistema') ?>
                                        </td>
                                        <td class="py-3 px-4 text-slate-500 whitespace-nowrap">
                                            <?= formatDateTime($ml['recorded_at']) ?>
                                        </td>
                                        <?php if (!empty($isAdmin)): ?>
                                            <td class="py-3 px-3 text-center">
                                                <form method="POST" action="<?= url('veiculos/quilometragem/excluir') ?>" onsubmit="return confirm('Confirma excluir este registro de quilometragem?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="log_id" value="<?= (int)$ml['id'] ?>">
                                                    <button type="submit" class="text-slate-400 hover:text-rose-600 p-1 transition" title="Excluir Registro">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function updateKmHint() {
        const sel = document.getElementById('mileageVehicleSelect');
        const hint = document.getElementById('currentKmHint');
        if (!sel || !hint) return;
        const opt = sel.options[sel.selectedIndex];
        const km = opt ? opt.dataset.currentKm : '';
        if (km !== '' && km !== undefined) {
            hint.textContent = 'Último Km registrado: ' + parseInt(km, 10).toLocaleString('pt-BR') + ' km';
            hint.classList.remove('hidden');
        } else {
            hint.classList.add('hidden');
        }
    }

    function selectVehicleForMileage(id, currentKm) {
        const sel = document.getElementById('mileageVehicleSelect');
        if (sel) {
            sel.value = String(id);
            updateKmHint();
            document.getElementById('mileageKmInput')?.focus();
        }
    }

    document.addEventListener('DOMContentLoaded', updateKmHint);
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
