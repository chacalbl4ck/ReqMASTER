<?php
declare(strict_types=1);

// ========================================================
// REQMASTER - INSTALADOR SEGURO & SETUP AUTOMATIZADO DO BANCO
// Compatível com PHP 8.2, 8.4 e PHP 8.5+ (Pdo\Mysql)
// ========================================================

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

$configFile = __DIR__ . '/../config/db_config.php';
$configLockFile = __DIR__ . '/../config/.installed.lock';
$lockFile = __DIR__ . '/installed.lock';
$databaseSqlFile = __DIR__ . '/../database.sql';

// Compatibilidade de constantes MySQL entre PHP 8.2 e PHP 8.5+
$mysqlInitCmd = null;
if (defined('Pdo\Mysql::ATTR_INIT_COMMAND')) {
    $mysqlInitCmd = \Pdo\Mysql::ATTR_INIT_COMMAND;
} elseif (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
    $mysqlInitCmd = @constant('PDO::MYSQL_ATTR_INIT_COMMAND');
}

$mysqlMultiStmt = null;
if (defined('Pdo\Mysql::ATTR_MULTI_STATEMENTS')) {
    $mysqlMultiStmt = \Pdo\Mysql::ATTR_MULTI_STATEMENTS;
} elseif (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
    $mysqlMultiStmt = @constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
}

// Detecção dinâmica da URL base & login
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';

$basePath = preg_replace('/\/install(\/index\.php)?.*$/i', '', $parsedPath);
$basePath = rtrim($basePath, '/');

if (!str_ends_with($basePath, '/public') && is_dir(dirname(__DIR__) . '/public')) {
    $basePath .= '/public';
}

$baseAppUrl = rtrim($protocol . $host . $basePath, '/');
$loginUrl = ($baseAppUrl ?: '') . '/login';

function validateStrongPassword(string $pwd): array
{
    $missing = [];
    if (strlen($pwd) < 8) $missing[] = 'mínimo 8 caracteres';
    if (!preg_match('/[A-Z]/', $pwd)) $missing[] = '1 letra maiúscula';
    if (!preg_match('/[a-z]/', $pwd)) $missing[] = '1 letra minúscula';
    if (!preg_match('/[0-9]/', $pwd)) $missing[] = '1 número';
    if (!preg_match('/[^a-zA-Z0-9]/', $pwd)) $missing[] = '1 símbolo (!@#$...)';
    return $missing;
}

// Ação: apagar a pasta install automaticamente
if (isset($_GET['action']) && $_GET['action'] === 'delete_install') {
    $installDir = __DIR__;

    if (is_dir($installDir)) {
        $files = array_diff(scandir($installDir), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $installDir . DIRECTORY_SEPARATOR . $file;
            if ($file !== 'index.php') {
                if (is_dir($filePath)) {
                    $subFiles = array_diff(scandir($filePath), ['.', '..']);
                    foreach ($subFiles as $sf) {
                        @unlink($filePath . DIRECTORY_SEPARATOR . $sf);
                    }
                    @rmdir($filePath);
                } else {
                    @unlink($filePath);
                }
            }
        }
    }

    $unlinked = @unlink(__FILE__);
    $dirRemoved = $unlinked ? @rmdir($installDir) : false;

    if (!$dirRemoved && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @pclose(@popen("start /B cmd /C ping 127.0.0.1 -n 2 > nul & rmdir /S /Q \"{$installDir}\"", "r"));
        $dirRemoved = true;
    }

    if ($dirRemoved) {
        header("Location: " . $loginUrl . (str_contains($loginUrl, '?') ? '&' : '?') . "msg=install_deleted");
        exit;
    }

    $deleteWarning = "Sem permissão do servidor web para remover a pasta '{$installDir}' automaticamente. Execute no terminal do servidor: sudo rm -rf " . $installDir;
}

// Verificação de requisitos do sistema
$phpVersionOk = version_compare(PHP_VERSION, '8.2.0', '>=');
$pdoLoaded = extension_loaded('pdo');
$pdoMysqlLoaded = extension_loaded('pdo_mysql');
$opensslLoaded = extension_loaded('openssl');
$configWritable = is_writable(__DIR__ . '/../config');
$sqlExists = file_exists($databaseSqlFile);

