<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Security;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\Product;
use App\Models\ServiceCatalog;
use App\Services\MailService;
use App\Services\CertificateSignService;

class RequisitionController
{
    private Requisition $reqModel;
    private Supplier $supplierModel;
    private Contract $contractModel;
    private ActivityLog $logModel;
    private Setting $settingModel;
    private MailService $mailService;
    private CertificateSignService $certService;

    public function __construct()
    {
        if (current_route() !== 'requisicoes/publica') {
            Security::requireLogin();
        }
        $this->reqModel = new Requisition();
        $this->supplierModel = new Supplier();
        $this->contractModel = new Contract();
        $this->logModel = new ActivityLog();
        $this->settingModel = new Setting();
        $this->mailService = new MailService();
        $this->certService = new CertificateSignService();
    }

    public function index(): void
    {
        $filters = [
            'status'      => $_GET['status'] ?? null,
            'type'        => $_GET['type'] ?? null,
            'supplier_id' => $_GET['supplier_id'] ?? null,
            'contract_id' => $_GET['contract_id'] ?? null,
            'priority'    => $_GET['priority'] ?? null,
            'search'      => trim($_GET['search'] ?? ''),
            'date_from'   => $_GET['date_from'] ?? null,
            'date_to'     => $_GET['date_to'] ?? null,
        ];

        $requisitions = $this->reqModel->all($filters);
        $suppliers = $this->supplierModel->getActiveList();

        require __DIR__ . '/../../views/requisitions/index.php';
    }

    public function create(): void
    {
        $suppliers = $this->supplierModel->getActiveList();
        $selectedSupplierId = (int)($_GET['supplier_id'] ?? 0);
        $selectedContractId = (int)($_GET['contract_id'] ?? 0);
        $reqType = $_GET['type'] ?? 'material';

        $contracts = [];
        if ($selectedSupplierId > 0) {
            $contracts = $this->contractModel->findBySupplier($selectedSupplierId, true);
        }

        $companySettings = $this->settingModel->getAll();
        $costCenters = (new CostCenter())->all(true);
        $products = (new Product())->all(true);
        $services = (new ServiceCatalog())->all(true);
        $preselectedRepair = !empty($_GET['repair']);
        $preselectedProductId = (int)($_GET['product_id'] ?? 0);
        $preselectedMaintenanceType = in_array($_GET['maintenance_type'] ?? '', ['reparo', 'preventiva'], true)
            ? $_GET['maintenance_type']
            : 'reparo';

        require __DIR__ . '/../../views/requisitions/create.php';
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('requisicoes');
        }

        $user = Security::getUser();
        $type = $_POST['type'] ?? 'material';
        $title = trim($_POST['title'] ?? '');
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $contractId = !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null;
        $department = trim($_POST['department'] ?? ($user['department'] ?? 'Geral'));
        $priority = $_POST['priority'] ?? 'media';
        $justification = trim($_POST['justification'] ?? '');
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        $requesterSignature = trim($_POST['requester_signature'] ?? '');

        if (empty($title) || empty($supplierId) || empty($justification)) {
            flash('danger', 'Por favor, preencha todos os campos obrigatórios (Título, Fornecedor/Prestador e Justificativa).');
            redirect('requisicoes/nova');
        }

        // Processa itens enviados dinamicamente
        $rawDescriptions = $_POST['item_description'] ?? [];
        $rawUnits = $_POST['item_unit'] ?? [];
        $rawQuantities = $_POST['item_quantity'] ?? [];
        $rawPrices = $_POST['item_price'] ?? [];
        $rawSpecs = $_POST['item_specs'] ?? [];

        $items = [];
        $totalEstimated = 0.0;
        $hasAnyPrice = false;

