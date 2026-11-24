<?php
declare(strict_types=1);

$companyName = $companySettings['company_name'] ?? 'Minha Empresa';
$companyLogo = $companySettings['company_logo'] ?? '';
$companyCnpj = $companySettings['company_cnpj'] ?? '';
$companyPhone = $companySettings['company_phone'] ?? '';
$companyAddress = $companySettings['company_address'] ?? '';
$hasValues = !empty($requisition['has_values']);
$reqColor = safe_hex_color($companySettings['req_primary_color'] ?? '#4338ca', '#4338ca');
$reqTint  = safe_hex_color($companySettings['req_bg_tint'] ?? '#eef2ff', '#eef2ff');
$reqBorder = safe_hex_color($companySettings['req_border_color'] ?? '#cbd5e1', '#cbd5e1');
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= bot_protection_meta($companySettings ?? []) ?>
    <title>Requisição <?= htmlspecialchars($requisition['code']) ?> - <?= htmlspecialchars($companyName) ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?= theme_css_vars($companySettings ?? []) ?>
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="min-h-full py-8 px-4 text-slate-800">
    <div class="max-w-4xl mx-auto bg-white rounded-3xl shadow-xl border overflow-hidden" style="border-color: <?= $reqBorder ?>;">
        <!-- Cabeçalho Institucional -->
        <div class="p-6 sm:p-8 border-b-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4" style="border-bottom-color: <?= $reqColor ?>; background-color: <?= $reqTint ?>;">
            <div class="flex items-center gap-4">
                <?php
                $logoPhysical = !empty($companyLogo) ? (__DIR__ . '/../../public/' . ltrim($companyLogo, '/')) : '';
                if (!empty($companyLogo) && file_exists($logoPhysical)):
                ?>
                    <img src="<?= asset($companyLogo) ?>" alt="Logo" class="max-h-14 max-w-[160px] object-contain">
                <?php endif; ?>
                <div>
                    <h1 class="text-lg sm:text-xl font-black text-slate-900 uppercase"><?= htmlspecialchars($companyName) ?></h1>
                    <p class="text-xs text-slate-600">
                        <?php if (!empty($companyCnpj)): ?>CNPJ: <?= \App\Helpers\Security::formatDocument($companyCnpj) ?> &bull; <?php endif; ?>
                        <?php if (!empty($companyPhone)): ?>Tel: <?= htmlspecialchars($companyPhone) ?><?php endif; ?>
                    </p>
                    <span class="inline-block mt-1 text-[11px] font-bold uppercase tracking-wider" style="color: <?= $reqColor ?>;">
                        Visualização Oficial de Requisição Online
                    </span>
                </div>
            </div>
            <div class="text-left sm:text-right">
                <div class="text-xl font-mono font-black" style="color: <?= $reqColor ?>;"><?= htmlspecialchars($requisition['code']) ?></div>
                <div class="mt-1"><?= statusBadge($requisition['status']) ?></div>
                <div class="text-[11px] text-slate-500 mt-1">Emissão: <?= formatDateTime($requisition['created_at']) ?></div>
            </div>
        </div>

        <div class="p-6 sm:p-8 space-y-6">
            <!-- Título e Informações Principais -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs bg-slate-50 p-4 rounded-2xl border" style="border-color: <?= $reqBorder ?>;">
                <div><span class="text-slate-400 block uppercase font-semibold">Título / Objeto</span><strong class="text-slate-800 text-sm"><?= htmlspecialchars($requisition['title']) ?></strong></div>
                <div><span class="text-slate-400 block uppercase font-semibold">Tipo & Prioridade</span><strong class="text-slate-800 uppercase"><?= $requisition['type'] === 'servico' ? 'Prestação de Serviços' : 'Compra de Materiais' ?></strong> &bull; <?= priorityBadge($requisition['priority']) ?></div>
                <div><span class="text-slate-400 block uppercase font-semibold">Solicitante</span><strong class="text-slate-800"><?= htmlspecialchars($requisition['requester_name']) ?></strong> (<?= htmlspecialchars($requisition['department']) ?>)</div>
                <div><span class="text-slate-400 block uppercase font-semibold">Fornecedor / Prestador</span><strong class="text-slate-800"><?= htmlspecialchars($requisition['supplier_name']) ?></strong></div>
            </div>

            <!-- Justificativa -->
            <div>
                <h2 class="text-xs font-bold uppercase tracking-wider mb-2" style="color: <?= $reqColor ?>;">Justificativa</h2>
                <p class="text-sm text-slate-700 bg-slate-50 p-4 rounded-xl border leading-relaxed" style="border-color: <?= $reqBorder ?>;">
                    <?= nl2br(htmlspecialchars($requisition['justification'])) ?>
                </p>
            </div>

            <!-- Itens -->
            <div>
                <h2 class="text-xs font-bold uppercase tracking-wider mb-2" style="color: <?= $reqColor ?>;">Itens / Serviços Solicitados</h2>
                <div class="overflow-x-auto rounded-xl border" style="border-color: <?= $reqBorder ?>;">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr style="background-color: <?= $reqTint ?>; color: <?= $reqColor ?>;">
                                <th class="py-2.5 px-3 font-bold">#</th>
                                <th class="py-2.5 px-3 font-bold">Descrição</th>
                                <th class="py-2.5 px-3 text-center font-bold">Unid.</th>
                                <th class="py-2.5 px-3 text-right font-bold">Qtd</th>
                                <?php if ($hasValues): ?>
                                    <th class="py-2.5 px-3 text-right font-bold">Unitário</th>
                                    <th class="py-2.5 px-3 text-right font-bold">Subtotal</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($requisition['items'] as $idx => $item): ?>
                                <tr>
                                    <td class="py-2.5 px-3 text-slate-400"><?= $idx + 1 ?></td>
                                    <td class="py-2.5 px-3 font-medium text-slate-800">
                                        <?= htmlspecialchars($item['description']) ?>
                                        <?php if (!empty($item['technical_specs'])): ?>
                                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($item['technical_specs']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2.5 px-3 text-center uppercase"><?= htmlspecialchars($item['unit']) ?></td>
                                    <td class="py-2.5 px-3 text-right font-semibold"><?= number_format((float)$item['quantity'], 2, ',', '.') ?></td>
                                    <?php if ($hasValues): ?>
                                        <td class="py-2.5 px-3 text-right"><?= formatMoney($item['unit_price']) ?></td>
                                        <td class="py-2.5 px-3 text-right font-bold"><?= formatMoney($item['total_price']) ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if ($hasValues): ?>
                            <tfoot>
                                <tr style="background-color: <?= $reqTint ?>;">
                                    <td colspan="5" class="py-2.5 px-3 text-right font-bold text-slate-700">TOTAL GERAL:</td>
                                    <td class="py-2.5 px-3 text-right font-black text-sm" style="color: <?= $reqColor ?>;"><?= formatMoney($requisition['total_amount']) ?></td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Assinaturas Digitais -->
            <?php $isAdminRequester = (($requisition['requester_role'] ?? '') === 'admin'); ?>
            <div class="grid grid-cols-1 <?= (!$isAdminRequester || !empty($requisition['approver_signature'])) ? 'sm:grid-cols-2' : '' ?> gap-4 pt-2 items-stretch">
                <div class="p-4 rounded-2xl border bg-slate-50 text-center flex flex-col justify-between space-y-2" style="border-color: <?= $reqBorder ?>;">
                    <div class="text-[11px] font-bold uppercase text-slate-500">Assinatura do Solicitante <?= $isAdminRequester ? '(Admin)' : '' ?></div>
                    <div class="h-24 flex items-center justify-center">
                        <?php if (!empty($requisition['requester_signature'])): ?>
                            <img src="<?= htmlspecialchars(signature_image_src($requisition['requester_signature'])) ?>" alt="Assinatura Solicitante" class="max-h-24 mx-auto object-contain">
                        <?php else: ?>
                            <div class="text-xs text-slate-400 italic">Sem assinatura digital registrada</div>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($requisition['requester_name']) ?></div>
                </div>

                <?php if (!$isAdminRequester || !empty($requisition['approver_signature'])): ?>
                    <div class="p-4 rounded-2xl border bg-slate-50 text-center flex flex-col justify-between space-y-2" style="border-color: <?= $reqBorder ?>;">
                        <div class="text-[11px] font-bold uppercase text-slate-500">Assinatura / Aprovação do Gestor</div>
                        <div class="h-24 flex items-center justify-center">
                            <?php if (!empty($requisition['approver_signature'])): ?>
                                <img src="<?= htmlspecialchars(signature_image_src($requisition['approver_signature'])) ?>" alt="Assinatura Aprovador" class="max-h-24 mx-auto object-contain">
                            <?php else: ?>
                                <div class="text-xs text-slate-400 italic"><?= $requisition['status'] === 'pendente' ? 'Aguardando aprovação' : 'Sem assinatura digital' ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($requisition['approver_name'] ?? 'Gestor / Aprovador') ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-slate-50 px-6 py-4 border-t text-center text-[11px] text-slate-400" style="border-color: <?= $reqBorder ?>;">
            Documento autenticado eletronicamente &bull; <?= htmlspecialchars($companyName) ?> &bull; Token validado em <?= date('d/m/Y H:i') ?>
        </div>
    </div>
</body>
</html>
