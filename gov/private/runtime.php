<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config.php';

if (!defined('API_KEY') || trim((string) API_KEY) === '') {
    throw new RuntimeException('Chave da API principal nao configurada.');
}

return [
    'api_url' => 'https://lemeinformatica.com.br/gov/api/deputados.php',
    'api_key' => (string) API_KEY,
];
