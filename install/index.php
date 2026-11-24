<?php
declare(strict_types=1);

// ========================================================
// REQMASTER - INSTALADOR & SETUP AUTOMATIZADO DO BANCO
// Totalmente compatível com PHP 8.2, 8.4 e PHP 8.5+ (Pdo\Mysql)
// ========================================================

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

$configFile = __DIR__ . '/../config/db_config.php';
$lockFile = __DIR__ . '/installed.lock';
$databaseSqlFile = __DIR__ . '/../database.sql';

// --------------------------------------------------------
// COMPATIBILIDADE DE CONSTANTES MYSQL ENTRE PHP 8.2 E PHP 8.5+
// --------------------------------------------------------
// No PHP 8.5, as constantes PDO::MYSQL_ATTR_* foram descontinuadas em favor de Pdo\Mysql::ATTR_*
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

// --------------------------------------------------------
// DETECÇÃO DINÂMICA DA URL BASE & LOGIN
// --------------------------------------------------------
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';

// Remove /install ou /install/index.php da URL atual para obter o caminho raiz do projeto
$basePath = preg_replace('/\/install(\/index\.php)?.*$/i', '', $parsedPath);
$basePath = rtrim($basePath, '/');

// Verifica se a chamada foi feita através de /public/install
if (str_contains($parsedPath, '/public/install')) {
    if (!str_ends_with($basePath, '/public')) {
        $basePath .= '/public';
    }
}

$baseAppUrl = rtrim($protocol . $host . $basePath, '/');
$loginUrl = ($baseAppUrl ?: '') . '/login';

