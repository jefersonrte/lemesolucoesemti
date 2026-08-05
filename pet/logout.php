<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/client.php';

pet_dashboard_headers();
pet_dashboard_session_start();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !pet_dashboard_validate_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ./');
    exit;
}

$token = pet_dashboard_token();
if ($token !== '') {
    pet_dashboard_remote_request('sso.php', 'POST', $token, ['acao' => 'revogar']);
}
pet_dashboard_session_clear();
header('Location: ../');
exit;
