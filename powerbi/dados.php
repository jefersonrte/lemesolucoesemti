<?php
require_once __DIR__ . '/../auth/security.php';
require_once __DIR__ . '/../api-client.php';

apply_security_headers();
require_login(true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_error('Metodo nao permitido.', 405);
}

relay_json(main_api_request('powerbi.php', 'GET'));
