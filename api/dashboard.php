<?php
require_once __DIR__ . '/../auth/security.php';
require_once __DIR__ . '/../api-client.php';

apply_security_headers();
require_login(true);

relay_json(main_api_request('dashboard.php', 'GET'));
