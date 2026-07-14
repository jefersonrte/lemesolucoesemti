<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/conexao-local.php';

function apply_security_headers(): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (APP_ENV === 'production') {
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    }
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    $now = time();

    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = $now;
    }

    if (isset($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > SESSION_IDLE_LIMIT_SECONDS) {
        logout_user();
        redirect('login.php?expirou=1');
    }

    $_SESSION['last_activity'] = $now;

    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = $now;
    }

    if (($now - (int) $_SESSION['last_regeneration']) > SESSION_REGENERATE_SECONDS) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = $now;
    }

    if (empty($_SESSION[CSRF_SESSION_KEY])) {
        $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }
}

function csrf_token(): string
{
    start_secure_session();
    return $_SESSION[CSRF_SESSION_KEY];
}

function validate_csrf_token(?string $token): bool
{
    start_secure_session();
    return is_string($token) && hash_equals($_SESSION[CSRF_SESSION_KEY] ?? '', $token);
}

function csrf_from_request(): ?string
{
    return $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
}

function require_csrf_for_state_change(): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !validate_csrf_token(csrf_from_request())) {
        json_error('Token de seguranca invalido. Atualize a pagina e tente novamente.', 419);
    }
}

function current_user(): ?array
{
    start_secure_session();

    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['usuario_id'],
        'nome' => (string) ($_SESSION['usuario_nome'] ?? ''),
        'email' => (string) ($_SESSION['usuario_email'] ?? ''),
        'perfil' => (string) ($_SESSION['usuario_perfil'] ?? ''),
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(bool $json = false): array
{
    $user = current_user();

    if ($user !== null) {
        return $user;
    }

    if ($json) {
        json_error('Usuario nao autenticado.', 401);
    }

    redirect('login.php');
}

function require_role(array $allowedRoles, bool $json = false): array
{
    $user = require_login($json);

    if (!in_array($user['perfil'], $allowedRoles, true)) {
        if ($json) {
            json_error('Voce nao tem permissao para executar esta acao.', 403);
        }
        http_response_code(403);
        echo 'Acesso negado.';
        exit;
    }

    return $user;
}

function can_create_or_update(array $user): bool
{
    return in_array($user['perfil'], [ROLE_ADMIN, ROLE_OPERADOR], true);
}

function can_delete(array $user): bool
{
    return $user['perfil'] === ROLE_ADMIN;
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function json_error(string $message, int $status = 400, array $extra = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'ok' => false,
        'erro' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function audit_log(?int $userId, string $action, string $details = ''): void
{
    try {
        $conn = local_db();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

        $stmt = $conn->prepare('INSERT INTO auth_audit_log (usuario_id, acao, detalhes, ip, user_agent) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('issss', $userId, $action, $details, $ip, $userAgent);
        $stmt->execute();
    } catch (Throwable $e) {
        // O log nao pode derrubar o sistema.
    }
}

function logout_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
