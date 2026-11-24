<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Exception;

class MailService
{
    private Setting $setting;

    public function __construct()
    {
        $this->setting = new Setting();
    }

    public function isConfigured(): bool
    {
        $host = $this->setting->get('smtp_host');
        $user = $this->setting->get('smtp_user');
        return !empty($host) && !empty($user);
    }

    public function isNotificationsEnabled(): bool
    {
        return $this->setting->get('email_notifications', '0') === '1' && $this->isConfigured();
    }

    public function send(string $to, string $toName, string $subject, string $htmlBody): bool
    {
        $host     = $this->setting->get('smtp_host', '');
        $port     = (int)$this->setting->get('smtp_port', '587');
        $user     = $this->setting->get('smtp_user', '');
        $pass     = $this->setting->get('smtp_pass', '');
        $secure   = strtolower($this->setting->get('smtp_secure', 'tls'));
        $fromEmail = $this->setting->get('smtp_from_email', $user);
        $fromName  = $this->setting->get('smtp_from_name', $this->setting->get('company_name', 'ReqMaster'));

        if (empty($host) || empty($fromEmail)) {
            // Se SMTP não estiver preenchido, tenta usar mail() nativo
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=utf-8\r\n";
            $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
            return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
        }

        return $this->sendViaSmtp($host, $port, $user, $pass, $secure, $fromEmail, $fromName, $to, $toName, $subject, $htmlBody);
    }

    private function sendViaSmtp(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $secure,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody
    ): bool {
        $timeout = 15;
        $remote = $host;
        if ($secure === 'ssl') {
            $remote = 'ssl://' . $host;
        }

        $socket = @fsockopen($remote, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            throw new Exception("Falha ao conectar no servidor SMTP {$host}:{$port} ({$errno} - {$errstr})");
        }

        $getResponse = function () use ($socket): string {
            $response = '';
            while ($str = fgets($socket, 515)) {
                $response .= $str;
                if (substr($str, 3, 1) === ' ') {
                    break;
                }
            }
            return $response;
        };

        $sendCommand = function (string $cmd) use ($socket, $getResponse): string {
            fputs($socket, $cmd . "\r\n");
            return $getResponse();
        };

        $greeting = $getResponse();
        if (!str_starts_with($greeting, '220')) {
            fclose($socket);
            throw new Exception("Servidor SMTP rejeitou conexão: " . trim($greeting));
        }

        $clientHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $ehlo = $sendCommand("EHLO {$clientHost}");

        // Se TLS for requerido, inicia STARTTLS
        if ($secure === 'tls') {
            $startTls = $sendCommand("STARTTLS");
            if (!str_starts_with($startTls, '220')) {
                fclose($socket);
                throw new Exception("Falha ao iniciar STARTTLS: " . trim($startTls));
            }
            // Habilita criptografia no stream socket
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            if (!$crypto) {
                fclose($socket);
                throw new Exception("Não foi possível negociar criptografia TLS com o servidor.");
            }
            $sendCommand("EHLO {$clientHost}");
        }

        // Autenticação SMTP
        if (!empty($user)) {
            $auth = $sendCommand("AUTH LOGIN");
            if (!str_starts_with($auth, '334')) {
                fclose($socket);
                throw new Exception("Servidor não aceitou AUTH LOGIN: " . trim($auth));
            }
            $userRes = $sendCommand(base64_encode($user));
            if (!str_starts_with($userRes, '334')) {
                fclose($socket);
                throw new Exception("Usuário SMTP rejeitado: " . trim($userRes));
            }
            $passRes = $sendCommand(base64_encode($pass));
            if (!str_starts_with($passRes, '235')) {
                fclose($socket);
                throw new Exception("Senha SMTP rejeitada ou incorreta.");
            }
        }

        // Remetente e Destinatário
        $mailFrom = $sendCommand("MAIL FROM: <{$fromEmail}>");
        if (!str_starts_with($mailFrom, '250')) {
            fclose($socket);
            throw new Exception("Erro no comando MAIL FROM: " . trim($mailFrom));
        }

        $rcptTo = $sendCommand("RCPT TO: <{$toEmail}>");
        if (!str_starts_with($rcptTo, '250') && !str_starts_with($rcptTo, '251')) {
            fclose($socket);
            throw new Exception("Erro no comando RCPT TO ({$toEmail}): " . trim($rcptTo));
        }

        // Conteúdo
        $dataCmd = $sendCommand("DATA");
        if (!str_starts_with($dataCmd, '354')) {
            fclose($socket);
            throw new Exception("Erro ao iniciar envio de dados: " . trim($dataCmd));
        }

        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
            "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date('r'),
            "X-Mailer: ReqMaster Mailer",
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
        $sendData = $sendCommand($message);
        $sendCommand("QUIT");
        fclose($socket);

        return str_starts_with($sendData, '250');
    }

