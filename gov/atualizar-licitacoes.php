<?php
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

header('Cache-Control: no-store, max-age=0');
header('Content-Type: application/json; charset=utf-8');
echo '{"ok":true,"mensagem":"Atualizacao agendada."}';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_flush();
    flush();
}

ignore_user_abort(true);
@set_time_limit(240);
$config = require __DIR__ . '/private/runtime.php';
$separator = strpos($config['api_url'], '?') === false ? '?' : '&';
$url = $config['api_url'] . $separator . 'acao=sincronizar';
$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 220,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'X-API-KEY: ' . $config['api_key'],
        'User-Agent: LemeLicitacoesAutoSync/1.0',
    ],
]);
curl_exec($curl);
curl_close($curl);