$canInstall = $phpVersionOk && $pdoLoaded && $pdoMysqlLoaded && $opensslLoaded && $configWritable && $sqlExists;

$step = 'form';
$errorMessage = $deleteWarning ?? '';
$successData = null;

if (file_exists($configFile) && (file_exists($configLockFile) || file_exists($lockFile)) && !isset($_POST['install_submit'])) {
    $step = 'already_installed';
}

// Processamento do formulário de instalação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_submit'])) {
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');

    $adminName  = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = (string)($_POST['admin_password'] ?? '');

    // Validação rigorosa do nome do banco
    if (empty($dbHost) || empty($dbPort) || empty($dbName) || !preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        $errorMessage = "Informe um host, porta e nome de banco de dados válidos (apenas letras, números e underline).";
    } elseif (empty($dbUser)) {
        $errorMessage = "É obrigatório criar e informar um usuário dedicado do MySQL.";
    } elseif (strtolower($dbUser) === 'root') {
        $errorMessage = "Por segurança, não utilize o usuário genérico 'root'. Crie previamente um usuário MySQL exclusivo com senha forte e privilégios vinculados apenas a este banco de dados.";
    } elseif (empty($dbPass)) {
        $errorMessage = "É obrigatório definir uma senha para o usuário do banco de dados MySQL. Bancos sem senha não são permitidos.";
    } elseif (!empty($dbMissing = validateStrongPassword($dbPass))) {
        $errorMessage = "A senha do banco de dados MySQL não atende aos critérios de senha forte (faltam: " . implode(', ', $dbMissing) . ").";
    } elseif (empty($adminName) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Informe o nome completo e um e-mail válido para a conta do Administrador.";
    } elseif (!empty($admMissing = validateStrongPassword($adminPass))) {
        $errorMessage = "A senha do Administrador precisa ser uma senha forte (faltam: " . implode(', ', $admMissing) . ").";
    } else {
        try {
            $serverOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ];
            if ($mysqlInitCmd !== null) {
                $serverOptions[$mysqlInitCmd] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
            }

            $dbOptions = $serverOptions;
            if ($mysqlMultiStmt !== null) {
                $dbOptions[$mysqlMultiStmt] = true;
            }

            // 1. Tenta conectar diretamente no banco (caso já criado e vinculado ao usuário exclusivo)
            $dsnDb = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            try {
                $pdo = new PDO($dsnDb, $dbUser, $dbPass, $dbOptions);
            } catch (PDOException $e) {
                // Se o erro for banco desconhecido (1049), tenta conectar ao servidor para criá-lo com o usuário configurado
                if ((int)($e->errorInfo[1] ?? 0) === 1049 || str_contains($e->getMessage(), 'Unknown database')) {
                    $dsnServer = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
                    $pdoServer = new PDO($dsnServer, $dbUser, $dbPass, $serverOptions);
                    $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                    $pdo = new PDO($dsnDb, $dbUser, $dbPass, $dbOptions);
                } else {
                    throw $e;
                }
            }

            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            // 2. Verifica os privilégios do usuário para alertar sobre isolamento exclusivo ao banco
            $grantsStmt = $pdo->query("SHOW GRANTS FOR CURRENT_USER()");
            $grants = $grantsStmt ? $grantsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $hasDbScopedGrant = false;
            $hasWildcardGlobalGrant = false;
            foreach ($grants as $g) {
                if (stripos($g, 'ON *.*') !== false && stripos($g, 'ALL PRIVILEGES') !== false) {
                    $hasWildcardGlobalGrant = true;
                }
                if (stripos($g, "`{$dbName}`.*") !== false || stripos($g, "{$dbName}.*") !== false) {
                    $hasDbScopedGrant = true;
                }
            }

            if ($hasWildcardGlobalGrant && !$hasDbScopedGrant) {
                throw new Exception("O usuário '{$dbUser}' possui privilégios globais irrestritos (*.*). Por segurança, vincule o usuário EXCLUSIVAMENTE ao banco `{$dbName}` (GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'...');");
            }

            // 3. Lê e executa o esquema limpo do database.sql (sem dados fictícios)
            $sqlContent = file_get_contents($databaseSqlFile);
            if ($sqlContent === false) {
                throw new Exception("Não foi possível ler o arquivo database.sql.");
            }

            // Remove CREATE DATABASE / USE fixos e executa diretamente no banco conectado
            $sqlContent = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`[^`]+`[^;]*;/i', '', $sqlContent);
            $sqlContent = preg_replace('/USE\s+`[^`]+`\s*;/i', '', $sqlContent);

            $pdo->exec($sqlContent);

            // 4. Cria exclusivamente o Administrador Principal configurado
            $hashedPassword = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmtIns = $pdo->prepare("
                INSERT INTO users (name, email, password, role, department, active)
                VALUES (:name, :email, :pwd, 'admin', 'Administração', 1)
            ");
            $stmtIns->execute([
                'name'  => $adminName,
                'email' => $adminEmail,
                'pwd'   => $hashedPassword,
            ]);

            // 5. Inicializa configurações limpas (travado aguardando licença oficial)
            $stmtSet = $pdo->prepare("
                INSERT INTO settings (key_name, value) VALUES (:k, :v)
                ON DUPLICATE KEY UPDATE value = :v2
            ");
            $initialSettings = [
                'license_status'        => 'unlicensed',
                'license_key'           => '',
                'license_lock_mode'     => '1',
                'license_proof'         => '',
                'is_web_hosted'         => '0',
                'enable_qrcode_link'    => '0',
                'block_search_bots'     => '0',
                'theme_primary_color'   => '#4f46e5',
                'theme_secondary_color' => '#4338ca',
                'theme_accent_color'    => '#6366f1',
                'req_primary_color'     => '#4338ca',
                'req_bg_tint'           => '#eef2ff',
                'req_border_color'      => '#cbd5e1',
            ];
            foreach ($initialSettings as $sk => $sv) {
                $stmtSet->execute(['k' => $sk, 'v' => $sv, 'v2' => $sv]);
            }

            // 6. Grava config/db_config.php vinculado exclusivamente ao usuário configurado
            $configPhp = "<?php\ndeclare(strict_types=1);\n\n// Gerado automaticamente pelo Setup Seguro em " . date('Y-m-d H:i:s') . "\nreturn [\n"
                . "    'host'         => " . var_export($dbHost, true) . ",\n"
                . "    'port'         => " . var_export($dbPort, true) . ",\n"
                . "    'dbname'       => " . var_export($dbName, true) . ",\n"
                . "    'user'         => " . var_export($dbUser, true) . ",\n"
                . "    'pass'         => " . var_export($dbPass, true) . ",\n"
                . "    'installed_at' => " . var_export(date('Y-m-d H:i:s'), true) . ",\n"
                . "];\n";

            if (@file_put_contents($configFile, $configPhp, LOCK_EX) === false) {
                throw new Exception("Não foi possível gravar config/db_config.php. Verifique as permissões de escrita na pasta config/.");
            }

            // 7. Cria trava de instalação na pasta config/ (sempre gravável) e opcionalmente em install/ sem emitir warning
            @file_put_contents($configLockFile, "Installed on " . date('c'), LOCK_EX);
            if (is_writable(__DIR__)) {
                @file_put_contents($lockFile, "Installed on " . date('c'), LOCK_EX);
            }

            $step = 'success';
            $successData = [
                'dbHost'     => $dbHost,
                'dbPort'     => $dbPort,
                'dbName'     => $dbName,
                'dbUser'     => $dbUser,
                'adminEmail' => $adminEmail,
            ];
        } catch (PDOException $e) {
            $errorMessage = "Erro na conexão ou execução MySQL: " . $e->getMessage() . ". Certifique-se de que o usuário '{$dbUser}' e a senha foram previamente criados no MySQL e vinculados ao banco '{$dbName}'.";
        } catch (Exception $e) {
            $errorMessage = "Erro no processo de instalação: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="../public/assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../public/assets/img/favicon-32.png">
    <title>Instalador Seguro - ReqMaster</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
        select, textarea {
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff;
        }
        input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):focus,
        select:focus, textarea:focus {
            border-color: #4f46e5 !important;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.16) !important;
            outline: none !important;
        }
    </style>
</head>
<body class="min-h-full py-10 px-4 flex items-center justify-center text-slate-800">
    <div class="max-w-2xl w-full">

        <div class="text-center mb-8">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white shadow-xl shadow-indigo-500/30 mb-3">
                <i class="fas fa-shield-halved text-2xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Req<span class="text-indigo-400">Master</span> Setup</h1>
            <p class="text-sm text-slate-400 mt-1">Assistente Seguro de Instalação & Vinculação Exclusiva MySQL</p>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-3 shadow-md">
                <i class="fas fa-exclamation-triangle text-xl text-rose-500 shrink-0 mt-0.5"></i>
                <div class="text-sm">
                    <strong class="font-bold block">Falha na Instalação:</strong>
                    <span><?= htmlspecialchars($errorMessage) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($step === 'success'): ?>
            <div class="bg-white rounded-3xl p-8 border border-slate-100 shadow-2xl space-y-6">
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-2xl mb-3 shadow-inner">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">Instalação Segura Concluída!</h2>
                    <p class="text-sm text-slate-500 mt-1">O banco de dados foi vinculado exclusivamente ao usuário configurado e todas as tabelas foram criadas.</p>
                </div>

                <div class="p-6 rounded-2xl bg-rose-50 border-2 border-rose-300 text-rose-950 space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-rose-600 text-white flex items-center justify-center text-lg shrink-0 shadow-md">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold uppercase tracking-wider text-rose-900">Ação Obrigatória de Segurança</h3>
                            <p class="text-xs text-rose-700">Por segurança, <strong>APAGUE A PASTA DE INSTALAÇÃO</strong> imediatamente!</p>
                        </div>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-rose-200 text-xs font-mono text-slate-700 break-all select-all">
                        <span>Pasta a remover: <strong><?= htmlspecialchars(__DIR__) ?></strong></span>
                    </div>
                    <div class="pt-2 flex flex-col sm:flex-row gap-2">
                        <a href="?action=delete_install" onclick="return confirm('Confirma a exclusão da pasta install para segurança do sistema?');" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md transition">
                            <i class="fas fa-trash-alt"></i> Apagar Pasta 'install' Automaticamente
                        </a>
                        <span class="text-[11px] text-rose-600 self-center">ou remova manualmente no servidor.</span>
                    </div>
                </div>

                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-3 text-xs">
                    <h4 class="font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                        <i class="fas fa-key text-indigo-600"></i> Resumo da Configuração:
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div>
                            <span class="text-slate-400 block">E-mail do Administrador:</span>
                            <span class="font-mono font-bold text-slate-800 text-sm"><?= htmlspecialchars($successData['adminEmail']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Usuário MySQL Exclusivo:</span>
                            <span class="font-mono font-bold text-slate-800 text-sm"><?= htmlspecialchars($successData['dbUser']) ?></span>
                        </div>
                    </div>
                    <div class="pt-2 border-t border-slate-200 text-[11px] text-slate-500">
                        Banco vinculado: <strong><?= htmlspecialchars($successData['dbName']) ?></strong> em <strong><?= htmlspecialchars($successData['dbHost']) ?>:<?= htmlspecialchars($successData['dbPort']) ?></strong>.
                        <span class="block mt-1 text-amber-700 font-semibold">Próximo passo: ative a chave de licença na tela inicial para liberar o sistema.</span>
                    </div>
                </div>

                <div class="text-center pt-2">
                    <a href="<?= htmlspecialchars($loginUrl) ?>" class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold shadow-lg transition">
                        <i class="fas fa-sign-in-alt"></i> Ir para o Sistema
                    </a>
                </div>
            </div>

        <?php elseif ($step === 'already_installed'): ?>
            <div class="bg-white rounded-3xl p-8 border border-slate-100 shadow-2xl space-y-6 text-center">
                <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto text-2xl mb-3 shadow-inner">
                    <i class="fas fa-lock"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-900 tracking-tight">Sistema Já Instalado!</h2>
                <p class="text-sm text-slate-500">O arquivo de configuração já foi gravado e o instalador está bloqueado.</p>
                <div class="p-5 rounded-2xl bg-rose-50 border-2 border-rose-200 text-rose-900 text-left space-y-2">
                    <div class="font-bold flex items-center gap-2 text-rose-800">
                        <i class="fas fa-exclamation-circle"></i> Apague a pasta <code>install</code> do servidor!
                    </div>
                    <div class="pt-2">
                        <a href="?action=delete_install" onclick="return confirm('Confirma a exclusão da pasta install?');" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition">
                            <i class="fas fa-trash-alt"></i> Excluir pasta install agora
                        </a>
                    </div>
                </div>
                <a href="<?= htmlspecialchars($loginUrl) ?>" class="inline-block px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md transition">
                    Acessar Tela de Login
                </a>
            </div>

        <?php else: ?>
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-100 shadow-2xl space-y-6">
                <!-- Checklist -->
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-2 text-xs">
                    <h3 class="font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                        <i class="fas fa-server text-indigo-600"></i> Verificação de Requisitos
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-slate-600">
                        <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200">
                            <span>PHP &ge; 8.2 (Atual: <?= PHP_VERSION ?>)</span>
                            <i class="fas <?= $phpVersionOk ? 'fa-check-circle text-emerald-500' : 'fa-times-circle text-rose-500' ?>"></i>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200">
                            <span>PDO MySQL & OpenSSL</span>
                            <i class="fas <?= ($pdoLoaded && $pdoMysqlLoaded && $opensslLoaded) ? 'fa-check-circle text-emerald-500' : 'fa-times-circle text-rose-500' ?>"></i>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200">
                            <span>Escrita em /config</span>
                            <i class="fas <?= $configWritable ? 'fa-check-circle text-emerald-500' : 'fa-times-circle text-rose-500' ?>"></i>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200">
                            <span>Esquema database.sql</span>
                            <i class="fas <?= $sqlExists ? 'fa-check-circle text-emerald-500' : 'fa-times-circle text-rose-500' ?>"></i>
                        </div>
                    </div>
                </div>

                <!-- Alerta de Segurança de Usuário Exclusivo MySQL -->
                <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-950 text-xs space-y-2">
                    <div class="font-bold flex items-center gap-2 text-amber-900">
                        <i class="fas fa-database text-amber-600"></i> Pré-requisito Obrigatório de Segurança no MySQL
                    </div>
                    <p class="leading-relaxed">
                        Por segurança, o sistema <strong>não aceita usuário <code>root</code> nem banco sem senha</strong>. Antes de continuar, crie no seu MySQL um usuário dedicado com senha forte vinculado <strong>exclusivamente</strong> ao banco do sistema:
                    </p>
                    <pre id="sqlGuideBox" class="p-3 rounded-xl bg-slate-900 text-emerald-300 font-mono text-[11px] overflow-x-auto select-all"></pre>
                </div>

                <form method="POST" action="" class="space-y-6">
                    <!-- Seção 1: Banco MySQL -->
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-100 pb-2">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px]">1</span>
                            Credenciais Exclusivas do Banco MySQL
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Host do MySQL <span class="text-rose-500">*</span></label>
                                <input type="text" name="db_host" id="dbHostInput" required value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1') ?>" class="w-full text-sm rounded-xl" placeholder="127.0.0.1">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Porta <span class="text-rose-500">*</span></label>
                                <input type="text" name="db_port" required value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" class="w-full text-sm rounded-xl" placeholder="3306">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome do Banco de Dados <span class="text-rose-500">*</span></label>
                            <input type="text" name="db_name" id="dbNameInput" required value="<?= htmlspecialchars($_POST['db_name'] ?? 'reqmaster_db') ?>" oninput="updateSqlGuide()" class="w-full text-sm rounded-xl" placeholder="reqmaster_db">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Usuário Exclusivo MySQL <span class="text-rose-500">*</span></label>
                                <input type="text" name="db_user" id="dbUserInput" required value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" oninput="updateSqlGuide()" class="w-full text-sm rounded-xl" placeholder="Ex: reqmaster_usr (nunca root)">
                            </div>
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <label class="block text-xs font-semibold text-slate-700 uppercase">Senha Forte do MySQL <span class="text-rose-500">*</span></label>
                                    <button type="button" onclick="generateStrongPwd('dbPassInput', 'dbPwdMeter'); updateSqlGuide();" class="text-[10px] font-bold text-indigo-600 hover:underline">Sugerir Senha</button>
                                </div>
                                <input type="password" name="db_pass" id="dbPassInput" required value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" oninput="checkPwdMeter(this.value, 'dbPwdMeter'); updateSqlGuide();" class="w-full text-sm rounded-xl" placeholder="Mín. 8 chars, A-z, 0-9 e símbolo">
                                <div id="dbPwdMeter" class="text-[10px] mt-1 font-medium text-slate-400">Obrigatória senha forte (maiúscula, minúscula, número e símbolo).</div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção 2: Administrador Inicial -->
                    <div class="space-y-4 pt-2">
                        <h3 class="text-xs font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-100 pb-2">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px]">2</span>
                            Conta do Administrador Principal
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome Completo <span class="text-rose-500">*</span></label>
                                <input type="text" name="admin_name" required value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" class="w-full text-sm rounded-xl" placeholder="Administrador">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">E-mail de Acesso <span class="text-rose-500">*</span></label>
                                <input type="email" name="admin_email" required value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" class="w-full text-sm rounded-xl" placeholder="admin@seudominio.com.br">
                            </div>
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <label class="block text-xs font-semibold text-slate-700 uppercase">Senha Forte <span class="text-rose-500">*</span></label>
                                    <button type="button" onclick="generateStrongPwd('adminPassInput', 'admPwdMeter')" class="text-[10px] font-bold text-indigo-600 hover:underline">Sugerir</button>
                                </div>
                                <input type="password" name="admin_password" id="adminPassInput" required oninput="checkPwdMeter(this.value, 'admPwdMeter')" class="w-full text-sm rounded-xl" placeholder="Senha forte do Admin">
                                <div id="admPwdMeter" class="text-[10px] mt-1 font-medium text-slate-400">Mín. 8 chars (A-z, 0-9 e !@#$).</div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <button type="submit" name="install_submit" <?= !$canInstall ? 'disabled' : '' ?> class="w-full py-3.5 px-4 rounded-2xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                            <i class="fas fa-shield-alt"></i> Validar Usuário Exclusivo & Instalar Banco
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="text-center mt-6 text-xs text-slate-500">
            ReqMaster &bull; Instalador Seguro em PHP 8.5 & MySQL
        </div>
    </div>

    <script>
        function updateSqlGuide() {
            const box = document.getElementById('sqlGuideBox');
            if (!box) return;
            const db = (document.getElementById('dbNameInput')?.value || 'reqmaster_db').trim() || 'reqmaster_db';
            const usr = (document.getElementById('dbUserInput')?.value || 'reqmaster_usr').trim() || 'reqmaster_usr';
            const host = (document.getElementById('dbHostInput')?.value || '127.0.0.1').trim() || '127.0.0.1';
            const pwd = (document.getElementById('dbPassInput')?.value || 'SuaSenhaForte@2026!');

            box.textContent =
`CREATE DATABASE IF NOT EXISTS \`${db}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '${usr}'@'${host}' IDENTIFIED BY '${pwd}';
GRANT ALL PRIVILEGES ON \`${db}\`.* TO '${usr}'@'${host}';
FLUSH PRIVILEGES;`;
        }

        function checkPwdMeter(val, targetId) {
            const el = document.getElementById(targetId);
            if (!el) return;
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[a-z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^a-zA-Z0-9]/.test(val)) score++;

            if (!val) {
                el.className = 'text-[10px] mt-1 font-medium text-slate-400';
                el.textContent = 'Obrigatória senha forte.';
            } else if (score < 5) {
                el.className = 'text-[10px] mt-1 font-semibold text-rose-600';
                el.textContent = `Senha fraca (${score}/5 requisitos).`;
            } else {
                el.className = 'text-[10px] mt-1 font-bold text-emerald-600';
                el.textContent = '✓ Senha forte aprovada!';
            }
        }

        function generateStrongPwd(inputId, meterId) {
            const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            const lower = 'abcdefghijkmnopqrstuvwxyz';
            const nums  = '23456789';
            const syms  = '!@#$%&*+?';
            const all   = upper + lower + nums + syms;
            let pwd = upper[Math.floor(Math.random()*upper.length)]
                    + lower[Math.floor(Math.random()*lower.length)]
                    + nums[Math.floor(Math.random()*nums.length)]
                    + syms[Math.floor(Math.random()*syms.length)];
            for (let i = 0; i < 12; i++) {
                pwd += all[Math.floor(Math.random() * all.length)];
            }
            const inp = document.getElementById(inputId);
            inp.type = 'text';
            inp.value = pwd;
            checkPwdMeter(pwd, meterId);
        }

        document.addEventListener('DOMContentLoaded', updateSqlGuide);
    </script>
</body>
</html>
