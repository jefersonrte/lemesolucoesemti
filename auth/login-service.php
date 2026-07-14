<?php
require_once __DIR__ . '/security.php';

function login_identifier(string $email): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return strtolower(trim($email)) . '|' . $ip;
}

function count_recent_failed_attempts(string $identifier): int
{
    $conn = local_db();
    $since = date('Y-m-d H:i:s', time() - (LOGIN_LOCK_MINUTES * 60));

    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM auth_login_attempts WHERE identificador = ? AND sucesso = 0 AND criado_em >= ?');
    $stmt->bind_param('ss', $identifier, $since);
    $stmt->execute();

    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

function register_login_attempt(string $identifier, string $email, bool $success): void
{
    $conn = local_db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $successInt = $success ? 1 : 0;

    $stmt = $conn->prepare('INSERT INTO auth_login_attempts (identificador, email, ip, user_agent, sucesso) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssi', $identifier, $email, $ip, $userAgent, $successInt);
    $stmt->execute();
}

function clear_failed_attempts(string $identifier): void
{
    $conn = local_db();
    $stmt = $conn->prepare('DELETE FROM auth_login_attempts WHERE identificador = ? AND sucesso = 0');
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
}

function find_active_user_by_email(string $email): ?array
{
    $conn = local_db();
    $stmt = $conn->prepare('SELECT id, nome, email, senha_hash, perfil, ativo FROM usuarios_admin WHERE email = ? AND ativo = 1 LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();
    return $user ?: null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int) $user['id'];
    $_SESSION['usuario_nome'] = (string) $user['nome'];
    $_SESSION['usuario_email'] = (string) $user['email'];
    $_SESSION['usuario_perfil'] = (string) $user['perfil'];
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));

    $conn = local_db();
    $stmt = $conn->prepare('UPDATE usuarios_admin SET ultimo_login_em = NOW() WHERE id = ?');
    $id = (int) $user['id'];
    $stmt->bind_param('i', $id);
    $stmt->execute();

    audit_log($id, 'login_sucesso', 'Usuario autenticado no dashboard.');
}
