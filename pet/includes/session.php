<?php
declare(strict_types=1);

const PET_DASHBOARD_SESSION = 'LEME_SSO_DASHBOARD';
const PET_DASHBOARD_IDLE_SECONDS = 1800;

function pet_dashboard_headers(): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}

function pet_dashboard_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_name(PET_DASHBOARD_SESSION);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    if (isset($_SESSION['pet_last_activity'])
        && $now - (int) $_SESSION['pet_last_activity'] > PET_DASHBOARD_IDLE_SECONDS) {
        pet_dashboard_session_clear();
        pet_dashboard_session_start();
    }
    $_SESSION['pet_last_activity'] = $now;
    $_SESSION['pet_csrf'] ??= bin2hex(random_bytes(32));
}

function pet_dashboard_user(): ?array
{
    pet_dashboard_session_start();
    $user = $_SESSION['pet_user'] ?? null;
    $token = (string) ($_SESSION['pet_access_token'] ?? '');
    $expires = (int) ($_SESSION['pet_token_expires'] ?? 0);
    return is_array($user) && preg_match('/^[a-f0-9]{64}$/', $token) && $expires > time()
        ? $user
        : null;
}

function pet_dashboard_token(): string
{
    return pet_dashboard_user() === null ? '' : (string) $_SESSION['pet_access_token'];
}

function pet_dashboard_csrf(): string
{
    pet_dashboard_session_start();
    return (string) $_SESSION['pet_csrf'];
}

function pet_dashboard_validate_csrf(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(pet_dashboard_csrf(), $token);
}

function pet_dashboard_session_clear(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name(PET_DASHBOARD_SESSION);
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function pet_dashboard_json_error(string $message, int $status = 401): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'erro' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
