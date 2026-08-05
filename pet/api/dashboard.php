<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/client.php';

pet_dashboard_headers();
$user = pet_dashboard_user();
if ($user === null) {
    pet_dashboard_json_error('A sessao integrada expirou. Entre novamente.');
}

$response = pet_dashboard_remote_request('relatorios.php', 'GET', pet_dashboard_token());
http_response_code((int) $response['status']);
header('Content-Type: application/json; charset=utf-8');
echo $response['body'];