        foreach ($rawDescriptions as $index => $desc) {
            $desc = trim($desc);
            if (empty($desc)) continue;

            $qtyStr = str_replace(',', '.', (string)($rawQuantities[$index] ?? 1));
            $qty = max(0.01, (float)$qtyStr);

            $rawPrice = trim((string)($rawPrices[$index] ?? ''));
            $price = 0.00;
            if ($rawPrice !== '' && $rawPrice !== '0' && $rawPrice !== '0,00' && $rawPrice !== '0.00') {
                $priceClean = str_replace(['R$', ' ', '.', ','], ['', '', '', '.'], $rawPrice);
                $price = max(0.00, (float)$priceClean);
                if ($price > 0) {
                    $hasAnyPrice = true;
                }
            }

            $totalEstimated += ($qty * $price);

            $items[] = [
                'description'     => $desc,
                'unit'            => trim($rawUnits[$index] ?? 'un'),
                'quantity'        => $qty,
                'unit_price'      => $price,
                'technical_specs' => trim($rawSpecs[$index] ?? '')
            ];
        }

        if (empty($items)) {
            flash('danger', 'Adicione pelo menos um item ou serviço à requisição.');
            redirect('requisicoes/nova');
        }

        // Validação de saldo do contrato (se vinculado)
        if ($contractId) {
            $contract = $this->contractModel->findById($contractId);
            if ($contract) {
                if ($contract['status'] !== 'ativo') {
                    flash('danger', 'O contrato selecionado não está ativo.');
                    redirect('requisicoes/nova');
                }
                if ($hasAnyPrice && $totalEstimated > $contract['balance_value']) {
                    flash('warning', 'Atenção: O valor da requisição excede o saldo remanescente do contrato.');
                }
            }
        }

        // Processa upload de anexos / fotos
        $attachments = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            $uploadRelDir = $this->settingModel->get('upload_dir', 'public/uploads/attachments');
            $uploadAbsDir = __DIR__ . '/../../' . $uploadRelDir;
            if (!is_dir($uploadAbsDir)) {
                @mkdir($uploadAbsDir, 0777, true);
            }

            $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
            $allowedMimes = ['image/', 'application/pdf', 'application/msword', 'application/vnd.', 'text/plain', 'text/csv', 'application/zip'];

            $fileCount = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $singleFile = [
                        'name'     => $_FILES['attachments']['name'][$i],
                        'type'     => $_FILES['attachments']['type'][$i],
                        'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                        'error'    => $_FILES['attachments']['error'][$i],
                        'size'     => $_FILES['attachments']['size'][$i],
                    ];
                    $check = Security::validateUploadedFile($singleFile, $allowedExts, $allowedMimes, 15 * 1024 * 1024);
                    if (!$check['valid']) {
                        continue;
                    }

                    $origName = $singleFile['name'];
                    $cleanName = 'att_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $check['ext'];
                    $destPath = $uploadAbsDir . '/' . $cleanName;

