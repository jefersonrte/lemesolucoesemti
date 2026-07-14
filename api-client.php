<?php
require_once __DIR__ . '/config.php';

function main_api_request(string $endpoint, string $method = 'GET', ?string $body = null): array
{
    $url = rtrim(API_BASE_URL, '/') . '/' . ltrim($endpoint, '/');

    $headers = [
        'Accept: application/json',
        'X-API-KEY: ' . API_KEY
    ];

    if ($body !== null && $body !== '') {
        $headers[] = 'Content-Type: application/json; charset=utf-8';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($body !== null && $body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        return [
            'status' => 502,
            'body' => json_encode([
                'ok' => false,
                'erro' => 'Falha ao conectar na API principal.',
                'detalhe' => $curlError
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ];
    }

    return [
        'status' => $statusCode ?: 502,
        'body' => $responseBody
    ];
}

function relay_json(array $apiResponse): never
{
    http_response_code($apiResponse['status']);
    header('Content-Type: application/json; charset=utf-8');
    echo $apiResponse['body'];
    exit;
}
