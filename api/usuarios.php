<?php
require_once __DIR__ . '/../auth/security.php';
require_once __DIR__ . '/../api-client.php';

apply_security_headers();
$loggedUser = require_role([ROLE_ADMIN], true);
require_csrf_for_state_change();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    json_error('Metodo nao permitido.', 405);
}

$endpoint = 'https://lemeinformatica.com.br/pet/usuarios.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id) {
    $endpoint .= '?id=' . (int) $id;
}

$body = null;
if ($method !== 'GET') {
    $raw = file_get_contents('php://input');
    $body = $raw === false ? '' : $raw;
}

$response = main_api_request($endpoint, $method, $body);

if ($method !== 'GET' && $response['status'] >= 200 && $response['status'] < 300) {
    audit_log(
        $loggedUser['id'],
        'usuario_central_gerenciado',
        'Operacao ' . $method . ' encaminhada para a API central.'
    );
}

relay_json($response);
