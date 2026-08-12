<?php
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

header('Cache-Control: no-store, max-age=0');
header('Content-Type: application/json; charset=utf-8');
http_response_code(202);
echo '{"ok":true,"mensagem":"As licitacoes sao sincronizadas automaticamente a cada 6 horas."}';
