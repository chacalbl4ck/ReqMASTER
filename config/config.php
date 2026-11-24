<?php
declare(strict_types=1);

// Inicializa a sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações de Fuso Horário e Idioma
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL, 'pt_BR.utf-8', 'pt_BR', 'Portuguese_Brazil');

// Detecção dinâmica de Base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = rtrim($protocol . $host . $scriptDir, '/');

// Se o scriptDir terminar em /public, o baseUrl já aponta para a pasta pública
define('BASE_URL', $baseUrl);
define('APP_NAME', 'ReqMaster - Gestão de Requisições & Contratos');
define('APP_VERSION', '2.0.0');

// ==========================================
// FUNÇÕES AUXILIARES GLOBAIS (HELPERS)
// ==========================================

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . ($path ? '/' . $path : '');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): void
{
    header("Location: " . url($path));
    exit;
}

function formatMoney(float|int|string|null $val): string
{
    $val = (float)($val ?? 0);
    return 'R$ ' . number_format($val, 2, ',', '.');
}

function formatDate(?string $date, string $format = 'd/m/Y'): string
{
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '-';
}

function formatDateTime(?string $date): string
{
    return formatDate($date, 'd/m/Y H:i');
}

function sanitize(mixed $data): string
{
    if (is_null($data)) return '';
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function statusBadge(string $status): string
{
    return match ($status) {
        'rascunho' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200"><i class="fas fa-pencil-alt mr-1"></i> Rascunho</span>',
        'pendente' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200"><i class="fas fa-clock mr-1"></i> Pendente</span>',
        'em_analise' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200"><i class="fas fa-search mr-1"></i> Em Análise</span>',
        'aprovada' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200"><i class="fas fa-check-circle mr-1"></i> Aprovada</span>',
        'rejeitada' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200"><i class="fas fa-times-circle mr-1"></i> Rejeitada</span>',
        'em_execucao' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 border border-indigo-200"><i class="fas fa-cogs mr-1"></i> Em Execução</span>',
        'concluida' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200"><i class="fas fa-flag-checkered mr-1"></i> Concluída</span>',
        'cancelada' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 border border-slate-200"><i class="fas fa-ban mr-1"></i> Cancelada</span>',
        default => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' . htmlspecialchars(ucfirst($status)) . '</span>',
    };
}

function priorityBadge(string $priority): string
{
    return match ($priority) {
        'urgente' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-600 text-white animate-pulse"><i class="fas fa-bolt mr-1"></i> Urgente</span>',
        'alta' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-orange-100 text-orange-800 border border-orange-200"><i class="fas fa-arrow-up mr-1"></i> Alta</span>',
        'media' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200"><i class="fas fa-equals mr-1"></i> Média</span>',
        'baixa' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200"><i class="fas fa-arrow-down mr-1"></i> Baixa</span>',
        default => '<span class="text-xs text-gray-500">' . htmlspecialchars(ucfirst($priority)) . '</span>',
    };
}
