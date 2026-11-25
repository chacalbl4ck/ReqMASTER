<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            // 1. Tenta carregar o arquivo gerado pelo instalador automatizado
            $configFile = __DIR__ . '/db_config.php';
            $installerConfig = file_exists($configFile) ? require $configFile : [];

            $host = $installerConfig['host'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
            $port = $installerConfig['port'] ?? $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
            $dbname = $installerConfig['dbname'] ?? $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'sistema_requisicoes';
            $user = $installerConfig['user'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
            $pass = $installerConfig['pass'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Compatibilidade com PHP 8.5+ e versões anteriores para opções MySQL
            if (defined('Pdo\Mysql::ATTR_INIT_COMMAND')) {
                $options[\Pdo\Mysql::ATTR_INIT_COMMAND] = "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci";
            } elseif (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[@constant('PDO::MYSQL_ATTR_INIT_COMMAND')] = "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci";
            }

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
                // Garante a codificação utf8mb4 sem depender de constantes legadas
                self::$instance->exec("SET NAMES {$charset} COLLATE utf8mb4_unicode_ci");
            } catch (PDOException $e) {
                // Verifica se a pasta install ainda existe para sugerir o setup automatizado
                $installExists = is_dir(__DIR__ . '/../install');
                $installUrl = function_exists('install_url') ? install_url() : '../install/';

                die("<div style='font-family:Segoe UI,sans-serif;max-width:620px;margin:50px auto;padding:28px;border:1px solid #fca5a5;border-radius:16px;background:#fff;color:#1e293b;box-shadow:0 10px 25px rgba(0,0,0,0.08);'>
                    <div style='display:flex;align-items:center;gap:12px;margin-bottom:16px;'>
                        <div style='width:42px;height:42px;border-radius:10px;background:#fee2e2;color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:bold;'>⚠️</div>
                        <div>
                            <h2 style='margin:0;font-size:18px;color:#991b1b;'>Erro de Conexão com o Banco MySQL</h2>
                            <p style='margin:2px 0 0;font-size:12px;color:#64748b;'>Não foi possível conectar ao banco de dados informado.</p>
                        </div>
                    </div>
                    
                    <p style='font-size:13px;line-height:1.5;background:#fef2f2;padding:12px;border-radius:8px;border:1px solid #fecaca;color:#991b1b;font-family:monospace;margin:0 0 16px 0;word-break:break-all;'>
                        " . htmlspecialchars($e->getMessage()) . "
                    </p>

                    " . ($installExists ? "
                    <div style='background:#f0fdf4;border:1px solid #bbf7d0;padding:16px;border-radius:12px;margin-bottom:18px;'>
                        <strong style='color:#166534;font-size:13px;display:block;margin-bottom:4px;'>🚀 Assistente de Setup Automatizado Disponível:</strong>
                        <p style='font-size:12px;color:#15803d;margin:0 0 10px 0;'>Você pode criar o banco de dados e as tabelas com 1 clique utilizando nosso assistente visual.</p>
                        <a href='{$installUrl}' style='display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:bold;'>Iniciar Setup Automatizado Agora &rarr;</a>
                    </div>" : "") . "

                    <div style='font-size:12px;color:#64748b;line-height:1.6;'>
                        <strong>Ou resolva manualmente:</strong>
                        <ul style='margin:6px 0 0;padding-left:18px;'>
                            <li>Verifique se o serviço MySQL está em execução no seu XAMPP, Laragon ou Docker.</li>
                            <li>Confirme se o banco <code>{$dbname}</code> existe e se o usuário <code>{$user}</code> tem acesso.</li>
                        </ul>
                    </div>
                </div>");
            }
        }

        return self::$instance;
    }
}