// --------------------------------------------------------
// AÇÃO: APAGAR A PASTA INSTALL AUTOMATICAMENTE
// --------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete_install') {
    $installDir = __DIR__;
    
    // 1. Remove todos os arquivos da pasta, exceto index.php temporariamente
    if (is_dir($installDir)) {
        $files = array_diff(scandir($installDir), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $installDir . DIRECTORY_SEPARATOR . $file;
            if ($file !== 'index.php') {
                if (is_dir($filePath)) {
                    // Limpa subdiretórios se houver
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

    // 2. Tenta remover o próprio index.php e o diretório install
    $unlinked = @unlink(__FILE__);
    $dirRemoved = $unlinked ? @rmdir($installDir) : false;

    // 3. No Windows, quando o Apache mantém o arquivo aberto durante a execução do script,
    // dispara um processo em background com atraso de 1 segundo para remover a pasta após o fechamento da requisição HTTP.
    if (!$dirRemoved && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @pclose(@popen("start /B cmd /C ping 127.0.0.1 -n 2 > nul & rmdir /S /Q \"{$installDir}\"", "r"));
        $dirRemoved = true;
    }

    // Redireciona para o login
    header("Location: " . $loginUrl . (str_contains($loginUrl, '?') ? '&' : '?') . "msg=install_deleted");
    exit;
}

// --------------------------------------------------------
// VERIFICAÇÃO DE REQUISITOS DO SISTEMA
// --------------------------------------------------------
$phpVersionOk = version_compare(PHP_VERSION, '8.2.0', '>=');
$pdoLoaded = extension_loaded('pdo');
$pdoMysqlLoaded = extension_loaded('pdo_mysql');
$configWritable = is_writable(__DIR__ . '/../config');
$sqlExists = file_exists($databaseSqlFile);

$canInstall = $phpVersionOk && $pdoLoaded && $pdoMysqlLoaded && $configWritable && $sqlExists;

$step = 'form';
$errorMessage = '';
$successData = null;

// Se já houver lock de instalação e não for submissão, exibe aviso de segurança
if (file_exists($lockFile) && !isset($_POST['install_submit'])) {
    $step = 'already_installed';
}

// --------------------------------------------------------
// PROCESSAMENTO DO FORMULÁRIO DE INSTALAÇÃO
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_submit'])) {
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? 'sistema_requisicoes');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = $_POST['db_pass'] ?? '';
    $createDb = isset($_POST['create_db']);
    $importSeed = isset($_POST['import_seed']);

    $adminName = trim($_POST['admin_name'] ?? 'Administrador do Sistema');
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@empresa.com');
    $adminPass = trim($_POST['admin_password'] ?? 'admin123');

    if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
        $errorMessage = "Por favor, preencha todos os campos obrigatórios do banco de dados.";
    } else {
        try {
            // 1. Testa a conexão com o servidor MySQL (sem especificar banco ainda)
            $dsnServer = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $serverOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ];
            if ($mysqlInitCmd !== null) {
                $serverOptions[$mysqlInitCmd] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
            }

            $pdoServer = new PDO($dsnServer, $dbUser, $dbPass, $serverOptions);
            $pdoServer->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            // 2. Cria o banco de dados se solicitado ou se não existir
            if ($createDb) {
                $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            }

            // 3. Conecta diretamente no banco selecionado
            $dsnDb = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $dbOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ];
            if ($mysqlInitCmd !== null) {
                $dbOptions[$mysqlInitCmd] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
            }
            if ($mysqlMultiStmt !== null) {
                $dbOptions[$mysqlMultiStmt] = true;
            }

            $pdo = new PDO($dsnDb, $dbUser, $dbPass, $dbOptions);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            // 4. Lê o arquivo SQL e ajusta para o nome do banco informado
            $sqlContent = file_get_contents($databaseSqlFile);
            if ($sqlContent === false) {
                throw new Exception("Não foi possível ler o arquivo database.sql.");
            }

            // Substitui referências de 'sistema_requisicoes' pelo banco customizado
            $sqlContent = str_replace('`sistema_requisicoes`', "`{$dbName}`", $sqlContent);
            $sqlContent = str_replace('USE `sistema_requisicoes`;', "USE `{$dbName}`;", $sqlContent);

            // Se o usuário desmarcou dados de teste (seed), mantém apenas a estrutura
            if (!$importSeed) {
                $seedPos = strpos($sqlContent, 'CARGA DE DADOS INICIAIS (SEED)');
                if ($seedPos !== false) {
                    $sqlContent = substr($sqlContent, 0, $seedPos);
                }
            }

            // Executa o script SQL
            $pdo->exec($sqlContent);

            // 5. Garante a criação ou atualização do Administrador informado
            $hashedPassword = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmtUserCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmtUserCheck->execute(['email' => $adminEmail]);
            $existingUserId = $stmtUserCheck->fetchColumn();

            if ($existingUserId) {
                $stmtUp = $pdo->prepare("UPDATE users SET name = :name, password = :pwd, role = 'admin', active = 1 WHERE id = :id");
                $stmtUp->execute([
                    'name' => $adminName,
                    'pwd'  => $hashedPassword,
                    'id'   => $existingUserId
                ]);
            } else {
                $stmtIns = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, department, active) 
                    VALUES (:name, :email, :pwd, 'admin', 'Diretoria / TI', 1)
                ");
                $stmtIns->execute([
                    'name'  => $adminName,
                    'email' => $adminEmail,
                    'pwd'   => $hashedPassword
                ]);
            }

            // 6. Grava o arquivo de configuração de banco: config/db_config.php
            $configPhp = "<?php\ndeclare(strict_types=1);\n\n// Gerado automaticamente pelo Setup em " . date('Y-m-d H:i:s') . "\nreturn [\n"
                . "    'host'     => " . var_export($dbHost, true) . ",\n"
                . "    'port'     => " . var_export($dbPort, true) . ",\n"
                . "    'dbname'   => " . var_export($dbName, true) . ",\n"
                . "    'user'     => " . var_export($dbUser, true) . ",\n"
                . "    'pass'     => " . var_export($dbPass, true) . ",\n"
                . "    'installed_at' => " . var_export(date('Y-m-d H:i:s'), true) . ",\n"
                . "];\n";

            file_put_contents($configFile, $configPhp, LOCK_EX);

            // 7. Cria o arquivo de bloqueio para impedir novas execuções
            file_put_contents($lockFile, "Installed on " . date('c'));

            $step = 'success';
            $successData = [
                'dbHost' => $dbHost,
                'dbPort' => $dbPort,
                'dbName' => $dbName,
                'adminEmail' => $adminEmail,
                'adminPass' => $adminPass,
            ];

        } catch (PDOException $e) {
            $errorMessage = "Erro na conexão ou execução MySQL: " . $e->getMessage();
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
    <title>Instalador & Setup do Banco de Dados - ReqMaster</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="min-h-full py-10 px-4 flex items-center justify-center text-slate-800">
    <div class="max-w-2xl w-full">

        <!-- Logotipo e Cabeçalho do Setup -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white shadow-xl shadow-indigo-500/30 mb-3">
                <i class="fas fa-magic text-2xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Req<span class="text-indigo-400">Master</span> Setup</h1>
            <p class="text-sm text-slate-400 mt-1">Assistente Automatizado de Instalação e Criação do Banco MySQL</p>
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

        <!-- ========================================================= -->
        <!-- CASO 1: INSTALAÇÃO CONCLUÍDA COM SUCESSO                  -->
        <!-- ========================================================= -->
        <?php if ($step === 'success'): ?>
            <div class="bg-white rounded-3xl p-8 border border-slate-100 shadow-2xl space-y-6">
                <!-- Ícone de Sucesso -->
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-2xl mb-3 shadow-inner">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">Banco de Dados Configurado com Sucesso!</h2>
                    <p class="text-sm text-slate-500 mt-1">O esquema relacional, tabelas e dados iniciais foram criados perfeitamente no MySQL.</p>
                </div>

                <!-- ALERTA CRÍTICO: APAGAR A PASTA INSTALL -->
                <div class="p-6 rounded-2xl bg-rose-50 border-2 border-rose-300 text-rose-950 space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-rose-600 text-white flex items-center justify-center text-lg shrink-0 shadow-md">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold uppercase tracking-wider text-rose-900">Ação Obrigatória de Segurança</h3>
                            <p class="text-xs text-rose-700">Por segurança, você <strong>DEVE APAGAR A PASTA DE INSTALAÇÃO</strong> agora!</p>
                        </div>
                    </div>
                    <p class="text-xs text-rose-800 leading-relaxed">
                        Manter a pasta <code>install</code> no servidor permite que qualquer pessoa execute o instalador novamente e sobrescreva suas tabelas e dados.
                    </p>

                    <div class="bg-white p-3 rounded-xl border border-rose-200 text-xs font-mono text-slate-700 break-all select-all">
                        <span>Pasta a remover: <strong><?= htmlspecialchars(__DIR__) ?></strong></span>
                    </div>

                    <!-- Botão de Remoção Automatizada da Pasta Install -->
                    <div class="pt-2 flex flex-col sm:flex-row gap-2">
                        <a href="?action=delete_install" onclick="return confirm('Confirma a exclusão da pasta install para segurança do sistema?');" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md shadow-rose-600/30 transition">
                            <i class="fas fa-trash-alt"></i> Apagar Pasta 'install' Automaticamente
                        </a>
                        <span class="text-[11px] text-rose-600 self-center">ou delete a pasta <code>install</code> manualmente no seu computador.</span>
                    </div>
                </div>

                <!-- Resumo dos Dados de Acesso -->
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-3 text-xs">
                    <h4 class="font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                        <i class="fas fa-key text-indigo-600"></i> Credenciais do Administrador Configurado:
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div>
                            <span class="text-slate-400 block">E-mail de Login:</span>
                            <span class="font-mono font-bold text-slate-800 text-sm"><?= htmlspecialchars($successData['adminEmail']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Senha:</span>
                            <span class="font-mono font-bold text-slate-800 text-sm"><?= htmlspecialchars($successData['adminPass']) ?></span>
                        </div>
                    </div>
                    <div class="pt-2 border-t border-slate-200 text-[11px] text-slate-500">
                        Banco criado: <strong><?= htmlspecialchars($successData['dbName']) ?></strong> no host <strong><?= htmlspecialchars($successData['dbHost']) ?>:<?= htmlspecialchars($successData['dbPort']) ?></strong>
                    </div>
                </div>

                <!-- Botão de Acesso ao Sistema -->
                <div class="text-center pt-2">
                    <a href="<?= htmlspecialchars($loginUrl) ?>" class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold shadow-lg shadow-indigo-600/30 transition">
                        <i class="fas fa-sign-in-alt"></i> Ir para a Tela de Login do ReqMaster
                    </a>
                </div>
            </div>

        <!-- ========================================================= -->
        <!-- CASO 2: SISTEMA JÁ INSTALADO (LOCK ATIVO)                 -->
        <!-- ========================================================= -->
        <?php elseif ($step === 'already_installed'): ?>
            <div class="bg-white rounded-3xl p-8 border border-slate-100 shadow-2xl space-y-6 text-center">
                <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto text-2xl mb-3 shadow-inner">
                    <i class="fas fa-lock"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-900 tracking-tight">O Sistema Já Está Instalado!</h2>
                <p class="text-sm text-slate-500">
                    O arquivo de configuração do banco de dados já existe e o instalador está bloqueado.
                </p>

                <!-- ALERTA PARA APAGAR A PASTA -->
                <div class="p-5 rounded-2xl bg-rose-50 border-2 border-rose-200 text-rose-900 text-left space-y-2">
                    <div class="font-bold flex items-center gap-2 text-rose-800">
                        <i class="fas fa-exclamation-circle text-base"></i> ATENÇÃO DE SEGURANÇA:
                    </div>
                    <p class="text-xs text-rose-700">
                        Para sua proteção, apague a pasta <code>install</code> do seu servidor.
                    </p>
                    <div class="bg-white p-2.5 rounded-lg border border-rose-200 text-xs font-mono text-slate-600 break-all select-all">
                        <?= htmlspecialchars(__DIR__) ?>
                    </div>
                    <div class="pt-2">
                        <a href="?action=delete_install" onclick="return confirm('Confirma a exclusão da pasta install?');" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition">
                            <i class="fas fa-trash-alt"></i> Excluir pasta install agora
                        </a>
                    </div>
                </div>

                <div class="pt-4 flex flex-col sm:flex-row justify-center gap-3">
                    <a href="<?= htmlspecialchars($loginUrl) ?>" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-md transition">
                        <i class="fas fa-arrow-right mr-1"></i> Acessar Tela de Login
                    </a>
                </div>
            </div>

        <!-- ========================================================= -->
        <!-- CASO 3: FORMULÁRIO DO SETUP                               -->
        <!-- ========================================================= -->
        <?php else: ?>
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-100 shadow-2xl space-y-6">
                <!-- Checklist de Requisitos -->
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-2 text-xs">
                    <h3 class="font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                        <i class="fas fa-server text-indigo-600"></i> Verificação de Requisitos do Servidor
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-slate-600">
                        <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200">
                            <span>PHP &ge; 8.2 (Atual: <?= PHP_VERSION ?>)</span>
                            <i class="fas <?= $phpVersionOk ? 'fa-check-circle text-emerald-500' : 'fa-times-circle text-rose-500' ?>"></i>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200">
                            <span>Extensão PDO & MySQL</span>
                            <i class="fas <?= ($pdoLoaded && $pdoMysqlLoaded) ? 'fa-check-circle text-emerald-500' : 'fa-times-circle text-rose-500' ?>"></i>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200">
                            <span>Permissão de Escrita em /config</span>
                            <i class="fas <?= $configWritable ? 'fa-check-circle text-emerald-500' : 'fa-times-circle text-rose-500' ?>"></i>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200">
                            <span>Arquivo database.sql Encontrado</span>
                            <i class="fas <?= $sqlExists ? 'fa-check-circle text-emerald-500' : 'fa-times-circle text-rose-500' ?>"></i>
                        </div>
                    </div>

                    <?php if (!$canInstall): ?>
                        <div class="p-2 rounded bg-rose-50 text-rose-700 text-[11px] font-medium mt-2">
                            ⚠️ Um ou mais requisitos não foram atendidos. Verifique as extensões do seu PHP antes de prosseguir.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Formulário de Parâmetros de Conexão -->
                <form method="POST" action="" class="space-y-6">
                    <!-- Seção 1: Conexão MySQL -->
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-100 pb-2">
                            <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px]">1</span>
                            Conexão com o Banco de Dados MySQL
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Host do MySQL <span class="text-rose-500">*</span></label>
                                <input type="text" name="db_host" required value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="127.0.0.1 ou localhost">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Porta <span class="text-rose-500">*</span></label>
                                <input type="text" name="db_port" required value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="3306">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome do Banco de Dados <span class="text-rose-500">*</span></label>
                            <input type="text" name="db_name" required value="<?= htmlspecialchars($_POST['db_name'] ?? 'sistema_requisicoes') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="sistema_requisicoes">
                            <span class="text-[11px] text-slate-400 mt-1 block">O banco de dados será criado automaticamente se ainda não existir.</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Usuário do MySQL <span class="text-rose-500">*</span></label>
                                <input type="text" name="db_user" required value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="root">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Senha do MySQL</label>
                                <input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Deixe em branco se não houver senha">
                            </div>
                        </div>

                        <!-- Checkboxes de Automação -->
                        <div class="pt-2 space-y-2 text-xs">
                            <label class="flex items-center text-slate-700 cursor-pointer">
                                <input type="checkbox" name="create_db" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mr-2">
                                <span><strong>Criar o banco de dados automaticamente</strong> se ele não existir</span>
                            </label>

                            <label class="flex items-center text-slate-700 cursor-pointer">
                                <input type="checkbox" name="import_seed" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mr-2">
                                <span><strong>Importar dados de demonstração (seed)</strong> (fornecedores de exemplo, contratos e requisições)</span>
                            </label>
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
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Nome do Admin</label>
                                <input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Administrador do Sistema') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">E-mail de Acesso <span class="text-rose-500">*</span></label>
                                <input type="email" name="admin_email" required value="<?= htmlspecialchars($_POST['admin_email'] ?? 'admin@empresa.com') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Senha de Acesso <span class="text-rose-500">*</span></label>
                                <input type="text" name="admin_password" required value="<?= htmlspecialchars($_POST['admin_password'] ?? 'admin123') ?>" class="w-full text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Botão de Execução do Setup -->
                    <div class="pt-4 border-t border-slate-100">
                        <button type="submit" name="install_submit" <?= !$canInstall ? 'disabled' : '' ?> class="w-full py-3.5 px-4 rounded-2xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-600/30 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                            <i class="fas fa-play"></i> Iniciar Criação do Banco & Configuração
                        </button>
                        <p class="text-[11px] text-slate-400 text-center mt-2">
                            O processo criará o banco MySQL, importará as tabelas e salvará a configuração automaticamente.
                        </p>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Rodapé -->
        <div class="text-center mt-6 text-xs text-slate-500">
            ReqMaster &bull; Setup Automatizado em PHP 8.5 & MySQL
        </div>
    </div>
</body>
</html>
