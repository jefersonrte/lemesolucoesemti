<?php
require_once __DIR__ . '/auth/login-service.php';

apply_security_headers();
start_secure_session();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('login.php');
}

if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
    redirect('login.php?erro=csrf');
}

$email = trim((string) ($_POST['email'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
    redirect('login.php?erro=1');
}

$identifier = login_identifier($email);

if (count_recent_failed_attempts($identifier) >= LOGIN_MAX_ATTEMPTS) {
    audit_log(null, 'login_bloqueado', 'Muitas tentativas para ' . $email);
    redirect('login.php?erro=bloqueado');
}

$user = find_active_user_by_email($email);

if (!$user || !password_verify($senha, $user['senha_hash'])) {
    register_login_attempt($identifier, $email, false);
    audit_log(null, 'login_falha', 'Falha de login para ' . $email);
    redirect('login.php?erro=1');
}

register_login_attempt($identifier, $email, true);
clear_failed_attempts($identifier);
login_user($user);

redirect('index.php');
