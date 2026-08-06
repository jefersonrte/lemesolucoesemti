<?php
declare(strict_types=1);

require_once __DIR__ . '/../pet/includes/session.php';

pet_dashboard_headers();
if (pet_dashboard_user() === null) {
    pet_dashboard_json_error('Usuario nao autenticado.', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    pet_dashboard_json_error('Metodo nao permitido.', 405);
}

$dataset = (string) ($_GET['dataset'] ?? 'powerbi');
$endpoints = [
    'powerbi' => 'powerbi.php',
    'dashboard' => 'dashboard.php',
    'animais' => 'animais.php',
    'alimentos' => 'alimentos.php',
];
if (!isset($endpoints[$dataset])) {
    pet_dashboard_json_error('Conjunto de dados invalido.', 400);
}

$parameters = $_GET;
unset($parameters['dataset']);
$endpoint = $endpoints[$dataset];
if ($parameters !== []) {
    $endpoint .= '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
}

require_once __DIR__ . '/../api-client.php';
relay_json(main_api_request($endpoint, 'GET'));
