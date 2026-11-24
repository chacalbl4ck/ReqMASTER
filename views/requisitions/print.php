<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Requisição <?= htmlspecialchars($requisition['code']) ?> - Ficha Oficial</title>
    <style>
        @page {
            size: A4;
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
            font-size: 11pt;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .header {
            border-bottom: 2px solid #1e293b;
            padding-bottom: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 18pt;
            color: #0f172a;
        }
        .header .subtitle {
            font-size: 9pt;
            color: #64748b;
            margin: 0;
        }
        .code-box {
            text-align: right;
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            border-radius: 6px;
            background: #f8fafc;
        }
        .code-box .code {
            font-size: 14pt;
            font-weight: bold;
            color: #4338ca;
            letter-spacing: 1px;
        }
        .code-box .status {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #334155;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin: 16px 0 8px 0;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px 16px;
            font-size: 9.5pt;
            margin-bottom: 12px;
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
            font-size: 9pt;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            font-size: 8.5pt;
            text-transform: uppercase;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row td {
            font-weight: bold;
            background-color: #f8fafc;
            font-size: 10pt;
        }
        .box-justification {
            border: 1px solid #e2e8f0;
            padding: 10px;
            border-radius: 4px;
            background: #fafafa;
            font-size: 9pt;
            white-space: pre-wrap;
        }
        .signatures {
            margin-top: 40px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            page-break-inside: avoid;
        }
        .sig-block {
            text-align: center;
            font-size: 8.5pt;
        }
        .sig-line {
            border-top: 1px solid #475569;
            margin-top: 45px;
            padding-top: 4px;
            font-weight: bold;
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <!-- Botão de Impressão Flutuante -->
    <div class="no-print" style="background:#1e293b;padding:12px;text-align:center;color:#fff;margin-bottom:20px;">
        <button onclick="window.print()" style="background:#4f46e5;color:#fff;border:none;padding:8px 20px;font-size:14px;border-radius:6px;cursor:pointer;font-weight:bold;">
            🖨️ Imprimir Requisição (ou Salvar em PDF)
        </button>
        <span style="margin-left:15px;font-size:12px;color:#94a3b8;">Dica: Selecione "Salvar como PDF" na caixa de impressão para gerar um PDF oficial.</span>
    </div>

    <!-- Cabeçalho Oficial -->
    <div class="header">
        <div>
            <h1>FICHA DE REQUISIÇÃO INTERNA</h1>
            <p class="subtitle"><?= APP_NAME ?> &bull; Sistema Integrado de Suprimentos & Contratos</p>
            <p class="subtitle" style="margin-top:4px;">Tipo: <strong><?= $requisition['type'] === 'servico' ? 'PRESTAÇÃO DE SERVIÇOS' : 'COMPRA DE MATERIAIS' ?></strong></p>
        </div>
        <div class="code-box">
            <div class="code"><?= htmlspecialchars($requisition['code']) ?></div>
            <div class="status">STATUS: <?= strtoupper(htmlspecialchars($requisition['status'])) ?></div>
            <div style="font-size:8pt;color:#64748b;margin-top:2px;">Emissão: <?= formatDateTime($requisition['created_at']) ?></div>
        </div>
    </div>

    <!-- Informações do Solicitante e Atendimento -->
    <div class="section-title">1. Dados do Solicitante & Requisição</div>
    <div class="info-grid">
        <div class="info-item"><strong>Título:</strong> <?= htmlspecialchars($requisition['title']) ?></div>
        <div class="info-item"><strong>Departamento/Setor:</strong> <?= htmlspecialchars($requisition['department']) ?></div>
        <div class="info-item"><strong>Solicitante:</strong> <?= htmlspecialchars($requisition['requester_name']) ?> (<?= htmlspecialchars($requisition['requester_email'] ?? '') ?>)</div>
        <div class="info-item"><strong>Prioridade:</strong> <?= strtoupper(htmlspecialchars($requisition['priority'])) ?></div>
        <div class="info-item"><strong>Prazo Desejado:</strong> <?= formatDate($requisition['deadline']) ?></div>
        <div class="info-item"><strong>Data Aprovação:</strong> <?= formatDateTime($requisition['approved_at']) ?></div>
    </div>

    <!-- Destinatário / Para Quem Solicita & Contrato -->
    <div class="section-title">2. Para Quem Solicita (Fornecedor / Prestador) & Contrato</div>
    <div class="info-grid">
        <div class="info-item"><strong>Razão Social / Nome:</strong> <?= htmlspecialchars($requisition['supplier_name']) ?></div>
        <div class="info-item"><strong>CNPJ / CPF:</strong> <?= \App\Helpers\Security::formatDocument($requisition['supplier_document']) ?></div>
        <div class="info-item"><strong>Telefone / Contato:</strong> <?= htmlspecialchars($requisition['supplier_phone'] ?? '-') ?></div>
        <div class="info-item"><strong>E-mail:</strong> <?= htmlspecialchars($requisition['supplier_email'] ?? '-') ?></div>
        <div class="info-item" style="grid-column: span 2;">
            <strong>Contrato Vinculado:</strong> 
            <?php if (!empty($requisition['contract_number'])): ?>
                <strong><?= htmlspecialchars($requisition['contract_number']) ?></strong> - <?= htmlspecialchars($requisition['contract_title']) ?>
                (Teto: <?= formatMoney($requisition['contract_total_value']) ?> &bull; Saldo Remanescente: <?= formatMoney($requisition['contract_balance']) ?>)
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

    <!-- Itens Requisitados -->
    <div class="section-title">3. Itens / Serviços Solicitados</div>
    <table>
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">#</th>
                <th>Descrição do Item / Especificações Técnicas</th>
                <th style="width: 50px;" class="text-center">Unid.</th>
                <th style="width: 60px;" class="text-right">Qtd</th>
                <th style="width: 90px;" class="text-right">Preço Unit.</th>
                <th style="width: 100px;" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requisition['items'] as $i => $item): ?>
                <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($item['description']) ?></strong>
                        <?php if (!empty($item['technical_specs'])): ?>
                            <div style="font-size: 8pt; color: #475569; margin-top: 2px;">
                                Obs: <?= htmlspecialchars($item['technical_specs']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= strtoupper(htmlspecialchars($item['unit'])) ?></td>
                    <td class="text-right"><?= number_format((float)$item['quantity'], 2, ',', '.') ?></td>
                    <td class="text-right"><?= formatMoney($item['unit_price']) ?></td>
                    <td class="text-right"><?= formatMoney($item['total_price']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="text-right">VALOR TOTAL DA REQUISIÇÃO:</td>
                <td class="text-right" style="color:#1e293b;"><?= formatMoney($requisition['total_amount']) ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Justificativa -->
    <div class="section-title">4. Justificativa da Aquisição / Contratação</div>
    <div class="box-justification"><?= htmlspecialchars($requisition['justification']) ?></div>

    <!-- Assinaturas -->
    <div class="signatures">
        <div class="sig-block">
            <div class="sig-line"><?= htmlspecialchars($requisition['requester_name']) ?></div>
            <div>Solicitante (<?= htmlspecialchars($requisition['department']) ?>)</div>
        </div>
        <div class="sig-block">
            <div class="sig-line"><?= htmlspecialchars($requisition['approver_name'] ?? 'Gestor de Compras / Diretoria') ?></div>
            <div>Aprovador Responsável</div>
        </div>
        <div class="sig-block">
            <div class="sig-line"><?= htmlspecialchars($requisition['supplier_name']) ?></div>
            <div>Fornecedor / Prestador de Serviço</div>
        </div>
    </div>
</body>
</html>
