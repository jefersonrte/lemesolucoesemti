<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/security.php';
require_once dirname(__DIR__, 2) . '/api-client.php';

apply_security_headers();
require_login(true);

relay_json(main_api_request('https://lemeinformatica.com.br/pet/api/relatorios.php'));
