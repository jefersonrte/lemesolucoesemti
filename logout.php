<?php
require_once __DIR__ . '/auth/security.php';

apply_security_headers();
start_secure_session();

$user = current_user();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? null)) {
    audit_log($user['id'] ?? null, 'logout', 'Usuario saiu do dashboard.');
    logout_user();
    redirect('login.php');
}

// Para evitar logout forjado por link externo, GET apenas mostra uma tela simples.
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sair - Dashboard API Leme</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
    <main class="login-card compact">
        <h1>Sair do sistema</h1>
        <p>Confirme para encerrar sua sessao com seguranca.</p>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <button class="btn full" type="submit">Confirmar saida</button>
        </form>
    </main>
</body>
</html>
