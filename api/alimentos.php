<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/security.php';
require_once __DIR__ . '/../api-client.php';

apply_security_headers();
$user = require_login(true);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (in_array($method, ['POST', 'PUT'], true) && !can_create_or_update($user)) {
    json_error('Seu perfil nao permite cadastrar ou editar registros.', 403);
}

if ($method === 'DELETE' && !can_delete($user)) {
    json_error('Somente administrador pode excluir registros.', 403);
}

require_csrf_for_state_change();

$query = $_SERVER['QUERY_STRING'] ?? '';
$endpoint = 'alimentos.php' . ($query ? '?' . $query : '');
$body = file_get_contents('php://input') ?: null;
$response = main_api_request($endpoint, $method, $body);

if ($response['status'] >= 200 && $response['status'] < 300 && in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    audit_log($user['id'], 'crud_alimentos_' . strtolower($method), 'Endpoint: ' . $endpoint);
}

relay_json($response);