    public function notifyNewRequisition(array $requisition, array $approvers): void
    {
        if (!$this->isNotificationsEnabled()) {
            return;
        }

        $companyName = $this->setting->get('company_name', 'Nossa Empresa');
        $subject = "Nova Requisição Pendente de Aprovação: {$requisition['code']} - {$requisition['title']}";
        
        $body = "
        <div style='font-family:sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;'>
            <div style='border-bottom:2px solid #4f46e5;padding-bottom:12px;margin-bottom:15px;'>
                <h2 style='color:#1e293b;margin:0;'>Nova Requisição Cadastrada</h2>
                <p style='color:#64748b;font-size:13px;margin:3px 0 0;'>Empresa: <strong>{$companyName}</strong></p>
            </div>
            <p style='font-size:14px;color:#334155;'>Uma nova requisição foi registrada e aguarda sua análise:</p>
            <ul style='font-size:13px;color:#334155;line-height:1.6;'>
                <li><strong>Código:</strong> {$requisition['code']}</li>
                <li><strong>Título:</strong> {$requisition['title']}</li>
                <li><strong>Tipo:</strong> " . ($requisition['type'] === 'servico' ? 'Prestação de Serviço' : 'Compra de Material') . "</li>
                <li><strong>Solicitante:</strong> {$requisition['requester_name']} ({$requisition['department']})</li>
                <li><strong>Prioridade:</strong> " . strtoupper($requisition['priority']) . "</li>
                " . (!empty($requisition['has_values']) ? "<li><strong>Valor Total Estimado:</strong> " . formatMoney($requisition['total_amount']) . "</li>" : "") . "
            </ul>
            <div style='margin-top:20px;'>
                <a href='" . url('requisicoes/ver?id=' . $requisition['id']) . "' style='background:#4f46e5;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:bold;display:inline-block;'>
                    Visualizar e Avaliar Requisição &rarr;
                </a>
            </div>
        </div>";

        foreach ($approvers as $appr) {
            if (!empty($appr['email'])) {
                try {
                    $this->send($appr['email'], $appr['name'], $subject, $body);
                } catch (Exception $e) {
                    // Silently continue
                }
            }
        }
    }

    public function notifyStatusChange(array $requisition, string $newStatus, ?string $reason = null): void
    {
        if (!$this->isNotificationsEnabled()) {
            return;
        }

        $requesterEmail = $requisition['requester_email'] ?? '';
        $requesterName  = $requisition['requester_name'] ?? 'Solicitante';

        if (empty($requesterEmail)) {
            return;
        }

        $statusTitles = [
            'aprovada'    => 'Aprovada ✅',
            'rejeitada'   => 'Rejeitada ❌',
            'em_execucao' => 'Em Execução / Pedido Emitido ⚙️',
            'concluida'   => 'Concluída / Entregue 🏁',
        ];

        $statusTitle = $statusTitles[$newStatus] ?? ucfirst($newStatus);
        $subject = "Atualização de Status da Requisição {$requisition['code']}: {$statusTitle}";

        $body = "
        <div style='font-family:sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;'>
            <div style='border-bottom:2px solid #4f46e5;padding-bottom:12px;margin-bottom:15px;'>
                <h2 style='color:#1e293b;margin:0;'>Sua Requisição foi {$statusTitle}</h2>
                <p style='color:#64748b;font-size:13px;margin:3px 0 0;'>Código: <strong>{$requisition['code']}</strong></p>
            </div>
            <p style='font-size:14px;color:#334155;'>Olá <strong>{$requesterName}</strong>, o status da sua solicitação foi atualizado para: <strong style='color:#4f46e5;'>{$statusTitle}</strong>.</p>
            " . ($reason ? "<p style='font-size:13px;background:#fef2f2;border:1px solid #fecaca;padding:10px;border-radius:8px;color:#991b1b;'><strong>Motivo / Observações:</strong> {$reason}</p>" : "") . "
            <div style='margin-top:20px;'>
                <a href='" . url('requisicoes/ver?id=' . $requisition['id']) . "' style='background:#4f46e5;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:bold;display:inline-block;'>
                    Ver Detalhes da Requisição &rarr;
                </a>
            </div>
        </div>";

        try {
            $this->send($requesterEmail, $requesterName, $subject, $body);
        } catch (Exception $e) {
            // Silently continue
        }
    }
}
