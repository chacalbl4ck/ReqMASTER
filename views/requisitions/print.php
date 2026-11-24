<?php
declare(strict_types=1);

$companyName = $companySettings['company_name'] ?? 'Minha Empresa';
$companyTrade = $companySettings['company_trade_name'] ?? '';
$companyCnpj = $companySettings['company_cnpj'] ?? '';
$companyPhone = $companySettings['company_phone'] ?? '';
$companyEmail = $companySettings['company_email'] ?? '';
$companyAddress = $companySettings['company_address'] ?? '';
$companyLogo = $companySettings['company_logo'] ?? '';

$reqColor  = safe_hex_color($companySettings['req_primary_color'] ?? '#4338ca', '#4338ca');
$reqTint   = safe_hex_color($companySettings['req_bg_tint'] ?? '#eef2ff', '#eef2ff');
$reqBorder = safe_hex_color($companySettings['req_border_color'] ?? '#cbd5e1', '#cbd5e1');

$hasValues = !empty($requisition['has_values']) && (float)$requisition['total_amount'] > 0;
$attachments = $requisition['attachments'] ?? [];
$qrCodeUrl = $qrCodeUrl ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <?= bot_protection_meta($companySettings ?? []) ?>
    <title>Requisição <?= htmlspecialchars($requisition['code']) ?> - Ficha Oficial</title>
    <!-- html2pdf.js para exportação direta em PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <?php if (!empty($qrCodeUrl)): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <?php endif; ?>
    <style>
        @page {
            size: A4;
            margin: 12mm 15mm 12mm 15mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
            font-size: 10.5pt;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .header {
            border-bottom: 2.5px solid <?= $reqColor ?>;
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-logo {
            max-height: 55px;
            max-width: 140px;
            object-fit: contain;
        }
        .header h1 {
            margin: 0 0 2px 0;
            font-size: 16pt;
            color: #0f172a;
        }
        .header .subtitle {
            font-size: 8.5pt;
            color: #475569;
            margin: 0;
        }
        .code-box {
            text-align: right;
            border: 1px solid <?= $reqBorder ?>;
            padding: 6px 12px;
            border-radius: 6px;
            background: <?= $reqTint ?>;
            min-width: 160px;
        }
        .code-box .code {
            font-size: 13pt;
            font-weight: bold;
            color: <?= $reqColor ?>;
            letter-spacing: 0.5px;
        }
        .code-box .status {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .section-title {
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: <?= $reqColor ?>;
            border-bottom: 1.5px solid <?= $reqBorder ?>;
            padding-bottom: 3px;
            margin: 14px 0 6px 0;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px 16px;
            font-size: 9pt;
            margin-bottom: 8px;
        }
        .info-item {
            margin-bottom: 2px;
        }
        .info-item strong {
            color: #475569;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-top: 6px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            font-size: 8pt;
            text-transform: uppercase;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row td {
            font-weight: bold;
            background-color: #f8fafc;
            font-size: 9.5pt;
        }
        .box-justification {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            border-radius: 4px;
            background: #fafafa;
            font-size: 8.5pt;
            white-space: pre-wrap;
        }
        .attachments-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }
        .att-thumb {
            width: 85px;
            height: 75px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 2px;
            text-align: center;
            background: #f8fafc;
        }
        .att-thumb img {
            max-width: 100%;
            max-height: 55px;
            object-fit: cover;
        }
        .att-thumb span {
            display: block;
            font-size: 7pt;
            color: #64748b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .signatures {
            margin-top: 28px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            align-items: start;
            page-break-inside: avoid;
        }
        .signatures.signatures-2 {
            grid-template-columns: repeat(2, 1fr);
            gap: 36px;
        }
        .sig-block {
            text-align: center;
            font-size: 8pt;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        .sig-img-box {
            height: 60px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            margin-bottom: 6px;
        }
        .sig-img {
            max-height: 56px;
            max-width: 180px;
            object-fit: contain;
            display: block;
        }
        .sig-line {
            border-top: 1px solid #475569;
            padding-top: 5px;
            font-weight: bold;
            color: #0f172a;
        }
        .sig-sub {
            font-size: 7.5pt;
            color: #64748b;
            margin-top: 2px;
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <!-- Barra de Ações Superior (Não Impressa) -->
    <div class="no-print" style="background:#0f172a;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;color:#fff;margin-bottom:15px;border-radius:8px;">
        <div style="font-size:13px;">
            <strong>Ficha de Requisição:</strong> <?= htmlspecialchars($requisition['code']) ?> &bull; <?= htmlspecialchars($companyName) ?>
        </div>
        <div style="display:flex;gap:10px;">
            <button onclick="downloadPdfDoc()" id="btnPrintPdf" style="background:#e11d48;color:#fff;border:none;padding:7px 16px;font-size:12px;border-radius:6px;cursor:pointer;font-weight:bold;">
                📥 Baixar Arquivo PDF
            </button>
            <button onclick="window.print()" style="background:#4f46e5;color:#fff;border:none;padding:7px 16px;font-size:12px;border-radius:6px;cursor:pointer;font-weight:bold;">
                🖨️ Imprimir na Impressora
            </button>
        </div>
    </div>

    <!-- Conteúdo da Folha A4 Capturável -->
    <div id="printDocArea">
        <!-- Cabeçalho Oficial -->
        <div class="header">
            <div class="header-logo-area">
                <?php 
                $logoPhysical = !empty($companyLogo) ? (__DIR__ . '/../../public/' . ltrim($companyLogo, '/')) : '';
                if (!empty($companyLogo) && file_exists($logoPhysical)): 
                ?>
                    <img src="<?= asset($companyLogo) ?>" alt="Logo" class="header-logo">
                <?php endif; ?>
                <div>
                    <h1><?= strtoupper(htmlspecialchars($companyName)) ?></h1>
                    <p class="subtitle">
                        <?php if (!empty($companyCnpj)): ?>CNPJ: <?= \App\Helpers\Security::formatDocument($companyCnpj) ?> &bull; <?php endif; ?>
                        <?php if (!empty($companyPhone)): ?>Tel: <?= htmlspecialchars($companyPhone) ?> &bull; <?php endif; ?>
                        <?= htmlspecialchars($companyAddress) ?>
                    </p>
                    <p class="subtitle" style="margin-top:2px;font-weight:bold;color:<?= $reqColor ?>;">
                        FICHA DE REQUISIÇÃO DE <?= $requisition['type'] === 'servico' ? 'PRESTAÇÃO DE SERVIÇOS' : 'COMPRA DE MATERIAIS' ?>
                    </p>
                </div>
            </div>
            <div class="code-box">
                <div class="code"><?= htmlspecialchars($requisition['code']) ?></div>
                <div class="status"><?= mb_strtoupper(htmlspecialchars(statusLabel($requisition['status'])), 'UTF-8') ?></div>
                <div style="font-size:7.5pt;color:#64748b;margin-top:2px;">Emissão: <?= formatDateTime($requisition['created_at']) ?></div>
            </div>
        </div>

        <!-- 1. Dados do Solicitante -->
        <div class="section-title">1. Dados do Solicitante & Identificação</div>
        <div class="info-grid">
            <div class="info-item"><strong>Empresa Solicitante:</strong> <?= htmlspecialchars($companyName) ?></div>
            <div class="info-item"><strong>Departamento / Setor:</strong> <?= htmlspecialchars($requisition['department']) ?></div>
            <div class="info-item"><strong>Requisitante Responsável:</strong> <?= htmlspecialchars($requisition['requester_name']) ?></div>
            <div class="info-item"><strong>E-mail do Requisitante:</strong> <?= htmlspecialchars($requisition['requester_email'] ?? '-') ?></div>
            <div class="info-item"><strong>Título / Finalidade:</strong> <?= htmlspecialchars($requisition['title']) ?></div>
            <div class="info-item"><strong>Prioridade:</strong> <?= strtoupper(htmlspecialchars($requisition['priority'])) ?> &bull; <strong>Prazo:</strong> <?= formatDate($requisition['deadline']) ?></div>
        </div>

        <!-- 2. Para Quem Solicita (Fornecedor / Prestador) -->
        <div class="section-title">2. Para Quem Solicita (Fornecedor / Prestador) & Contrato</div>
        <div class="info-grid">
            <div class="info-item"><strong>Razão Social / Nome:</strong> <?= htmlspecialchars($requisition['supplier_name']) ?></div>
            <div class="info-item"><strong>CNPJ / CPF:</strong> <?= \App\Helpers\Security::formatDocument($requisition['supplier_document']) ?></div>
            <div class="info-item"><strong>Telefone / WhatsApp:</strong> <?= htmlspecialchars($requisition['supplier_phone'] ?? '-') ?></div>
            <div class="info-item"><strong>E-mail:</strong> <?= htmlspecialchars($requisition['supplier_email'] ?? '-') ?></div>
            <div class="info-item" style="grid-column: span 2;">
                <strong>Contrato Vinculado:</strong> 
                <?php if (!empty($requisition['contract_number'])): ?>
                    <strong><?= htmlspecialchars($requisition['contract_number']) ?></strong> - <?= htmlspecialchars($requisition['contract_title']) ?>
                    <?php if ($hasValues): ?>
                        (Saldo Remanescente: <?= formatMoney($requisition['contract_balance']) ?>)
                    <?php endif; ?>
                <?php else: ?>
                    Não vinculado a contrato prévio (Compra/Serviço Avulso)
                <?php endif; ?>
            </div>
            <?php if (!empty($requisition['supplier_bank_info'])): ?>
                <div class="info-item" style="grid-column: span 2;">
                    <strong>Dados Bancários / Pix para Pagamento:</strong> <?= htmlspecialchars($requisition['supplier_bank_info']) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. Itens / Serviços Solicitados -->
        <div class="section-title">3. Itens / Serviços Solicitados</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 25px;" class="text-center">#</th>
                    <th>Descrição do Item / Especificações Técnicas</th>
                    <th style="width: 45px;" class="text-center">Unid.</th>
                    <th style="width: 55px;" class="text-right">Qtd</th>
                    <?php if ($hasValues): ?>
                        <th style="width: 85px;" class="text-right">Preço Unit.</th>
                        <th style="width: 95px;" class="text-right">Subtotal</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requisition['items'] as $i => $item): ?>
                    <tr>
                        <td class="text-center"><?= $i + 1 ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['description']) ?></strong>
                            <?php if (!empty($item['technical_specs'])): ?>
                                <div style="font-size: 7.5pt; color: #475569; margin-top: 1px;">
                                    Especificações: <?= htmlspecialchars($item['technical_specs']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= strtoupper(htmlspecialchars($item['unit'])) ?></td>
                        <td class="text-right"><?= number_format((float)$item['quantity'], 2, ',', '.') ?></td>
                        <?php if ($hasValues): ?>
                            <td class="text-right"><?= formatMoney($item['unit_price']) ?></td>
                            <td class="text-right"><?= formatMoney($item['total_price']) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($hasValues): ?>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" class="text-right">VALOR TOTAL DA REQUISIÇÃO:</td>
                        <td class="text-right" style="color:#1e293b;"><?= formatMoney($requisition['total_amount']) ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>

        <!-- 4. Justificativa -->
        <div class="section-title">4. Justificativa da Aquisição / Contratação</div>
        <div class="box-justification"><?= htmlspecialchars($requisition['justification']) ?></div>

        <!-- 5. Fotos e Anexos (se houver) -->
        <?php if (!empty($attachments)): ?>
            <div class="section-title">5. Fotos & Documentos Anexados</div>
            <div class="attachments-preview">
                <?php foreach ($attachments as $att): ?>
                    <?php if (str_starts_with($att['mime_type'], 'image/')): ?>
                        <div class="att-thumb">
                            <img src="<?= asset($att['filename']) ?>" alt="Foto">
                            <span><?= htmlspecialchars($att['original_name']) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="att-thumb">
                            <div style="font-size:20px;color:#e11d48;padding-top:10px;">📄</div>
                            <span><?= htmlspecialchars($att['original_name']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 6. Assinaturas Digitais / Físicas -->
        <?php $isAdminRequester = (($requisition['requester_role'] ?? '') === 'admin'); ?>
        <div class="signatures <?= $isAdminRequester ? 'signatures-2' : '' ?>">
            <!-- Solicitante -->
            <div class="sig-block">
                <div class="sig-img-box">
                    <?php if (!empty($requisition['requester_signature'])): ?>
                        <img src="<?= $requisition['requester_signature'] ?>" alt="Assinatura" class="sig-img">
                    <?php endif; ?>
                </div>
                <div class="sig-line"><?= htmlspecialchars($requisition['requester_name']) ?></div>
                <div class="sig-sub">
                    <?= $isAdminRequester ? 'Solicitante / Administrador' : 'Solicitante' ?> (<?= htmlspecialchars($requisition['department']) ?>)
                </div>
            </div>

            <?php if (!$isAdminRequester): ?>
                <!-- Aprovador (apenas quando solicitante não é Admin) -->
                <div class="sig-block">
                    <div class="sig-img-box">
                        <?php if (!empty($requisition['approver_signature'])): ?>
                            <img src="<?= $requisition['approver_signature'] ?>" alt="Assinatura" class="sig-img">
                        <?php endif; ?>
                    </div>
                    <div class="sig-line"><?= htmlspecialchars($requisition['approver_name'] ?? 'Gestor de Compras / Diretoria') ?></div>
                    <div class="sig-sub">
                        <?= !empty($requisition['approved_at']) ? 'Aprovado em ' . formatDate($requisition['approved_at']) : 'Aprovador Responsável' ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Fornecedor -->
            <div class="sig-block">
                <div class="sig-img-box"></div>
                <div class="sig-line"><?= htmlspecialchars($requisition['supplier_name']) ?></div>
                <div class="sig-sub">Fornecedor / Prestador Contratado</div>
            </div>
        </div>

        <?php if (!empty($qrCodeUrl)): ?>
            <!-- 7. QR Code de Visualização Online (Sistema Hospedado na Web) -->
            <div style="margin-top:22px;padding:10px 14px;border:1px solid <?= $reqBorder ?>;border-radius:8px;background:<?= $reqTint ?>;display:flex;align-items:center;gap:14px;page-break-inside:avoid;">
                <div id="printQrBox" style="background:#fff;padding:4px;border:1px solid #e2e8f0;border-radius:6px;flex-shrink:0;"></div>
                <div style="font-size:8pt;color:#334155;line-height:1.45;">
                    <strong style="color:<?= $reqColor ?>;font-size:8.5pt;display:block;margin-bottom:2px;">
                        VERIFICAÇÃO & VISUALIZAÇÃO ONLINE
                    </strong>
                    Aponte a câmera do celular para o QR Code ao lado para conferir a autenticidade e os detalhes atualizados desta requisição online:<br>
                    <span style="font-family:monospace;font-size:7.2pt;color:#64748b;word-break:break-all;"><?= htmlspecialchars($qrCodeUrl) ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function downloadPdfDoc() {
            const btn = document.getElementById('btnPrintPdf');
            btn.innerHTML = '⏳ Gerando PDF...';

            const element = document.getElementById('printDocArea');
            const opt = {
                margin:       8,
                filename:     'Requisicao_<?= $requisition['code'] ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                btn.innerHTML = '📥 Baixar Arquivo PDF';
            }).catch(err => {
                btn.innerHTML = '📥 Baixar Arquivo PDF';
                window.print();
            });
        }

        window.addEventListener('DOMContentLoaded', function () {
            <?php if (!empty($qrCodeUrl)): ?>
            const qrEl = document.getElementById('printQrBox');
            if (qrEl && typeof QRCode !== 'undefined') {
                new QRCode(qrEl, {
                    text: <?= json_encode($qrCodeUrl) ?>,
                    width: 68,
                    height: 68,
                    colorDark: "#0f172a",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
            <?php endif; ?>

            const params = new URLSearchParams(window.location.search);
            if (params.get('auto_pdf') === '1') {
                setTimeout(downloadPdfDoc, 700);
            }
        });
    </script>
</body>
</html>
