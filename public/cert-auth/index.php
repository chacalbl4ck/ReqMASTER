<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/Helpers/Security.php';
require_once __DIR__ . '/../../app/Services/CertificateSignService.php';

use App\Helpers\Security;
use App\Services\CertificateSignService;

Security::sendSecurityHeaders(true);

if (!Security::isLoggedIn()) {
    echo '<!DOCTYPE html><html lang="pt-BR"><body style="font-family:sans-serif;padding:30px;text-align:center;">
        <h2>Sessão não autenticada</h2>
        <p>Faça login no sistema antes de assinar com Certificado Digital.</p>
        <button onclick="window.close()">Fechar</button>
    </body></html>';
    exit;
}

$ctx = preg_replace('/[^a-z]/', '', $_GET['ctx'] ?? 'requester');
$nonce = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['nonce'] ?? '');
$docTitle = trim((string)($_GET['doc_title'] ?? 'Requisição ReqMaster'));

$pemCert = $_SERVER['SSL_CLIENT_CERT'] ?? '';
$clientVerify = $_SERVER['SSL_CLIENT_VERIFY'] ?? '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

$result = null;
if (!empty($pemCert)) {
    $user = Security::getUser();
    $certService = new CertificateSignService();
    $docData = [
        'app'       => APP_NAME,
        'title'     => $docTitle,
        'timestamp' => date('Y-m-d H:i:s'),
        'user'      => $user['name'] ?? 'Usuário',
        'ip'        => Security::getClientIp(),
    ];
    $result = $certService->signFromX509Pem($pemCert, $docData, (string)$clientVerify);

    if (!empty($result['success']) && !empty($nonce)) {
        $_SESSION['pending_cert_signature'][$nonce] = [
            'ctx'    => $ctx,
            'result' => $result,
            'ts'     => time(),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Autenticação de Certificado Digital - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/tailwind.min.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 font-sans">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl space-y-4 text-center">
        <?php if ($result && !empty($result['success'])): ?>
            <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-2xl">
                <i class="fas fa-certificate"></i>
            </div>
            <h1 class="text-lg font-bold text-white">Certificado Validado com Sucesso!</h1>
            <p class="text-xs text-slate-400">
                Titular: <strong class="text-emerald-300"><?= htmlspecialchars($result['signer_name']) ?></strong><br>
                Documento: <?= htmlspecialchars($result['signer_doc']) ?>
            </p>
            <div class="bg-white p-2 rounded-xl flex justify-center">
                <img src="<?= htmlspecialchars($result['seal_image']) ?>" alt="Selo Digital" class="max-w-full h-auto">
            </div>
            <p class="text-xs text-slate-400">Transferindo assinatura para o formulário...</p>
            <script>
                (function() {
                    const payload = {
                        type: 'RM_CERT_SIGNED',
                        ctx: <?= json_encode($ctx) ?>,
                        nonce: <?= json_encode($nonce) ?>,
                        result: <?= json_encode($result) ?>
                    };
                    if (window.opener && !window.opener.closed) {
                        window.opener.postMessage(payload, '*');
                        setTimeout(() => window.close(), 700);
                    }
                })();
            </script>
        <?php elseif ($result && empty($result['success'])): ?>
            <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-500/15 text-rose-400 border border-rose-500/30 flex items-center justify-center text-2xl">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1 class="text-lg font-bold text-white">Falha na Leitura do Certificado</h1>
            <p class="text-xs text-rose-300"><?= htmlspecialchars($result['message'] ?? 'Certificado inválido.') ?></p>
            <button onclick="window.location.reload()" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold">
                Selecionar Outro Certificado
            </button>
        <?php else: ?>
            <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-500/15 text-amber-400 border border-amber-500/30 flex items-center justify-center text-2xl">
                <i class="fas fa-id-card"></i>
            </div>
            <h1 class="text-lg font-bold text-white">Nenhum Certificado Recebido</h1>
            <?php if (!$isHttps): ?>
                <p class="text-xs text-slate-300 leading-relaxed">
                    Para acessar os certificados A1 e A3 instalados no Windows ou Linux pelo navegador, esta página precisa ser carregada via <strong>HTTPS (mTLS)</strong>.
                </p>
                <a href="<?= htmlspecialchars('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '')) ?>" class="block w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold">
                    <i class="fas fa-lock mr-1"></i> Abrir em HTTPS Seguro
                </a>
            <?php else: ?>
                <p class="text-xs text-slate-300 leading-relaxed">
                    Nenhum certificado foi selecionado na caixa de diálogo do navegador (ou o módulo <code>mod_ssl</code> do servidor web não solicitou o certificado do cliente).
                </p>
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-left text-[11px] text-slate-400 space-y-1">
                    <div><strong class="text-slate-300">Dica (Windows/Linux):</strong></div>
                    <div>1. Certifique-se de que seu certificado A1 ou Token A3 está instalado no repositório de certificados do Windows (<code>certmgr.msc</code>) ou Linux NSS e conectado.</div>
                    <div>2. Clique abaixo para abrir novamente o seletor nativo do navegador.</div>
                </div>
                <button onclick="window.location.reload()" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold">
                    <i class="fas fa-sync-alt mr-1"></i> Solicitar Certificado ao Navegador
                </button>
            <?php endif; ?>
            <button onclick="window.close()" class="w-full py-2 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold">
                Fechar Janela
            </button>
        <?php endif; ?>
    </div>
</body>
</html>
