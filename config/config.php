<?php
declare(strict_types=1);

// Inicializa a sessão com parâmetros endurecidos de segurança
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
} elseif (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Configurações de Fuso Horário e Idioma
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL, 'pt_BR.utf-8', 'pt_BR', 'Portuguese_Brazil');

// Detecção dinâmica de Base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = $scriptName ? str_replace('\\', '/', dirname($scriptName)) : '';
if ($scriptDir === '.' || $scriptDir === '/' || $scriptDir === '\\') {
    $scriptDir = '';
}
$baseUrl = rtrim($protocol . $host . ($scriptDir ? '/' . ltrim($scriptDir, '/') : ''), '/');

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
    $path = ltrim($path, '/');
    if (str_starts_with($path, 'uploads/') || str_starts_with($path, 'assets/')) {
        return url($path);
    }
    return url('assets/' . $path);
}

function upload_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    if (str_starts_with($path, 'uploads/')) {
        return url($path);
    }
    return url('uploads/' . $path);
}

function current_route(): string
{
    if (isset($GLOBALS['current_route'])) {
        return $GLOBALS['current_route'];
    }
    if (isset($_GET['route'])) {
        return trim($_GET['route'], '/');
    }
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptName !== '/' && $scriptName !== '\\' && $scriptName !== '' && str_starts_with($uri, $scriptName)) {
        $uri = substr($uri, strlen($scriptName));
    }
    return trim($uri, '/');
}

function is_active(string $prefix): bool
{
    $current = current_route();
    if ($prefix === '' || $prefix === 'dashboard') {
        return $current === '' || $current === 'dashboard';
    }
    return str_starts_with($current, $prefix);
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
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

function safe_hex_color(?string $color, string $default = '#4f46e5'): string
{
    $c = trim((string)$color);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : $default;
}

function hex_to_rgb_string(string $hex): string
{
    $hex = ltrim(safe_hex_color($hex), '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "{$r}, {$g}, {$b}";
}

function bot_protection_meta(array $settings): string
{
    if (($settings['is_web_hosted'] ?? '0') === '1' && ($settings['block_search_bots'] ?? '0') === '1') {
        return '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">' . "\n    "
             . '<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">' . "\n";
    }
    return '';
}

function theme_css_vars(array $settings): string
{
    $primary   = safe_hex_color($settings['theme_primary_color'] ?? '#4f46e5', '#4f46e5');
    $secondary = safe_hex_color($settings['theme_secondary_color'] ?? '#4338ca', '#4338ca');
    $accent    = safe_hex_color($settings['theme_accent_color'] ?? '#6366f1', '#6366f1');
    $reqColor  = safe_hex_color($settings['req_primary_color'] ?? '#4338ca', '#4338ca');
    $reqTint   = safe_hex_color($settings['req_bg_tint'] ?? '#eef2ff', '#eef2ff');
    $reqBorder = safe_hex_color($settings['req_border_color'] ?? '#cbd5e1', '#cbd5e1');

    $primaryRgb = hex_to_rgb_string($primary);

    return "<style id=\"reqmaster-theme-vars\">
        :root {
            --rm-primary: {$primary};
            --rm-secondary: {$secondary};
            --rm-accent: {$accent};
            --rm-primary-rgb: {$primaryRgb};
            --rm-req-primary: {$reqColor};
            --rm-req-tint: {$reqTint};
            --rm-req-border: {$reqBorder};
        }
        /* Correção global para bordas visíveis em todos os campos */
        input:not([type=\"checkbox\"]):not([type=\"radio\"]):not([type=\"hidden\"]):not([type=\"file\"]):not([type=\"color\"]):not([type=\"range\"]),
        select,
        textarea {
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff;
        }
        input:not([type=\"checkbox\"]):not([type=\"radio\"]):not([type=\"hidden\"]):not([type=\"file\"]):not([type=\"color\"]):focus,
        select:focus,
        textarea:focus {
            border-color: var(--rm-primary) !important;
            box-shadow: 0 0 0 3px rgba(var(--rm-primary-rgb), 0.16) !important;
            outline: none !important;
        }
        /* Aplicação dinâmica do esquema de cores do site */
        .bg-indigo-600 { background-color: var(--rm-primary) !important; }
        .hover\\:bg-indigo-700:hover, .hover\\:bg-indigo-500:hover { background-color: var(--rm-secondary) !important; }
        .text-indigo-600, .text-primary-600 { color: var(--rm-primary) !important; }
        .text-indigo-700, .text-primary-700 { color: var(--rm-secondary) !important; }
        .border-indigo-600, .border-indigo-500 { border-color: var(--rm-primary) !important; }
        .bg-indigo-50 { background-color: rgba(var(--rm-primary-rgb), 0.08) !important; }
        .bg-indigo-100 { background-color: rgba(var(--rm-primary-rgb), 0.15) !important; }
        .shadow-indigo-600\\/25, .shadow-indigo-600\\/30, .shadow-primary-500\\/20 {
            box-shadow: 0 4px 12px rgba(var(--rm-primary-rgb), 0.25) !important;
        }
        /* Cores customizadas das Requisições */
        .rm-req-header { border-bottom-color: var(--rm-req-primary) !important; }
        .rm-req-accent { color: var(--rm-req-primary) !important; }
        .rm-req-badge  { background-color: var(--rm-req-tint) !important; color: var(--rm-req-primary) !important; border-color: var(--rm-req-border) !important; }
        .rm-req-card   { border-color: var(--rm-req-border) !important; }
    </style>";
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

function statusLabel(string $status): string
{
    return match ($status) {
        'rascunho'    => 'Rascunho',
        'pendente'    => 'Pendente',
        'em_analise'  => 'Em Análise',
        'aprovada'    => 'Aprovada',
        'rejeitada'   => 'Rejeitada',
        'em_execucao' => 'Em Execução',
        'concluida'   => 'Concluída',
        'cancelada'   => 'Cancelada',
        default       => ucwords(str_replace('_', ' ', $status)),
    };
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
        default => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' . htmlspecialchars(statusLabel($status)) . '</span>',
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