                    if (move_uploaded_file($singleFile['tmp_name'], $destPath)) {
                        $attachments[] = [
                            'filename'      => 'uploads/attachments/' . $cleanName,
                            'original_name' => $origName,
                            'mime_type'     => $check['mime'] ?: ($singleFile['type'] ?? 'application/octet-stream'),
                            'file_size'     => $singleFile['size'] ?? 0
                        ];
                    }
                }
            }
        }

        $isRepair = ($type === 'servico' && !empty($_POST['is_equipment_repair'])) ? 1 : 0;
        $linkedProductId = ($isRepair && !empty($_POST['linked_product_id'])) ? (int)$_POST['linked_product_id'] : null;
        $maintenanceType = ($isRepair && in_array($_POST['maintenance_type'] ?? '', ['reparo', 'preventiva'], true))
            ? $_POST['maintenance_type']
            : ($isRepair ? 'reparo' : null);

        $data = [
            'user_id'             => $user['id'],
            'supplier_id'         => $supplierId,
            'contract_id'         => $contractId,
            'type'                => $type,
            'is_equipment_repair' => $isRepair,
            'linked_product_id'   => $linkedProductId,
            'maintenance_type'    => $maintenanceType,
            'title'               => $title,
            'department'          => $department,
            'priority'            => $priority,
            'justification'       => $justification,
            'deadline'            => $deadline,
            'requester_signature' => !empty($requesterSignature) ? $requesterSignature : null,
            'status'              => 'pendente'
        ];

        try {
            $requisitionId = $this->reqModel->createWithItems($data, $items, $attachments);

            // Dispara notificação por e-mail para aprovadores se configurado
            try {
                $allUsers = (new User())->all();
                $approvers = array_filter($allUsers, fn($u) => in_array($u['role'], ['admin', 'aprovador']) && $u['active']);
                $newReq = $this->reqModel->findById($requisitionId);
                if ($newReq) {
                    $this->mailService->notifyNewRequisition($newReq, $approvers);
                }
            } catch (\Throwable $e) {
                // Silently continue if email fails
            }

            flash('success', "Requisição criada com sucesso!");
            redirect("requisicoes/ver?id={$requisitionId}");
        } catch (\Exception $e) {
            flash('danger', 'Erro ao salvar requisição: ' . $e->getMessage());
            redirect('requisicoes/nova');
        }
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $requisition = $this->reqModel->findById($id);

        if (!$requisition) {
            flash('danger', 'Requisição não encontrada.');
            redirect('requisicoes');
        }

        $logs = $this->logModel->getByRequisition($id);
        $currentUser = Security::getUser();
        $companySettings = $this->settingModel->getAll();

        $qrCodeUrl = null;
        if (($companySettings['is_web_hosted'] ?? '0') === '1' && ($companySettings['enable_qrcode_link'] ?? '0') === '1') {
            $token = $this->reqModel->ensureViewToken($id);
            $qrCodeUrl = url('requisicoes/publica?token=' . $token);
        }

        require __DIR__ . '/../../views/requisitions/show.php';
    }

    public function changeStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação inválida.');
            redirect('requisicoes');
        }

        $id = (int)($_POST['id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $approverSig = trim($_POST['approver_signature'] ?? '');
        $user = Security::getUser();

        $allowedStatuses = ['pendente', 'em_analise', 'aprovada', 'rejeitada', 'em_execucao', 'concluida', 'cancelada'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            flash('danger', 'Status inválido.');
            redirect("requisicoes/ver?id={$id}");
        }

        if (in_array($newStatus, ['aprovada', 'rejeitada', 'em_analise']) && !Security::hasRole(['admin', 'aprovador'])) {
            flash('danger', 'Apenas gestores ou aprovadores podem aprovar ou rejeitar requisições.');
            redirect("requisicoes/ver?id={$id}");
        }

        try {
            $this->reqModel->updateStatus($id, $newStatus, (int)$user['id'], $reason, !empty($approverSig) ? $approverSig : null);
            
            // Notificação por e-mail
            try {
                $reqUpdated = $this->reqModel->findById($id);
                if ($reqUpdated) {
                    $this->mailService->notifyStatusChange($reqUpdated, $newStatus, $reason);
                }
            } catch (\Throwable $e) {}

            $statusLabels = [
                'aprovada'    => 'aprovada com sucesso',
                'rejeitada'   => 'rejeitada',
                'em_execucao' => 'movida para em execução',
                'concluida'   => 'marcada como concluída',
                'cancelada'   => 'cancelada'
            ];
            $msg = $statusLabels[$newStatus] ?? "atualizada para '{$newStatus}'";
            flash('success', "Requisição {$msg}!");
        } catch (\Exception $e) {
            flash('danger', 'Erro ao atualizar status: ' . $e->getMessage());
        }

        redirect("requisicoes/ver?id={$id}");
    }

    public function addAttachment(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('requisicoes');
        }

        $id = (int)($_POST['requisition_id'] ?? 0);
        $requisition = $this->reqModel->findById($id);
        if (!$requisition) {
            flash('danger', 'Requisição não encontrada.');
            redirect('requisicoes');
        }

        if (!empty($_FILES['new_attachment']['name']) && $_FILES['new_attachment']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
            $allowedMimes = ['image/', 'application/pdf', 'application/msword', 'application/vnd.', 'text/plain', 'text/csv', 'application/zip'];
            $check = Security::validateUploadedFile($_FILES['new_attachment'], $allowedExts, $allowedMimes, 15 * 1024 * 1024);

            if (!$check['valid']) {
                flash('danger', $check['message']);
                redirect("requisicoes/ver?id={$id}");
            }

            $uploadRelDir = $this->settingModel->get('upload_dir', 'public/uploads/attachments');
            $uploadAbsDir = __DIR__ . '/../../' . $uploadRelDir;
            if (!is_dir($uploadAbsDir)) {
                @mkdir($uploadAbsDir, 0755, true);
            }

            $origName = $_FILES['new_attachment']['name'];
            $cleanName = 'att_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $check['ext'];
            $destPath = $uploadAbsDir . '/' . $cleanName;

            if (move_uploaded_file($_FILES['new_attachment']['tmp_name'], $destPath)) {
                $this->reqModel->addAttachment(
                    $id,
                    'uploads/attachments/' . $cleanName,
                    $origName,
                    $check['mime'] ?: ($_FILES['new_attachment']['type'] ?? 'application/octet-stream'),
                    (int)($_FILES['new_attachment']['size'] ?? 0)
                );
                flash('success', 'Anexo adicionado com sucesso!');
            } else {
                flash('danger', 'Falha ao salvar arquivo no servidor.');
            }
        } else {
            flash('danger', 'Nenhum arquivo válido foi selecionado.');
        }

        redirect("requisicoes/ver?id={$id}");
    }

    public function deleteAttachment(): void
    {
        Security::requireRole(['admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Ação não permitida.');
            redirect('requisicoes');
        }

        $attachmentId = (int)($_POST['attachment_id'] ?? 0);
        $reqId = (int)($_POST['requisition_id'] ?? 0);

        $this->reqModel->deleteAttachment($attachmentId);
        flash('success', 'Anexo removido.');
        redirect("requisicoes/ver?id={$reqId}");
    }

    public function sendEmail(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida.');
            redirect('requisicoes');
        }

        $id = (int)($_POST['id'] ?? 0);
        $requisition = $this->reqModel->findById($id);
        if (!$requisition) {
            flash('danger', 'Requisição não encontrada.');
            redirect('requisicoes');
        }

        $toEmail = trim($_POST['recipient_email'] ?? '');
        $toName  = trim($_POST['recipient_name'] ?? 'Destinatário');
        $subject = trim($_POST['email_subject'] ?? ("Requisição {$requisition['code']} - {$requisition['title']}"));
        $customMsg = trim($_POST['email_message'] ?? '');

        if (empty($toEmail)) {
            flash('danger', 'Informe um e-mail de destino válido.');
            redirect("requisicoes/ver?id={$id}");
        }

        $companySettings = $this->settingModel->getAll();
        $companyName = $companySettings['company_name'] ?? 'Minha Empresa';
        $reqColor = safe_hex_color($companySettings['req_primary_color'] ?? '#4f46e5', '#4f46e5');
        $isWebQr = ($companySettings['is_web_hosted'] ?? '0') === '1' && ($companySettings['enable_qrcode_link'] ?? '0') === '1';

        $onlineButtonHtml = '';
        if ($isWebQr) {
            $token = $this->reqModel->ensureViewToken($id);
            $publicLink = url('requisicoes/publica?token=' . $token);
            $onlineButtonHtml = "
            <div style='margin-top:20px;text-align:center;'>
                <a href='{$publicLink}' style='background:{$reqColor};color:#fff;text-decoration:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:bold;display:inline-block;'>
                    Visualizar Requisição Online &rarr;
                </a>
            </div>";
        }

        // Constrói corpo do e-mail elegante com tabela dos itens
        $itemsHtml = '';
        foreach ($requisition['items'] as $it) {
            $itemsHtml .= "<tr>
                <td style='border:1px solid #cbd5e1;padding:6px 8px;'>" . htmlspecialchars($it['description']) . "</td>
                <td style='border:1px solid #cbd5e1;padding:6px 8px;text-align:center;'>" . strtoupper($it['unit']) . "</td>
                <td style='border:1px solid #cbd5e1;padding:6px 8px;text-align:right;'>" . number_format((float)$it['quantity'], 2, ',', '.') . "</td>";
            if (!empty($requisition['has_values'])) {
                $itemsHtml .= "<td style='border:1px solid #cbd5e1;padding:6px 8px;text-align:right;'>" . formatMoney($it['unit_price']) . "</td>
                <td style='border:1px solid #cbd5e1;padding:6px 8px;text-align:right;'>" . formatMoney($it['total_price']) . "</td>";
            }
            $itemsHtml .= "</tr>";
        }

        $htmlBody = "
        <div style='font-family:Arial,Helvetica,sans-serif;max-width:650px;margin:auto;padding:25px;border:1px solid #cbd5e1;border-radius:12px;background:#fff;'>
            <div style='border-bottom:2px solid {$reqColor};padding-bottom:12px;margin-bottom:15px;'>
                <h2 style='color:#0f172a;margin:0 0 4px;'>FICHA DE REQUISIÇÃO: " . htmlspecialchars($requisition['code']) . "</h2>
                <p style='color:#64748b;font-size:12px;margin:0;'>Empresa Solicitante: <strong>" . htmlspecialchars($companyName) . "</strong></p>
                <p style='color:#64748b;font-size:12px;margin:2px 0 0;'>Solicitante Responsável: <strong>" . htmlspecialchars($requisition['requester_name']) . "</strong> (" . htmlspecialchars($requisition['requester_email'] ?? '') . ")</p>
            </div>
            " . (!empty($customMsg) ? "<div style='background:#f8fafc;border-left:4px solid {$reqColor};padding:12px;margin-bottom:15px;font-size:13px;color:#334155;white-space:pre-line;'>" . htmlspecialchars($customMsg) . "</div>" : "") . "
            <h3 style='color:#1e293b;font-size:14px;margin:15px 0 8px;'>Itens Solicitados:</h3>
            <table style='width:100%;border-collapse:collapse;font-size:12px;margin-bottom:15px;'>
                <thead>
                    <tr style='background:#f1f5f9;color:#334155;'>
                        <th style='border:1px solid #cbd5e1;padding:6px 8px;text-align:left;'>Item / Descrição</th>
                        <th style='border:1px solid #cbd5e1;padding:6px 8px;text-align:center;'>Unid.</th>
                        <th style='border:1px solid #cbd5e1;padding:6px 8px;text-align:right;'>Qtd</th>
                        " . (!empty($requisition['has_values']) ? "<th style='border:1px solid #cbd5e1;padding:6px 8px;text-align:right;'>Unitário</th><th style='border:1px solid #cbd5e1;padding:6px 8px;text-align:right;'>Total</th>" : "") . "
                    </tr>
                </thead>
                <tbody>{$itemsHtml}</tbody>
                " . (!empty($requisition['has_values']) ? "<tfoot><tr style='background:#f8fafc;font-weight:bold;'><td colspan='4' style='border:1px solid #cbd5e1;padding:6px 8px;text-align:right;'>TOTAL GERAL:</td><td style='border:1px solid #cbd5e1;padding:6px 8px;text-align:right;color:{$reqColor};'>" . formatMoney($requisition['total_amount']) . "</td></tr></tfoot>" : "") . "
            </table>
            <p style='font-size:12px;color:#64748b;'><strong>Justificativa:</strong> " . htmlspecialchars($requisition['justification']) . "</p>
            {$onlineButtonHtml}
        </div>";

        try {
            $sent = $this->mailService->send($toEmail, $toName, $subject, $htmlBody);
            if ($sent) {
                flash('success', "Requisição enviada com sucesso para {$toEmail}!");
            } else {
                flash('warning', "Não foi possível confirmar o envio para {$toEmail}. Verifique seu servidor de e-mail.");
            }
        } catch (\Exception $e) {
            flash('danger', "Erro ao enviar e-mail: " . $e->getMessage());
        }

        redirect("requisicoes/ver?id={$id}");
    }

    public function print(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $requisition = $this->reqModel->findById($id);

        if (!$requisition) {
            die('Requisição não encontrada.');
        }

        $companySettings = $this->settingModel->getAll();

        $qrCodeUrl = null;
        if (($companySettings['is_web_hosted'] ?? '0') === '1' && ($companySettings['enable_qrcode_link'] ?? '0') === '1') {
            $token = $this->reqModel->ensureViewToken($id);
            $qrCodeUrl = url('requisicoes/publica?token=' . $token);
        }

        require __DIR__ . '/../../views/requisitions/print.php';
    }

    public function publicView(): void
    {
        $companySettings = $this->settingModel->getAll();
        $isWebQr = ($companySettings['is_web_hosted'] ?? '0') === '1' && ($companySettings['enable_qrcode_link'] ?? '0') === '1';

        if (!$isWebQr) {
            http_response_code(404);
            die('Visualização online desativada nas configurações do sistema.');
        }

        $ip = Security::getClientIp();
        $waitMin = Security::isRateLimited('public_token', $ip);
        if ($waitMin !== null) {
            http_response_code(429);
            die("Muitas tentativas inválidas. Aguarde {$waitMin} minuto(s).");
        }

        $token = trim((string)($_GET['token'] ?? ''));
        $requisition = !empty($token) ? $this->reqModel->findByViewToken($token) : null;

        if (!$requisition) {
            Security::recordFailedAttempt('public_token', $ip, 10, 15);
            http_response_code(404);
            die('Token de visualização inválido ou requisição não encontrada.');
        }

        Security::clearAttempts('public_token', $ip);
        require __DIR__ . '/../../views/requisitions/public_view.php';
    }

    public function delete(): void
    {
        Security::requireRole(['admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida ou token expirado.');
            redirect('requisicoes');
        }

        $id = (int)($_POST['id'] ?? 0);
        $req = $this->reqModel->findById($id);

        if (!$req) {
            flash('danger', 'Requisição não encontrada.');
            redirect('requisicoes');
        }

        $this->reqModel->delete($id);
        flash('success', "Requisição {$req['code']} excluída com sucesso.");
        redirect('requisicoes');
    }

    public function edit(): void
    {
        Security::requireRole(['admin']);

        $id = (int)($_GET['id'] ?? 0);
        $requisition = $this->reqModel->findById($id);

        if (!$requisition) {
            flash('danger', 'Requisição não encontrada.');
            redirect('requisicoes');
        }

        $items = $this->reqModel->getItems($id);
        $suppliers = $this->supplierModel->getActiveList();
        $selectedSupplierId = (int)($requisition['supplier_id'] ?? 0);
        $contracts = $selectedSupplierId > 0 
            ? $this->contractModel->findBySupplier($selectedSupplierId, true) 
            : [];
        $companySettings = $this->settingModel->getAll();
        $costCenters = (new CostCenter())->all(true);
        $products = (new Product())->all(true);
        $services = (new ServiceCatalog())->all(true);

        require __DIR__ . '/../../views/requisitions/edit.php';
    }

    public function update(): void
    {
        Security::requireRole(['admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('danger', 'Requisição inválida ou token expirado.');
            redirect('requisicoes');
        }

        $id = (int)($_POST['id'] ?? 0);
        $req = $this->reqModel->findById($id);

        if (!$req) {
            flash('danger', 'Requisição não encontrada.');
            redirect('requisicoes');
        }

        $title = trim($_POST['title'] ?? '');
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $type = $_POST['type'] ?? 'material';
        $department = trim($_POST['department'] ?? 'Geral');
        $priority = $_POST['priority'] ?? 'media';
        $justification = trim($_POST['justification'] ?? '');
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        $contractId = !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null;

        if (empty($title) || empty($supplierId) || empty($justification)) {
            flash('danger', 'Preencha todos os campos obrigatórios.');
            redirect("requisicoes/editar?id={$id}");
        }

        // Processa itens
        $rawDescriptions = $_POST['item_description'] ?? [];
        $rawUnits = $_POST['item_unit'] ?? [];
        $rawQuantities = $_POST['item_quantity'] ?? [];
        $rawPrices = $_POST['item_price'] ?? [];
        $rawSpecs = $_POST['item_specs'] ?? [];

        $items = [];
        foreach ($rawDescriptions as $index => $desc) {
            $desc = trim($desc);
            if (empty($desc)) continue;

            $qty = (float)str_replace(',', '.', (string)($rawQuantities[$index] ?? 1));
            $priceStr = str_replace(['R$', ' ', '.'], '', (string)($rawPrices[$index] ?? '0'));
            $priceStr = str_replace(',', '.', $priceStr);
            $price = (float)$priceStr;

            $items[] = [
                'description'     => $desc,
                'unit'            => trim($rawUnits[$index] ?? 'un'),
                'quantity'        => $qty,
                'unit_price'      => $price,
                'technical_specs' => trim($rawSpecs[$index] ?? '')
            ];
        }

        if (empty($items)) {
            flash('danger', 'A requisição precisa conter ao menos um item.');
            redirect("requisicoes/editar?id={$id}");
        }

        $currentUser = Security::getUser();

        $isRepair = ($type === 'servico' && !empty($_POST['is_equipment_repair'])) ? 1 : 0;
        $linkedProductId = ($isRepair && !empty($_POST['linked_product_id'])) ? (int)$_POST['linked_product_id'] : null;
        $maintenanceType = ($isRepair && in_array($_POST['maintenance_type'] ?? '', ['reparo', 'preventiva'], true))
            ? $_POST['maintenance_type']
            : ($isRepair ? 'reparo' : null);

        try {
            $data = [
                'title'               => $title,
                'supplier_id'         => $supplierId,
                'contract_id'         => $contractId,
                'type'                => $type,
                'is_equipment_repair' => $isRepair,
                'linked_product_id'   => $linkedProductId,
                'maintenance_type'    => $maintenanceType,
                'department'          => $department,
                'priority'            => $priority,
                'justification'       => $justification,
                'deadline'            => $deadline
            ];

            $this->reqModel->updateByAdmin($id, $data, $items, (int)$currentUser['id'], $currentUser['name']);

            flash('success', "Requisição {$req['code']} atualizada com sucesso pelo administrador!");
            redirect("requisicoes/ver?id={$id}");
        } catch (\Exception $e) {
            flash('danger', 'Erro ao atualizar requisição: ' . $e->getMessage());
            redirect("requisicoes/editar?id={$id}");
        }
    }

    public function signCertificate(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $nonce = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['nonce'] ?? $_POST['nonce'] ?? '');
        if (!empty($nonce) && !empty($_SESSION['pending_cert_signature'][$nonce]['result'])) {
            $res = $_SESSION['pending_cert_signature'][$nonce]['result'];
            unset($_SESSION['pending_cert_signature'][$nonce]);
            echo json_encode($res);
            exit;
        }

        $pemCert = $_SERVER['SSL_CLIENT_CERT'] ?? '';
        if (!empty($pemCert)) {
            $docData = [
                'app'       => APP_NAME,
                'title'     => $_POST['doc_title'] ?? 'Requisição ReqMaster',
                'timestamp' => date('Y-m-d H:i:s'),
                'user'      => $_SESSION['user']['name'] ?? 'Usuário',
                'ip'        => Security::getClientIp(),
            ];
            $result = $this->certService->signFromX509Pem($pemCert, $docData, (string)($_SERVER['SSL_CLIENT_VERIFY'] ?? ''));
            echo json_encode($result);
            exit;
        }

        echo json_encode([
            'success' => false,
            'message' => 'Aguardando seleção do certificado digital instalado no Windows ou Linux pelo navegador.'
        ]);
        exit;
    }
}
