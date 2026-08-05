<?php
require_once __DIR__ . '/auth/security.php';

apply_security_headers();
start_secure_session();

$next = ($_GET['next'] ?? '') === 'pet' ? 'pet' : '';
if (is_logged_in()) {
    redirect($next === 'pet' ? 'pet/' : 'index.php');
}

$erro = $_GET['erro'] ?? '';
$expirou = isset($_GET['expirou']);
$mensagem = '';

if ($erro === '1') {
    $mensagem = 'E-mail ou senha invalidos.';
} elseif ($erro === 'csrf') {
    $mensagem = 'Token de seguranca expirado. Tente novamente.';
} elseif ($erro === 'bloqueado') {
    $mensagem = 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.';
} elseif ($expirou) {
    $mensagem = 'Sua sessao expirou por inatividade. Entre novamente.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Dashboard API Leme</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
    <main class="login-card">
        <div class="login-brand">
            <p class="eyebrow">Leme Solucoes em TI</p>
            <h1>Acesso ao Dashboard</h1>
            <p>Entre para consultar, cadastrar e administrar os dados via API.</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert-error"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="login-processa.php" class="login-form" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">

            <label>
                E-mail
                <input type="email" name="email" required autocomplete="username" placeholder="admin@lemesolucoesemti.com.br">
            </label>

            <label>
                Senha
                <input type="password" name="senha" required autocomplete="current-password" placeholder="Digite sua senha">
            </label>

            <button class="btn full" type="submit">Entrar no sistema</button>
        </form>

        <p class="login-help">Primeiro acesso padrao: <strong>admin@lemesolucoesemti.com.br</strong>. Altere a senha apos instalar.</p>
    </main>
</body>
</html>
