<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/client.php';

pet_dashboard_headers();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
pet_dashboard_session_start();

$code = trim((string) ($_GET['code'] ?? ''));
$destination = ($_GET['next'] ?? '') === 'powerbi' ? 'powerbi' : 'pet';
if (!preg_match('/^[a-f0-9]{64}$/', $code)) {
    header('Location: ' . ($destination === 'powerbi' ? '../powerbi/?erro=acesso' : './?erro=acesso'));
    exit;
}

$response = pet_dashboard_remote_request('sso.php', 'POST', null, ['codigo' => $code]);
$data = json_decode((string) $response['body'], true);
if ($response['status'] !== 200 || !is_array($data) || empty($data['ok'])
    || !preg_match('/^[a-f0-9]{64}$/', (string) ($data['data']['token'] ?? ''))
    || !is_array($data['data']['usuario'] ?? null)) {
    header('Location: ' . ($destination === 'powerbi' ? '../powerbi/?erro=acesso' : './?erro=acesso'));
    exit;
}

session_regenerate_id(true);
$_SESSION['pet_access_token'] = (string) $data['data']['token'];
$_SESSION['pet_token_expires'] = max(time() + 60, (int) ($data['data']['expira_em_epoch'] ?? 0));
$_SESSION['pet_user'] = $data['data']['usuario'];
$_SESSION['pet_last_activity'] = time();
$_SESSION['pet_csrf'] = bin2hex(random_bytes(32));

header('Location: ' . ($destination === 'powerbi' ? '../powerbi/' : './'));
exit;
